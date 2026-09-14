<?php
declare(strict_types=1);
namespace LGFC;
use PDO;
use InvalidArgumentException;
use DomainException;

final class Elections
{
    public static function migrate(PDO $pdo): void
    {
        Removals::migrate($pdo);
        $pdo->exec('CREATE TABLE IF NOT EXISTS election_results (
            election_id INTEGER PRIMARY KEY REFERENCES elections(id),
            result_json TEXT NOT NULL,
            ballot_revision_ids_json TEXT NOT NULL,
            frozen_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        // Older closed elections predate stored results: capture them once on upgrade.
        self::write($pdo, static function () use ($pdo): void {
            $ids = $pdo->query("SELECT id FROM elections WHERE status = 'closed'
                AND id NOT IN (SELECT election_id FROM election_results)")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $id) self::freeze($pdo, (int) $id);
        });
    }

    /** Serialize all lifecycle writes, so close and submit cannot pass each other. */
    private static function write(PDO $pdo, callable $operation): mixed
    {
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $result = $operation();
            $pdo->exec('COMMIT');
            return $result;
        } catch (\Throwable $error) {
            $pdo->exec('ROLLBACK');
            throw $error;
        }
    }

    public static function candidateIds(PDO $pdo, int $id): array
    {
        $stmt = $pdo->prepare('SELECT em.movie_id FROM election_movies em WHERE em.election_id = ? AND NOT EXISTS (SELECT 1 FROM election_removals er WHERE er.election_id=em.election_id AND er.movie_id=em.movie_id) ORDER BY em.movie_id');
        $stmt->execute([$id]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function ballots(PDO $pdo, int $id): array
    {
        $stmt = $pdo->prepare('SELECT br.user_id, c.movie_id FROM ballot_revisions br
            JOIN (SELECT user_id, MAX(id) AS latest_id FROM ballot_revisions WHERE election_id = ? GROUP BY user_id) latest ON latest.latest_id = br.id
            JOIN ballot_revision_choices c ON c.ballot_revision_id = br.id ORDER BY br.user_id, c.rank');
        $stmt->execute([$id]);
        $ballots = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $ballots[(int) $row['user_id']][] = (int) $row['movie_id'];
        return $ballots;
    }

    public static function currentResult(PDO $pdo, int $id): array
    {
        $ballots = self::ballots($pdo, $id);
        // An election with no submissions has no electoral outcome.
        return $ballots === [] ? ['winner' => null, 'rounds' => []] : Rcv::calculate(array_values($ballots), self::candidateIds($pdo, $id));
    }

    private static function freeze(PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare('SELECT MAX(id) FROM ballot_revisions WHERE election_id = ? GROUP BY user_id ORDER BY user_id');
        $stmt->execute([$id]);
        $revisions = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $insert = $pdo->prepare('INSERT INTO election_results (election_id, result_json, ballot_revision_ids_json) VALUES (?, ?, ?)');
        $insert->execute([$id, json_encode(self::currentResult($pdo, $id), JSON_THROW_ON_ERROR), json_encode($revisions, JSON_THROW_ON_ERROR)]);
    }

    public static function finalResult(PDO $pdo, int $id): array
    {
        $stmt = $pdo->prepare('SELECT result_json FROM election_results WHERE election_id = ?');
        $stmt->execute([$id]);
        $json = $stmt->fetchColumn();
        if ($json === false) throw new \RuntimeException('Stored result missing. Run db/migrate.php.');
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    public static function open(PDO $pdo, string $name): int
    {
        $name = trim($name);
        if ($name === '' || strlen($name) > 200) throw new InvalidArgumentException('Enter an election name of at most 200 characters.');
        return self::write($pdo, static function () use ($pdo, $name): int {
            if ($pdo->query("SELECT id FROM elections WHERE status = 'open' LIMIT 1")->fetchColumn() !== false) {
                throw new DomainException('Close the current election before opening another.');
            }
            $candidates = $pdo->query("SELECT id FROM movies WHERE status = 'active' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
            if ($candidates === []) throw new DomainException('Nominate at least one active film before opening an election.');
            $stmt = $pdo->prepare("INSERT INTO elections (name, status) VALUES (?, 'open')");
            $stmt->execute([$name]);
            $id = (int) $pdo->lastInsertId();
            $insert = $pdo->prepare('INSERT INTO election_movies (election_id, movie_id) VALUES (?, ?)');
            foreach ($candidates as $candidate) $insert->execute([$id, $candidate]);
            return $id;
        });
    }

    public static function close(PDO $pdo, int $id): void
    {
        self::write($pdo, static function () use ($pdo, $id): void {
            $stmt = $pdo->prepare('SELECT status FROM elections WHERE id = ?');
            $stmt->execute([$id]);
            $status = $stmt->fetchColumn();
            if ($status === false) throw new InvalidArgumentException('Election not found.');
            if ($status === 'closed') return; // Double-click/retry keeps the original snapshot.
            self::freeze($pdo, $id);
            $stmt = $pdo->prepare("UPDATE elections SET status = 'closed', closed_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$id]);
        });
    }

    public static function submit(PDO $pdo, int $id, int $userId, array $ranking): int
    {
        if (!$ranking || array_filter($ranking, static fn($value): bool => !is_int($value) || $value < 1)) {
            throw new InvalidArgumentException('Rank every eligible film using valid movie IDs.');
        }
        return self::write($pdo, static function () use ($pdo, $id, $userId, $ranking): int {
            $stmt = $pdo->prepare('SELECT status FROM elections WHERE id = ?');
            $stmt->execute([$id]);
            $status = $stmt->fetchColumn();
            if ($status === false) throw new InvalidArgumentException('Election not found.');
            if ($status !== 'open') throw new DomainException('Voting has closed. Your draft was not saved. Reload to view the final result.');
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND is_active = 1');
            $stmt->execute([$userId]);
            if ($stmt->fetchColumn() === false) throw new InvalidArgumentException('Choose an active voter.');
            $submitted = $ranking;
            sort($submitted, SORT_NUMERIC);
            $eligible = self::candidateIds($pdo, $id);
            if ($submitted !== $eligible) {
                $stmt = $pdo->prepare('SELECT movie_id FROM election_movies WHERE election_id=?');
                $stmt->execute([$id]);
                $original = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
                if (count(array_unique($submitted)) === count($submitted) && !array_diff($submitted, $original)
                    && !array_diff($eligible, $submitted) && array_diff($submitted, $eligible)) {
                    throw new CandidatesChanged($eligible);
                }
                throw new InvalidArgumentException('Ballot must rank every eligible movie exactly once.');
            }
            $stmt = $pdo->prepare('INSERT INTO ballot_revisions (election_id, user_id) VALUES (?, ?)');
            $stmt->execute([$id, $userId]);
            $revision = (int) $pdo->lastInsertId();
            $insert = $pdo->prepare('INSERT INTO ballot_revision_choices (ballot_revision_id, movie_id, rank) VALUES (?, ?, ?)');
            foreach (array_values($ranking) as $index => $movie) $insert->execute([$revision, $movie, $index + 1]);
            return $revision;
        });
    }
}
