<?php
declare(strict_types=1);

/** Escape for HTML output. Use for every dynamic value in views. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Base path of the app (supports installs in a sub-folder). */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $base   = rtrim(dirname($script), '/');
        if ($base === '.' ) {
            $base = '';
        }
    }
    return $base;
}

function url(string $path = '', array $query = []): string
{
    $u = base_path() . '/' . ltrim($path, '/');
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u === '' ? '/' : $u;
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . ltrim($path, '/');
    $v    = is_file($file) ? (string) filemtime($file) : APP_VERSION;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function redirect(string $path, array $query = []): never
{
    header('Location: ' . url($path, $query), true, 303);
    exit;
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Trimmed string input from POST (default) or GET. */
function input(string $key, string $default = '', string $source = 'post'): string
{
    $bag = $source === 'get' ? $_GET : $_POST;
    $v   = $bag[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function json_body(): array
{
    static $body = null;
    if ($body === null) {
        $raw  = file_get_contents('php://input') ?: '';
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            $body = [];
        }
    }
    return $body;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function old(string $key, mixed $default = ''): string
{
    return (string) ($_SESSION['_old'][$key] ?? $default);
}

function remember_input(array $data): void
{
    unset($data['_csrf'], $data['password'], $data['password_confirm'], $data['current_password']);
    $_SESSION['_old'] = $data;
}

function forget_input(): void
{
    unset($_SESSION['_old']);
}

function user(): ?array
{
    return Auth::user();
}

function can(string $perm, string $level = 'view'): bool
{
    return Auth::can($perm, $level);
}

function setting(string $key, mixed $default = null): mixed
{
    return Settings::get($key, $default);
}

function today(): string
{
    return date('Y-m-d');
}

function valid_date(?string $v): bool
{
    if (!$v || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return false;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $v));
    return checkdate($m, $d, $y);
}

function days_until(?string $date): ?int
{
    if (!valid_date($date)) {
        return null;
    }
    $t = new DateTimeImmutable(today());
    $d = new DateTimeImmutable($date);
    return (int) $t->diff($d)->format('%r%a');
}

/** Risk bucket for a date: expired | due | ok | none */
function date_risk(?string $date, ?int $window = null): string
{
    $days = days_until($date);
    if ($days === null) {
        return 'none';
    }
    $window ??= (int) setting('alert_window', 30);
    if ($days < 0) {
        return 'expired';
    }
    return $days <= $window ? 'due' : 'ok';
}

function fmt_date(?string $date, bool $withYear = true): string
{
    if (!valid_date($date)) {
        return '—';
    }
    return date($withYear ? 'j M Y' : 'j M', strtotime($date));
}

function fmt_datetime(?string $dt): string
{
    if (!$dt) {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts ? date('j M Y, H:i', $ts) : '—';
}

/** "in 12 days", "today", "3 days ago" */
function rel_days(?int $days): string
{
    if ($days === null) {
        return '';
    }
    if ($days === 0) {
        return 'Today';
    }
    if ($days === 1) {
        return 'Tomorrow';
    }
    if ($days === -1) {
        return 'Yesterday';
    }
    return $days > 0 ? "In {$days} days" : abs($days) . ' days ago';
}

function time_ago(?string $dt): string
{
    if (!$dt) {
        return 'Never';
    }
    $diff = time() - (int) strtotime($dt);
    return match (true) {
        $diff < 60      => 'Just now',
        $diff < 3600    => floor($diff / 60) . ' min ago',
        $diff < 86400   => floor($diff / 3600) . ' h ago',
        $diff < 2592000 => floor($diff / 86400) . ' d ago',
        default         => fmt_date(date('Y-m-d', (int) strtotime($dt))),
    };
}

function initials(?string $name): string
{
    $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
    $out   = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out ?: '?';
}

function humanize(?string $value): string
{
    $v = str_replace(['_', '-'], ' ', (string) $value);
    return mb_strtoupper(mb_substr($v, 0, 1)) . mb_substr($v, 1);
}

/** Semantic tone for a status value, used for badge colour. */
function tone(?string $status): string
{
    return match ((string) $status) {
        'active', 'approved', 'present', 'done', 'in_use', 'hired', 'ok', 'valid', 'available' => 'ok',
        'pending', 'due', 'late', 'in_progress', 'maintenance', 'on_leave', 'interview', 'medium', 'review', 'remote', 'shortlisted' => 'warn',
        'expired', 'rejected', 'absent', 'suspended', 'high', 'inactive', 'retired' => 'danger',
        default => 'neutral',
    };
}

function badge(?string $status, ?string $label = null): string
{
    if ($status === null || $status === '') {
        return '<span class="muted">—</span>';
    }
    return '<span class="badge tone-' . tone($status) . '">' . e($label ?? humanize($status)) . '</span>';
}

/** Badge describing an expiry date, e.g. "Expired 3 days ago". */
function expiry_badge(?string $date): string
{
    $days = days_until($date);
    if ($days === null) {
        return '<span class="muted">—</span>';
    }
    $risk  = date_risk($date);
    $label = match ($risk) {
        'expired' => $days === 0 ? 'Expires today' : 'Expired',
        'due'     => $days === 0 ? 'Expires today' : "{$days} days left",
        default   => 'Valid',
    };
    if ($risk === 'ok') {
        return '<span class="date-cell"><span>' . e(fmt_date($date)) . '</span></span>';
    }
    $t = $risk === 'due' ? 'warn' : 'danger';
    return '<span class="date-cell"><span>' . e(fmt_date($date)) . '</span><span class="badge tone-' . $t . '">' . e($label) . '</span></span>';
}

function icon(string $name, string $class = 'i'): string
{
    return '<svg class="' . e($class) . '" aria-hidden="true"><use href="#i-' . e($name) . '"></use></svg>';
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function view(string $template, array $data = [], ?string $layout = 'layouts/app'): never
{
    View::render($template, $data, $layout);
    exit;
}

function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    $wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    if ($wantsJson) {
        json_out(['ok' => false, 'error' => $message ?: 'Request failed.'], $code);
    }
    View::render('errors/error', [
        'code'    => $code,
        'message' => $message ?: match ($code) {
            403     => 'You do not have access to this page. Ask an administrator to grant access.',
            404     => 'This page does not exist.',
            419     => 'Your session expired. Go back, refresh the page and try again.',
            429     => 'Too many attempts. Wait a few minutes and try again.',
            default => 'Something went wrong.',
        },
    ], Auth::user() ? 'layouts/app' : 'layouts/bare');
    exit;
}

/** Neutralise spreadsheet formula injection for CSV exports. */
function csv_safe(mixed $v): string
{
    $s = (string) ($v ?? '');
    return preg_match('/^[=+\-@\t\r]/', $s) ? "'" . $s : $s;
}
