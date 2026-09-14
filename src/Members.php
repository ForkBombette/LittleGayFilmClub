<?php
declare(strict_types=1);
namespace LGFC;
use PDO;
use DomainException;

final class Members
{
    private static function write(PDO $pdo, int $actor, callable $operation): mixed
    {
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            Auth::requireOrganiser($pdo, $actor);
            $result = $operation();
            $pdo->exec('COMMIT');
            return $result;
        } catch (\Throwable $error) {
            $pdo->exec('ROLLBACK'); throw $error;
        }
    }

    private static function name(PDO $pdo, string $name, int $except = 0): string
    {
        $name = trim($name);
        $length = preg_match_all('/./us', $name);
        if ($name === '' || $length === false || $length > 100 || preg_match('/[\x00-\x1f\x7f]/', $name)) {
            throw new DomainException('Enter a display name of 1–100 characters, without line breaks or control characters.');
        }
        $stmt = $pdo->prepare('SELECT id FROM users WHERE display_name = ? COLLATE NOCASE AND id <> ?');
        $stmt->execute([$name, $except]);
        if ($stmt->fetchColumn()) throw new DomainException('That name already belongs to a member, possibly an inactive one. Edit or reactivate their existing account.');
        return $name;
    }

    public static function version(array $member): string
    {
        return hash('sha256', json_encode([(int)$member['id'], $member['display_name'], $member['role'], (int)$member['is_active']], JSON_THROW_ON_ERROR));
    }

    public static function create(PDO $pdo, int $actor, string $name): int
    {
        return self::write($pdo, $actor, static function () use ($pdo, $name): int {
            $name = self::name($pdo, $name);
            $stmt = $pdo->prepare("INSERT INTO users(display_name,role,is_active) VALUES(?,'member',1)");
            $stmt->execute([$name]);
            return (int)$pdo->lastInsertId();
        });
    }

    public static function update(PDO $pdo, int $actor, int $target, string $name, string $role, bool $active, string $version): void
    {
        self::write($pdo, $actor, static function () use ($pdo, $target, $name, $role, $active, $version): void {
            $stmt = $pdo->prepare('SELECT id,display_name,role,is_active FROM users WHERE id=?');
            $stmt->execute([$target]); $member = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$member) throw new DomainException('Member not found.');
            if (!hash_equals(self::version($member), $version)) throw new DomainException('This member changed since the page loaded. Reload before saving.');
            if (!in_array($role, ['member','organiser'], true)) throw new DomainException('Choose Member or Organiser.');
            $name = self::name($pdo, $name, $target);
            if ($member['role'] === 'organiser' && (int)$member['is_active'] === 1 && (!$active || $role !== 'organiser')) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role='organiser' AND is_active=1 AND id<>?");
                $stmt->execute([$target]);
                if ((int)$stmt->fetchColumn() === 0) throw new DomainException('Keep at least one active organiser. Make another active member an organiser first.');
            }
            $stmt = $pdo->prepare('UPDATE users SET display_name=?,role=?,is_active=? WHERE id=?');
            $stmt->execute([$name,$role,$active ? 1 : 0,$target]);
            if (!$active) {
                // Reactivation must not restore old device credentials or unused invitations.
                foreach (['auth_sessions','login_links'] as $table) {
                    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE user_id=?"); $stmt->execute([$target]);
                }
            }
        });
    }
}
