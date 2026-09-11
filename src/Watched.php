<?php
declare(strict_types=1);
namespace LGFC;
use PDO;
use InvalidArgumentException;

final class Watched
{
    public static function migrate(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS movie_watches (
            movie_id INTEGER PRIMARY KEY REFERENCES movies(id),
            watched_on TEXT NULL,
            election_id INTEGER NULL REFERENCES elections(id),
            recorded_by INTEGER NOT NULL REFERENCES users(id),
            recorded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
    }

    /** Record/correct one watched entry per film; never manufacture a ballot. */
    public static function record(PDO $pdo, int $userId, ?int $movieId, array $input): int
    {
        $date = trim((string) ($input['watched_on'] ?? ''));
        if ($date !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date || $date > (new \DateTimeImmutable('now', new \DateTimeZone('Europe/London')))->format('Y-m-d')) {
                throw new InvalidArgumentException('Enter a valid watched date in the past or today, or leave it blank if unknown.');
            }
        }
        $election = $input['election_id'] ?? '';
        $electionId = $election === '' ? null : filter_var($election, FILTER_VALIDATE_INT);
        if ($electionId === false || ($electionId !== null && $electionId < 1)) throw new InvalidArgumentException('Choose a valid election, or no election.');
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND is_active = 1');
            $stmt->execute([$userId]);
            if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Choose an active recorder.');
            if ($electionId !== null) {
                $stmt = $pdo->prepare('SELECT id FROM elections WHERE id = ?');
                $stmt->execute([$electionId]);
                if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Election not found.');
            }
            if ($movieId === null) {
                $movieId = Movies::nominate($pdo, $userId, $input);
            } else {
                $stmt = $pdo->prepare('SELECT id FROM movies WHERE id = ?');
                $stmt->execute([$movieId]);
                if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Film not found.');
            }
            $stmt = $pdo->prepare('INSERT INTO movie_watches (movie_id, watched_on, election_id, recorded_by)
                VALUES (?, ?, ?, ?) ON CONFLICT(movie_id) DO UPDATE SET
                watched_on = excluded.watched_on, election_id = excluded.election_id,
                recorded_by = excluded.recorded_by, recorded_at = CURRENT_TIMESTAMP');
            $stmt->execute([$movieId, $date ?: null, $electionId, $userId]);
            $stmt = $pdo->prepare("UPDATE movies SET status = 'watched' WHERE id = ?");
            $stmt->execute([$movieId]);
            $pdo->exec('COMMIT');
            return $movieId;
        } catch (\Throwable $error) {
            $pdo->exec('ROLLBACK');
            throw $error;
        }
    }

    public static function history(PDO $pdo): array
    {
        $rows = $pdo->query("SELECT m.*, w.watched_on, w.election_id, e.name AS election_name
            FROM movies m LEFT JOIN movie_watches w ON w.movie_id = m.id
            LEFT JOIN elections e ON e.id = w.election_id WHERE m.status = 'watched'
            ORDER BY w.watched_on IS NULL, w.watched_on DESC, m.id DESC")->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn(array $row): array => [
            'movie' => Movies::publicView($row),
            'watched_on' => $row['watched_on'],
            'election_id' => $row['election_id'] === null ? null : (int) $row['election_id'],
            'election_name' => $row['election_name'],
        ], $rows);
    }
}
