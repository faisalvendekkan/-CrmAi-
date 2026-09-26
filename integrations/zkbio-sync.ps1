param(
    [switch]$Setup,
    [string]$HrmsUrl,
    [string]$ZkbioUrl = 'http://127.0.0.1',
    [string]$TerminalSn = '',
    [ValidateRange(1, 90)][int]$LookbackDays = 7
)

$ErrorActionPreference = 'Stop'
$stateDir = Join-Path $env:LOCALAPPDATA 'MeridianZKBio'
$configPath = Join-Path $stateDir 'config.json'
$credentialPath = Join-Path $stateDir 'zkbio-credential.xml'
$tokenPath = Join-Path $stateDir 'hrms-token.txt'

if ($Setup) {
    if (-not $HrmsUrl) { throw 'Provide -HrmsUrl with your Meridian HR HTTPS website URL.' }
    $hrmsUri = [uri]$HrmsUrl
    $zkbioUri = [uri]$ZkbioUrl
    if ($hrmsUri.Scheme -ne 'https') { throw 'Meridian HR must use HTTPS.' }
    if ($zkbioUri.Scheme -ne 'https' -and -not ($zkbioUri.Scheme -eq 'http' -and $zkbioUri.Host -in @('127.0.0.1', 'localhost', '::1'))) {
        throw 'Use HTTPS for remote ZKBio Time, or HTTP on this computer at 127.0.0.1.'
    }
    New-Item -ItemType Directory -Path $stateDir -Force | Out-Null
    $credential = Get-Credential -Message 'Enter the ZKBio Time API administrator username and password'
    if (-not $credential) { throw 'ZKBio Time credentials were not entered.' }
    $hrmsToken = Read-Host 'Enter the Meridian HR biometric integration token' -AsSecureString
    if ($hrmsToken.Length -lt 32 -or $hrmsToken.Length -gt 200) { throw 'The Meridian HR token must have 32 to 200 characters.' }
    $credential | Export-Clixml -LiteralPath $credentialPath
    $hrmsToken | ConvertFrom-SecureString | Set-Content -LiteralPath $tokenPath
    @{
        hrms_url = $hrmsUri.GetLeftPart([System.UriPartial]::Authority) + $hrmsUri.AbsolutePath.TrimEnd('/')
        zkbio_url = $zkbioUri.GetLeftPart([System.UriPartial]::Authority) + $zkbioUri.AbsolutePath.TrimEnd('/')
        terminal_sn = $TerminalSn
        lookback_days = $LookbackDays
    } | ConvertTo-Json | Set-Content -LiteralPath $configPath -Encoding UTF8
    Write-Host "Saved encrypted credentials for the current Windows user in $stateDir. Run this script again without -Setup to sync."
    exit 0
}

if (-not (Test-Path -LiteralPath $configPath) -or -not (Test-Path -LiteralPath $credentialPath) -or -not (Test-Path -LiteralPath $tokenPath)) {
    throw 'Sync is not configured. Run this script once with -Setup and -HrmsUrl.'
}
$config = Get-Content -LiteralPath $configPath -Raw | ConvertFrom-Json
$effectiveLookbackDays = if ($PSBoundParameters.ContainsKey('LookbackDays')) { $LookbackDays } else { [int]$config.lookback_days }
$credential = Import-Clixml -LiteralPath $credentialPath
$secureToken = (Get-Content -LiteralPath $tokenPath -Raw).Trim() | ConvertTo-SecureString
$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureToken)
try {
    $hrmsToken = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)
}

$authBody = @{ username = $credential.UserName; password = $credential.GetNetworkCredential().Password } | ConvertTo-Json -Compress
$zkbioBase = ([string]$config.zkbio_url).TrimEnd('/')
$hrmsBase = ([string]$config.hrms_url).TrimEnd('/')
$hrmsUri = [uri]$hrmsBase
$zkbioUri = [uri]$zkbioBase
if ($hrmsUri.Scheme -ne 'https') { throw 'Meridian HR must use HTTPS.' }
if ($zkbioUri.Scheme -ne 'https' -and -not ($zkbioUri.Scheme -eq 'http' -and $zkbioUri.Host -in @('127.0.0.1', 'localhost', '::1'))) {
    throw 'Use HTTPS for remote ZKBio Time, or HTTP on this computer at 127.0.0.1.'
}
$auth = Invoke-RestMethod -Method Post -Uri "$zkbioBase/api-token-auth/" -ContentType 'application/json' -Body $authBody -TimeoutSec 30
if (-not $auth.token) { throw 'ZKBio Time did not return an API token.' }
$zkbioHeaders = @{ Authorization = "Token $($auth.token)"; Accept = 'application/json' }
$hrmsHeaders = @{ Authorization = "Bearer $hrmsToken"; Accept = 'application/json' }
$startTime = (Get-Date).AddDays(-$effectiveLookbackDays).ToString('yyyy-MM-dd HH:mm:ss')
$total = 0
$imported = 0
$unmatched = @{}

for ($page = 1; $page -le 1000; $page++) {
    $query = "page=$page&page_size=100&ordering=id&start_time=$([uri]::EscapeDataString($startTime))"
    if ($config.terminal_sn) { $query += "&terminal_sn=$([uri]::EscapeDataString([string]$config.terminal_sn))" }
    $response = Invoke-RestMethod -Method Get -Uri "$zkbioBase/iclock/api/transactions/?$query" -Headers $zkbioHeaders -TimeoutSec 60
    if ($response.code -ne 0) { throw "ZKBio Time returned API error code $($response.code)." }
    $rows = @($response.data | Where-Object { $null -ne $_ })
    if ($rows.Count -eq 0) { break }
    $punches = @($rows | ForEach-Object {
        @{
            id = $_.id
            emp_code = $_.emp_code
            punch_time = $_.punch_time
            punch_state = $_.punch_state
            verify_type = $_.verify_type
            terminal_sn = $_.terminal_sn
        }
    })
    $body = @{ transactions = $punches } | ConvertTo-Json -Depth 5 -Compress
    $result = Invoke-RestMethod -Method Post -Uri "$hrmsBase/api/biometrics/transactions" -Headers $hrmsHeaders -ContentType 'application/json' -Body $body -TimeoutSec 60
    if (-not $result.ok) { throw 'Meridian HR rejected the transaction batch.' }
    $total += $rows.Count
    $imported += [int]$result.imported
    foreach ($code in @($result.unmatched_codes)) { if ($code) { $unmatched[[string]$code] = $true } }
    if (-not $response.next) { break }
    if ($page -eq 1000) { throw 'Stopped after 1,000 pages. Reduce the lookback period.' }
}

Write-Host "Checked $total punches; imported $imported new punches."
if ($unmatched.Count -gt 0) {
    Write-Warning "Unmatched Employee IDs: $($unmatched.Keys -join ', '). Update those employees in Meridian HR, then use Match biometric punches on the Attendance page."
}
