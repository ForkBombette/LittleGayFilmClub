<?php
declare(strict_types=1);
namespace LGFC;
use PDO;
use DomainException;

final class Auth
{
    public const COOKIE = 'lgfc_login';
    public const SESSION_SECONDS = 90 * 86400;
    public static function migrate(PDO $pdo): void
    {
        $columns = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('role', $columns, true)) $pdo->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'member' CHECK (role IN ('member','organiser'))");
        $pdo->exec('CREATE TABLE IF NOT EXISTS login_links (token_hash TEXT PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id), expires_at INTEGER NOT NULL);
            CREATE TABLE IF NOT EXISTS auth_sessions (token_hash TEXT PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id), expires_at INTEGER NOT NULL)');
    }
    private static function transaction(PDO $pdo, callable $fn): mixed
    {
        $pdo->exec('BEGIN IMMEDIATE');
        try { $result = $fn(); $pdo->exec('COMMIT'); return $result; }
        catch (\Throwable $error) { $pdo->exec('ROLLBACK'); throw $error; }
    }
    public static function user(PDO $pdo, string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) return null;
        $stmt = $pdo->prepare('SELECT u.id,u.display_name,u.role FROM auth_sessions s JOIN users u ON u.id=s.user_id WHERE s.token_hash=? AND s.expires_at>? AND u.is_active=1');
        $stmt->execute([hash('sha256', $token), time()]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) return null;
        $user['id'] = (int) $user['id'];
        return $user;
    }
    public static function requireOrganiser(PDO $pdo, int $actor): void
    {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id=? AND is_active=1 AND role='organiser'");
        $stmt->execute([$actor]);
        if (!$stmt->fetchColumn()) throw new DomainException('Organiser permission required.');
    }
    public static function issue(PDO $pdo, int $actor, int $target): string
    {
        return self::transaction($pdo, static function () use ($pdo,$actor,$target): string {
            self::requireOrganiser($pdo,$actor);
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id=? AND is_active=1'); $stmt->execute([$target]);
            if (!$stmt->fetchColumn()) throw new DomainException('Choose an active member.');
            $stmt = $pdo->prepare('DELETE FROM login_links WHERE user_id=?'); $stmt->execute([$target]);
            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare('INSERT INTO login_links VALUES (?,?,?)');
            $stmt->execute([hash('sha256',$token),$target,time()+7*86400]);
            return $token;
        });
    }
    public static function exchange(PDO $pdo, string $token): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) throw new DomainException('This link is invalid or expired. Ask an organiser for a new one.');
        return self::transaction($pdo, static function () use ($pdo,$token): string {
            $stmt = $pdo->prepare('SELECT l.user_id FROM login_links l JOIN users u ON u.id=l.user_id WHERE token_hash=? AND expires_at>? AND u.is_active=1');
            $stmt->execute([hash('sha256',$token),time()]); $id = $stmt->fetchColumn();
            if (!$id) throw new DomainException('This link is invalid or expired. Ask an organiser for a new one.');
            $stmt = $pdo->prepare('DELETE FROM login_links WHERE token_hash=?'); $stmt->execute([hash('sha256',$token)]);
            $session = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare('INSERT INTO auth_sessions VALUES (?,?,?)');
            $stmt->execute([hash('sha256',$session),(int)$id,time()+self::SESSION_SECONDS]);
            return $session;
        });
    }
    public static function revoke(PDO $pdo, int $actor, int $target): void
    {
        self::transaction($pdo, static function () use ($pdo,$actor,$target): void {
            self::requireOrganiser($pdo,$actor);
            foreach (['login_links','auth_sessions'] as $table) {
                $stmt=$pdo->prepare("DELETE FROM {$table} WHERE user_id=?"); $stmt->execute([$target]);
            }
        });
    }
    public static function logout(PDO $pdo, string $token): void
    {
        $stmt=$pdo->prepare('DELETE FROM auth_sessions WHERE token_hash=?'); $stmt->execute([hash('sha256',$token)]);
    }
    public static function https(): bool { return !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off'; }
    public static function transportAllowed(): bool
    {
        // Direct loopback is the sole HTTP development exception. Never trust forwarded headers.
        return self::https() || (is_file(dirname(__DIR__) . '/var/allow-local-http')
            && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true));
    }
    public static function cookie(string $value, int $expiry): void
    {
        setcookie(self::COOKIE,$value,['expires'=>$expiry,'path'=>'/','secure'=>self::https(),'httponly'=>true,'samesite'=>'Strict']);
    }
    public static function requireUser(PDO $pdo, bool $json=false): array
    {
        if (!self::transportAllowed()) { http_response_code(403); exit($json ? json_encode(['error'=>'HTTPS is required.']) : 'HTTPS is required.'); }
        $user=self::user($pdo,(string)($_COOKIE[self::COOKIE] ?? ''));
        if (!$user) {
            if ($json) { http_response_code(401); echo json_encode(['error'=>'Please sign in again. Your draft has not been saved.']); }
            else { header('Location: login.php',true,303); }
            exit;
        }
        return $user;
    }
    public static function guardOrganiser(PDO $pdo,array $user): void
    {
        try { self::requireOrganiser($pdo,$user['id']); }
        catch (DomainException $error) { http_response_code(403); exit('Organiser permission required.'); }
    }
    public static function accountBar(array $user): void
    {
        echo '<p>Signed in as ' . Web::escape($user['display_name']) . ' · <a href="account.php">Account</a></p>';
    }
}
