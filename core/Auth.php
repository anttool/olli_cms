<?php
declare(strict_types=1);

namespace OlliCMS;

class Auth
{
    private Storage $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
        if (session_status() === PHP_SESSION_NONE) {
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? '') == 443);

            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['olli_user']);
    }

    public function getUser(): ?array
    {
        return $_SESSION['olli_user'] ?? null;
    }

    public function getCsrfToken(): string
    {
        return $_SESSION['csrf_token'] ?? '';
    }

    public function verifyCsrf(?string $token): bool
    {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public function isRateLimited(): bool
    {
        $attempts = $_SESSION['login_attempts'] ?? 0;
        $lockUntil = $_SESSION['login_lock_until'] ?? 0;
        if ($lockUntil > time()) {
            return true;
        }
        if ($lockUntil !== 0 && $lockUntil <= time()) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_lock_until'] = 0;
        }
        return false;
    }

    public function login(string $username, string $password): bool
    {
        if ($this->isRateLimited()) {
            return false;
        }

        $data = $this->storage->readJson('config/users.json', ['users' => []]);
        foreach ($data['users'] ?? [] as $user) {
            if (strcasecmp($user['username'] ?? '', trim($username)) === 0) {
                if (password_verify($password, $user['password_hash'] ?? '')) {
                    session_regenerate_id(true);
                    $_SESSION['login_attempts'] = 0;
                    $_SESSION['login_lock_until'] = 0;
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $_SESSION['olli_user'] = [
                        'username' => $user['username'],
                        'display_name' => $user['display_name'] ?? $user['username'],
                        'role' => $user['role'] ?? 'admin',
                    ];
                    return true;
                }
            }
        }

        // Kirjataan epäonnistunut yritys (Brute-force suojaus: 5 yritystä -> 2 min lukitus)
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        if ($_SESSION['login_attempts'] >= 5) {
            $_SESSION['login_lock_until'] = time() + 120;
        }
        return false;
    }

    public function logout(): void
    {
        unset($_SESSION['olli_user'], $_SESSION['olli_edit_mode']);
        session_regenerate_id(true);
    }

    public function changePassword(string $username, string $newPassword): bool
    {
        if (strlen($newPassword) < 8) {
            return false;
        }
        $data = $this->storage->readJson('config/users.json', ['users' => []]);
        $updated = false;
        foreach ($data['users'] as &$user) {
            if (strcasecmp($user['username'] ?? '', $username) === 0) {
                $user['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                $updated = true;
                break;
            }
        }
        if ($updated) {
            return $this->storage->writeJson('config/users.json', $data);
        }
        return false;
    }
}
