<?php
declare(strict_types=1);
namespace LGFC;
use PDO;
use InvalidArgumentException;

final class Movies
{
    public static function publicView(array $movie): array
    {
        $hidden = $movie['mystery_alias'] !== null && $movie['revealed_at'] === null;
        // Whitelist only: never serialize a raw database record to the browser.
        return [
            'id' => (int) $movie['id'],
            'title' => $hidden ? $movie['mystery_alias'] : $movie['title'],
            'release_year' => $hidden || $movie['release_year'] === null ? null : (int) $movie['release_year'],
            'image_url' => $hidden ? null : self::safeImage($movie['image_url']),
            'summary' => $hidden ? null : $movie['summary'],
            'nomination_pitch' => $movie['nomination_pitch'],
            'nominator_id' => $movie['nominator_id'] === null ? null : (int) $movie['nominator_id'],
            'is_mystery' => $hidden,
            'revealed' => $movie['mystery_alias'] !== null && !$hidden,
        ];
    }

    /** Browse-only films never alter the frozen ballot candidate list. */
    public static function browseLists(PDO $pdo, int $electionId): array
    {
        $stmt = $pdo->prepare("SELECT m.* FROM movies m
            WHERE m.status IN ('active', 'watched')
                AND (m.status='watched' OR NOT EXISTS (SELECT 1 FROM
                    (SELECT movie_id FROM movie_night_announcements ORDER BY id DESC LIMIT 1) a WHERE a.movie_id=m.id))
                AND NOT EXISTS (
                SELECT 1 FROM election_movies em WHERE em.movie_id = m.id AND em.election_id = ?
            )");
        $stmt->execute([$electionId]);
        $lists = ['ineligible' => [], 'watched' => []];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $movie) {
            $lists[$movie['status'] === 'watched' ? 'watched' : 'ineligible'][] = self::publicView($movie);
        }
        foreach ($lists as &$list) {
            usort($list, static fn(array $a, array $b): int => strcasecmp($a['title'], $b['title']) ?: $a['id'] <=> $b['id']);
        }
        return $lists;
    }

    public static function safeImage(?string $url): ?string
    {
        if (!$url) return null;
        // Support local public artwork and HTTPS posters, never script/data URLs.
        if (preg_match('~^images/[a-zA-Z0-9_./-]+$~D', $url) && !str_contains($url, '..')) return $url;
        if (filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https'
            && !parse_url($url, PHP_URL_USER) && !parse_url($url, PHP_URL_PASS)) return $url;
        return null;
    }

    private static function activeUser(PDO $pdo, int $id): bool
    {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }

    public static function nominate(PDO $pdo, int $userId, array $input): int
    {
        if (!self::activeUser($pdo, $userId)) throw new InvalidArgumentException('Choose an active nominator.');
        $values = self::details($input);
        $stmt = $pdo->prepare('INSERT INTO movies (title, release_year, image_url, summary, nominator_id, nomination_pitch, mystery_alias) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$values['title'], $values['year'], $values['image'], $values['summary'], $userId, $values['pitch'], $values['alias']]);
        // Nomination never changes an already-open election's eligibility snapshot.
        return (int) $pdo->lastInsertId();
    }

    private static function details(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $pitch = trim((string) ($input['pitch'] ?? ''));
        $mystery = ($input['mystery'] ?? '') === '1';
        $alias = trim((string) ($input['alias'] ?? ''));
        $summary = trim((string) ($input['summary'] ?? ''));
        $image = trim((string) ($input['image_url'] ?? ''));
        $year = trim((string) ($input['year'] ?? ''));
        if ($title === '' || strlen($title) > 300) throw new InvalidArgumentException('Enter a title of at most 300 characters.');
        if ($mystery && ($alias === '' || $pitch === '')) throw new InvalidArgumentException('A mystery needs a public alias and a pitch.');
        if (strlen($alias) > 200 || strlen($pitch) > 4000 || strlen($summary) > 8000) throw new InvalidArgumentException('The alias, pitch or synopsis is too long.');
        if ($year !== '' && (!ctype_digit($year) || (int) $year < 1888 || (int) $year > 2100)) throw new InvalidArgumentException('Enter a valid release year.');
        if ($image !== '' && self::safeImage($image) === null) throw new InvalidArgumentException('Use an HTTPS poster URL or a path under images/.');
        return ['title' => $title, 'year' => $year === '' ? null : (int) $year,
            'image' => $image ?: null, 'summary' => $summary ?: null,
            'pitch' => $pitch ?: null, 'alias' => $mystery ? $alias : null];
    }

    /** Raw private details may be returned only to the active nominator. */
    public static function editable(PDO $pdo, int $movieId, int $userId): array
    {
        if (!self::activeUser($pdo, $userId)) throw new InvalidArgumentException('Sign in as an active member.');
        $stmt = $pdo->prepare('SELECT * FROM movies WHERE id = ?');
        $stmt->execute([$movieId]);
        $movie = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$movie || (int) $movie['nominator_id'] !== $userId) {
            throw new InvalidArgumentException('Only this film’s nominator can edit it.');
        }
        return $movie;
    }

    public static function editVersion(array $movie): string
    {
        return hash('sha256', json_encode($movie, JSON_THROW_ON_ERROR));
    }

    public static function edit(PDO $pdo, int $movieId, int $userId, array $input): void
    {
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $movie = self::editable($pdo, $movieId, $userId);
            if (!is_string($input['version'] ?? null) || !hash_equals(self::editVersion($movie), $input['version'])) {
                throw new InvalidArgumentException('This film changed since you opened the editor. Reload it before saving.');
            }
            // Mystery state and ownership are immutable here; only reveal() can disclose it.
            $input['mystery'] = $movie['mystery_alias'] !== null ? '1' : '';
            $values = self::details($input);
            $stmt = $pdo->prepare('UPDATE movies SET title=?, release_year=?, image_url=?, summary=?, nomination_pitch=?, mystery_alias=? WHERE id=?');
            $stmt->execute([$values['title'], $values['year'], $values['image'], $values['summary'], $values['pitch'], $values['alias'], $movieId]);
            $pdo->exec('COMMIT');
        } catch (\Throwable $error) {
            $pdo->exec('ROLLBACK');
            throw $error;
        }
    }

    public static function reveal(PDO $pdo, int $movieId, int $userId): void
    {
        if (!self::activeUser($pdo, $userId)) throw new InvalidArgumentException('Choose an active nominator.');
        $stmt = $pdo->prepare('SELECT nominator_id, mystery_alias FROM movies WHERE id = ?');
        $stmt->execute([$movieId]);
        $movie = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$movie || (int) $movie['nominator_id'] !== $userId || $movie['mystery_alias'] === null) {
            throw new InvalidArgumentException('Only this film’s nominator can reveal it.');
        }
        $stmt = $pdo->prepare('UPDATE movies SET revealed_at = COALESCE(revealed_at, CURRENT_TIMESTAMP) WHERE id = ?');
        $stmt->execute([$movieId]);
    }
}
