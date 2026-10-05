<?php
namespace App\Core;

class Auth
{
    private Database $db;

    // Maximum failed login attempts within the window before lockout.
    private const MAX_ATTEMPTS = 5;
    // Sliding window in seconds (15 minutes).
    private const WINDOW_SECONDS = 900;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function attempt(string $identifier, string $password): bool
    {
        $now  = time();
        $identifier = trim($identifier);

        // ── STEP 1: Rate-limit check BEFORE any DB query ──────────────────────
        // Critical fix: previously this check ran AFTER password_verify(), which
        // allowed a correct password to bypass the limiter on attempt 6+.
        $attempts = array_values(array_filter(
            (array)($_SESSION['_login_rate'] ?? []),
            static fn($ts) => is_int($ts) && $ts > $now - self::WINDOW_SECONDS
        ));
        if (count($attempts) >= self::MAX_ATTEMPTS) {
            // Too many recent failures — block immediately, no DB hit.
            return false;
        }

        // Look up the user by employee_code, username, email, or name
        $user = $this->db->fetch(
            'SELECT u.*, e.employee_code
             FROM users u
             LEFT JOIN employees e ON e.user_id = u.id
             WHERE e.employee_code = :id1
                OR (u.username IS NOT NULL AND u.username = :id2)
                OR (u.email IS NOT NULL AND u.email <> "" AND u.email = :id3)
                OR u.name = :id4
             LIMIT 1',
            [
                'id1' => $identifier,
                'id2' => $identifier,
                'id3' => $identifier,
                'id4' => $identifier,
            ]
        );

        // ── STEP 3: Verify password ────────────────────────────────────────────
        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Record failed attempt AFTER the rate-limit window check.
            $attempts[] = $now;
            $_SESSION['_login_rate'] = $attempts;
            return false;
        }

        // ── STEP 4: Rehash if PHP's default algorithm has been upgraded ────────
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $this->db->execute(
                'UPDATE users SET password_hash = :hash WHERE id = :id',
                ['hash' => $newHash, 'id' => (int)$user['id']]
            );
        }

        // ── STEP 5: Successful login — reset rate limiter, start session ───────
        unset($_SESSION['_login_rate']);
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'                   => (int)$user['id'],
            'name'                 => $user['name'],
            'email'                => $user['email'],
            'role'                 => $user['role'],
            'force_password_change' => (int)($user['force_password_change'] ?? 0),
        ];
        return true;
    }

    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public function mustChangePassword(): bool
    {
        if (empty($_SESSION['user']['id'])) {
            return false;
        }
        $row = $this->db->fetch('SELECT force_password_change FROM users WHERE id=:id LIMIT 1', ['id' => (int)$_SESSION['user']['id']]);
        $mustChange = !empty($row['force_password_change']);
        $_SESSION['user']['force_password_change'] = $mustChange ? 1 : 0;
        return $mustChange;
    }

    public function clearPasswordChangeRequirement(): void
    {
        if (isset($_SESSION['user'])) {
            $_SESSION['user']['force_password_change'] = 0;
        }
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
