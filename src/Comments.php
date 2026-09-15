<?php
declare(strict_types=1);
namespace LGFC;
use PDO;
use DomainException;
use InvalidArgumentException;

final class Comments
{
    public static function migrate(PDO $pdo): void
    {
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/db/comments.sql'));
    }

    public static function listing(PDO $pdo, int $movieId): array
    {
        $stmt = $pdo->prepare('SELECT c.user_id, u.display_name, c.body, c.version, c.created_at, c.updated_at
            FROM movie_comments c JOIN users u ON u.id=c.user_id WHERE c.movie_id=? ORDER BY c.created_at, c.user_id');
        $stmt->execute([$movieId]);
        return array_map(static function (array $row): array { $row['user_id'] = (int)$row['user_id']; return $row; }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Empty version means create, otherwise update/delete exactly the comment the member loaded. */
    public static function write(PDO $pdo, int $movieId, int $actor, string $body, string $version, bool $delete = false): void
    {
        $body = trim($body);
        if (!$delete && ($body === '' || !preg_match('//u', $body) || preg_match_all('/./us', $body) > 2000)) {
            throw new InvalidArgumentException('Write a comment of at most 2,000 characters.');
        }
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id=? AND is_active=1');
            $stmt->execute([$actor]);
            if (!$stmt->fetchColumn()) throw new InvalidArgumentException('An active member is required.');
            $stmt = $pdo->prepare('SELECT id FROM movies WHERE id=?');
            $stmt->execute([$movieId]);
            if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Film not found.');
            $stmt = $pdo->prepare('SELECT version FROM movie_comments WHERE movie_id=? AND user_id=?');
            $stmt->execute([$movieId,$actor]);
            $current = $stmt->fetchColumn();
            if (($current === false ? '' : $current) !== $version || ($delete && $current === false)) {
                throw new DomainException('Your comment changed in another tab. Refresh comments to review the latest version; your draft will stay here.');
            }
            if ($delete) {
                $stmt = $pdo->prepare('DELETE FROM movie_comments WHERE movie_id=? AND user_id=?');
                $stmt->execute([$movieId,$actor]);
            } elseif ($current === false) {
                $stmt = $pdo->prepare('INSERT INTO movie_comments(movie_id,user_id,body,version) VALUES(?,?,?,?)');
                $stmt->execute([$movieId,$actor,$body,bin2hex(random_bytes(16))]);
            } else {
                $stmt = $pdo->prepare('UPDATE movie_comments SET body=?,version=?,updated_at=CURRENT_TIMESTAMP WHERE movie_id=? AND user_id=?');
                $stmt->execute([$body,bin2hex(random_bytes(16)),$movieId,$actor]);
            }
            $pdo->exec('COMMIT');
        } catch (\Throwable $error) { $pdo->exec('ROLLBACK'); throw $error; }
    }
}
