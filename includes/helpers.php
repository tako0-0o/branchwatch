<?php

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money($n): string
{
    $n = (float)$n;
    return ($n < 0 ? '-' : '') . CURRENCY . number_format(abs($n), 2);
}

function base_path(): string
{
    static $base = null;
    if ($base !== null) return $base;
    if (BASE_URL !== null) return $base = rtrim(BASE_URL, '/');

    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $app  = realpath(__DIR__ . '/..') ?: '';
    $root = str_replace('\\', '/', $root);
    $app  = str_replace('\\', '/', $app);
    if ($root !== '' && str_starts_with($app, $root)) {
        return $base = rtrim(substr($app, strlen($root)), '/');
    }
    return $base = '';
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

// notif
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}


function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $t = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($t) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

function check_csrf_or_bounce(): void
{
    if (!csrf_verify()) {
        flash('error', 'Your session expired. Please try again.');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        header('Location: ' . $path);
        exit;
    }
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    $d = json_decode($raw ?: '', true);
    return is_array($d) ? $d : [];
}

// logs
function log_activity(string $action, string $detail = '', ?int $branchId = null, ?int $userId = null): void
{
    $u = current_user();
    $userId   ??= $u['id'] ?? null;
    $branchId ??= $u['branch_id'] ?? null;
    $st = db()->prepare('INSERT INTO activity_log (user_id, branch_id, action, detail) VALUES (?,?,?,?)');
    $st->execute([$userId, $branchId, $action, mb_substr($detail, 0, 255)]);
}

function valid_date(?string $s): ?string
{
    if (!$s) return null;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}
