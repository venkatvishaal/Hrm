<?php
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return null;
    }
    $message = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $message;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function require_csrf(): void
{
    $token = (string)($_POST['_csrf'] ?? '');
    if ($token === '' || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        exit('Invalid security token');
    }
}

function require_auth($auth): void
{
    if (!$auth->check()) {
        redirect('?route=login');
    }
}

function require_role($auth, array $roles): void
{
    require_auth($auth);
    $user = $auth->user();
    if (($user['role'] ?? '') === 'SuperAdmin') {
        return;
    }
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function require_password_current($auth, string $route): void
{
    require_auth($auth);
    if (method_exists($auth, 'mustChangePassword') && $auth->mustChangePassword() && !in_array($route, ['force-password', 'force-password.update', 'logout'], true)) {
        redirect('?route=force-password');
    }
}

function fmt_date(?string $value): string
{
    if (!$value) {
        return '';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return (string)$value;
    }
    return date('d/m/Y', $ts);
}

function fmt_datetime(?string $value): string
{
    if (!$value) {
        return '';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return (string)$value;
    }
    return date('d/m/Y H:i', $ts);
}
