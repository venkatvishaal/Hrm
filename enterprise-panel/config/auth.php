<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e(string|int|float|null $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function hasRole(string|array $requiredRoles): bool
{
    $roles = array_map('strtolower', (array)$requiredRoles);
    $activeRole = strtolower((string)($_SESSION['user_role'] ?? ''));
    return $activeRole !== '' && in_array($activeRole, $roles, true);
}

function requireRole(string|array $requiredRoles): void
{
    if (!hasRole($requiredRoles)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo '403 Forbidden';
        exit;
    }
}

function render_csrf_token(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token'] ?? '') . '">';
}

function request_csrf_token(): string
{
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (is_string($headerToken) && $headerToken !== '') {
        return $headerToken;
    }
    return is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
}

function validate_csrf_token(): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $requestToken = request_csrf_token();
    return is_string($sessionToken)
        && is_string($requestToken)
        && $sessionToken !== ''
        && $requestToken !== ''
        && hash_equals($sessionToken, $requestToken);
}

function require_valid_csrf(): void
{
    if (!validate_csrf_token()) {
        http_response_code(419);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}
