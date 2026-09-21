<?php
/**
 * Daily expiry email digest.
 *
 * Hostinger: hPanel → Advanced → Cron Jobs → Custom PHP
 *   Command:  /usr/bin/php /home/USER/domains/YOURDOMAIN/public_html/app/cli/digest.php
 *   Schedule: once a day, for example 0 7 * * *
 *
 * Runs from the command line only.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/app/bootstrap.php';

if (!Config::installed()) {
    fwrite(STDERR, "Not installed yet.\n");
    exit(1);
}
date_default_timezone_set((string) setting('timezone', 'Asia/Qatar'));
Migrator::ensure();

if (setting('digest_enabled') !== '1') {
    echo "Email digest is turned off in Expiry alerts.\n";
    exit(0);
}
$to = array_filter(array_map('trim', explode(',', (string) setting('digest_recipients', ''))));
if (!$to) {
    echo "No recipients configured.\n";
    exit(0);
}

$items = Alerts::items(['check_perms' => false]);
if (!$items) {
    echo "Nothing due. No email sent.\n";
    exit(0);
}

$company = (string) setting('company_name');
$expired = array_filter($items, fn ($i) => $i['risk'] === 'expired');
$due     = array_filter($items, fn ($i) => $i['risk'] === 'due');

$row = fn ($i) => '<tr><td style="padding:8px 12px;border-bottom:1px solid #e6e8ee">' . e($i['title']) . '<br><span style="color:#6b7280;font-size:12px">' . e($i['sub']) . '</span></td>'
    . '<td style="padding:8px 12px;border-bottom:1px solid #e6e8ee;white-space:nowrap">' . e(fmt_date($i['date'])) . '</td>'
    . '<td style="padding:8px 12px;border-bottom:1px solid #e6e8ee;white-space:nowrap;color:' . ($i['risk'] === 'expired' ? '#b42318' : '#8a5a00') . '">' . e(rel_days($i['days'])) . '</td></tr>';

$html = '<div style="font-family:-apple-system,Segoe UI,Arial,sans-serif;color:#111827;max-width:640px">'
    . '<h2 style="margin:0 0 4px">' . e($company) . ' — expiry digest</h2>'
    . '<p style="color:#6b7280;margin:0 0 20px">' . e(date('l j F Y')) . ': ' . count($expired) . ' expired, ' . count($due) . ' due within ' . e(setting('alert_window')) . ' days.</p>'
    . '<table style="border-collapse:collapse;width:100%;font-size:14px">' . implode('', array_map($row, array_slice($items, 0, 80))) . '</table>'
    . '<p style="color:#9ca3af;font-size:12px;margin-top:20px">Sent by Meridian HR. Change recipients in Expiry alerts.</p></div>';

$host = parse_url('//' . gethostname(), PHP_URL_HOST) ?: 'localhost';
$headers = implode("\r\n", [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: ' . mb_encode_mimeheader($company . ' HR') . ' <no-reply@' . $host . '>',
]);
$subject = mb_encode_mimeheader(count($expired) . ' expired, ' . count($due) . ' due soon — ' . $company);

$sent = 0;
foreach ($to as $addr) {
    if (filter_var($addr, FILTER_VALIDATE_EMAIL) && mail($addr, $subject, $html, $headers)) {
        $sent++;
    }
}
Activity::log('digest_sent', 'notifications', null, "Emailed expiry digest to {$sent} recipient(s)", null);
echo "Sent to {$sent} recipient(s).\n";
