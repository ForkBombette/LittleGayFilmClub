<?php
declare(strict_types=1);
namespace LGFC;
use PDO;
use InvalidArgumentException;

final class Removals
{
    public static function migrate(PDO $pdo): void
    {
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/db/removals.sql'));
    }

    public static function threshold(PDO $pdo): int
    {
        return intdiv((int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_active=1')->fetchColumn(), 2) + 1;
    }

    /** Creating a request also records the proposer's support. One decision per film. */
    public static function vote(PDO $pdo, int $movieId, int $userId, bool $support, string $reason = ''): void
    {
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id=? AND is_active=1');
            $stmt->execute([$userId]);
            if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Sign in as an active member.');
            $stmt = $pdo->prepare('SELECT * FROM removal_requests WHERE movie_id=?');
            $stmt->execute([$movieId]); $request = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($request && $request['passed_at'] !== null) {
                throw new InvalidArgumentException('This removal has already passed.');
            }
            if (!$request) {
                $stmt = $pdo->prepare("SELECT id FROM movies WHERE id=? AND (status='active' OR (status='watched' AND EXISTS (
                    SELECT 1 FROM election_movies em JOIN elections e ON e.id=em.election_id WHERE em.movie_id=movies.id AND e.status='open'
                )))");
                $stmt->execute([$movieId]);
                if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Choose a film in the active pool or current election.');
                $reason = trim($reason);
                if (!$support || $reason === '' || strlen($reason)>2000) throw new InvalidArgumentException('Give a reason of at most 2000 characters to propose removal.');
                $stmt = $pdo->prepare('INSERT INTO removal_requests(movie_id,proposed_by,reason) VALUES(?,?,?)');
                $stmt->execute([$movieId,$userId,$reason]);
            }
            $stmt = $pdo->prepare('INSERT INTO removal_votes(movie_id,user_id,support) VALUES(?,?,?)
                ON CONFLICT(movie_id,user_id) DO UPDATE SET support=excluded.support');
            $stmt->execute([$movieId,$userId,$support ? 1 : 0]);
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM removal_votes v JOIN users u ON u.id=v.user_id WHERE v.movie_id=? AND v.support=1 AND u.is_active=1');
            $stmt->execute([$movieId]); $yes = (int) $stmt->fetchColumn();
            $needed = self::threshold($pdo);
            if ($yes >= $needed) {
                $stmt = $pdo->prepare('UPDATE removal_requests SET passed_at=CURRENT_TIMESTAMP, support_at_pass=?, threshold_at_pass=? WHERE movie_id=?');
                $stmt->execute([$yes,$needed,$movieId]);
                $stmt = $pdo->prepare("UPDATE movies SET status='removed' WHERE id=?"); $stmt->execute([$movieId]);
                // Preserve the original snapshot and every ballot. Exclusions belong to elections.
                $stmt = $pdo->prepare("INSERT INTO election_removals(election_id,movie_id)
                    SELECT em.election_id,em.movie_id FROM election_movies em JOIN elections e ON e.id=em.election_id
                    WHERE em.movie_id=? AND e.status='open'");
                $stmt->execute([$movieId]);
            }
            $pdo->exec('COMMIT');
        } catch (\Throwable $error) {
            $pdo->exec('ROLLBACK'); throw $error;
        }
    }

    public static function listing(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare('SELECT r.*, m.*, r.movie_id AS request_movie_id,
            (SELECT COUNT(*) FROM removal_votes v JOIN users u ON u.id=v.user_id WHERE v.movie_id=r.movie_id AND v.support=1 AND u.is_active=1) AS support_count,
            (SELECT support FROM removal_votes WHERE movie_id=r.movie_id AND user_id=?) AS my_vote
            FROM removal_requests r JOIN movies m ON m.id=r.movie_id ORDER BY r.passed_at IS NOT NULL, r.movie_id DESC');
        $stmt->execute([$userId]);
        return array_map(static fn(array $row): array => [
            'movie' => Movies::publicView($row), 'reason' => $row['reason'], 'passed_at' => $row['passed_at'],
            'support' => (int) $row['support_count'], 'threshold_at_pass' => $row['threshold_at_pass'],
            'support_at_pass' => $row['support_at_pass'], 'my_vote' => $row['my_vote'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
