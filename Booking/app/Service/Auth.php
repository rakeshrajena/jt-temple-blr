<?php
declare(strict_types=1);

namespace SJT\Service;

use SJT\Core\Database;
use SJT\Core\RateLimiter;
use SJT\Core\Session;

/**
 * Administrator authentication: bcrypt/argon password hashes, per-account
 * lockout, per-IP throttling, session fixation protection, idle timeout.
 */
final class Auth
{
    public const IDLE_SECONDS = 1800;      // 30 minutes
    public const ABSOLUTE_SECONDS = 43200; // 12 hours
    private const MAX_FAILED = 5;
    private const LOCK_MINUTES = 15;

    private ?array $user = null;

    public function __construct(private Database $db, private RateLimiter $limiter)
    {
    }

    public static function hash(string $password): string
    {
        return password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
    }

    public static function passwordProblem(string $password): ?string
    {
        if (strlen($password) < 10) {
            return 'Password must be at least 10 characters.';
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return 'Password must contain letters and numbers.';
        }
        return null;
    }

    /** @return array{ok:bool, error:?string} */
    public function attempt(string $username, string $password, string $ip): array
    {
        $generic = 'Incorrect username or password.';
        if (!$this->limiter->hit('login-ip:' . $ip, 20, 900)) {
            return ['ok' => false, 'error' => 'Too many login attempts from this network. Please wait 15 minutes.'];
        }
        $u = $this->db->one('SELECT * FROM admins WHERE username = ?', [strtolower(trim($username))]);
        if (!$u || !(int) $u['is_active']) {
            password_verify($password, '$2y$10$usesomesillystringforsaltpaddingxxxxxxxxxxxxxxxxxxxxxx'); // equalise timing
            return ['ok' => false, 'error' => $generic];
        }
        if ($u['locked_until'] && $u['locked_until'] > now()) {
            return ['ok' => false, 'error' => 'This account is temporarily locked after repeated failed attempts. Try again after ' . date('g:i A', strtotime($u['locked_until'])) . '.'];
        }
        if (!password_verify($password, $u['password_hash'])) {
            $failed = (int) $u['failed_logins'] + 1;
            $lock = $failed >= self::MAX_FAILED ? date('Y-m-d H:i:s', time() + 60 * self::LOCK_MINUTES) : null;
            $this->db->update('admins', ['failed_logins' => $lock ? 0 : $failed, 'locked_until' => $lock, 'updated_at' => now()], 'id = :id', ['id' => (int) $u['id']]);
            (new Audit($this->db))->log(['id' => (int) $u['id'], 'name' => 'admin:' . $u['username']], 'admin', $u['id'], null, $lock ? 'auth.locked' : 'auth.failed', []);
            return ['ok' => false, 'error' => $generic];
        }
        if (password_needs_rehash($u['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
            $this->db->update('admins', ['password_hash' => self::hash($password)], 'id = :id', ['id' => (int) $u['id']]);
        }
        $this->db->update('admins', ['failed_logins' => 0, 'locked_until' => null, 'last_login_at' => now(), 'updated_at' => now()], 'id = :id', ['id' => (int) $u['id']]);
        Session::regenerate();
        Session::set('admin_id', (int) $u['id']);
        Session::set('admin_login_at', time());
        Session::set('admin_seen_at', time());
        (new Audit($this->db))->log(['id' => (int) $u['id'], 'name' => 'admin:' . $u['username']], 'admin', $u['id'], null, 'auth.login', []);
        return ['ok' => true, 'error' => null];
    }

    public function user(): ?array
    {
        if ($this->user !== null) {
            return $this->user;
        }
        $id = Session::get('admin_id');
        if (!$id) {
            return null;
        }
        $t = time();
        if ($t - (int) Session::get('admin_seen_at', 0) > self::IDLE_SECONDS || $t - (int) Session::get('admin_login_at', 0) > self::ABSOLUTE_SECONDS) {
            $this->logout();
            Session::flash('info', 'You were signed out after a period of inactivity. Please sign in again.');
            return null;
        }
        $u = $this->db->one('SELECT id, username, name, role, is_active FROM admins WHERE id = ?', [(int) $id]);
        if (!$u || !(int) $u['is_active']) {
            $this->logout();
            return null;
        }
        Session::set('admin_seen_at', $t);
        $u['id'] = (int) $u['id'];
        return $this->user = $u;
    }

    public function actor(): array
    {
        $u = $this->user();
        return ['id' => $u['id'] ?? null, 'name' => 'admin:' . ($u['username'] ?? 'unknown')];
    }

    public function logout(): void
    {
        Session::forget('admin_id');
        Session::forget('admin_login_at');
        Session::forget('admin_seen_at');
        Session::regenerate();
        $this->user = null;
    }
}
