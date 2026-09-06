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
        $stmt = $pdo->prepare('INSERT INTO movies (title, release_year, image_url, summary, nominator_id, nomination_pitch, mystery_alias) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$title, $year === '' ? null : (int) $year, $image ?: null, $summary ?: null, $userId, $pitch ?: null, $mystery ? $alias : null]);
        // Nomination never changes an already-open election's eligibility snapshot.
        return (int) $pdo->lastInsertId();
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
