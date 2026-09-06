<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use LGFC\Database;
use LGFC\Rcv;
use LGFC\Movies;
header('Cache-Control: no-store');

$pdo = Database::connect();
$election = $pdo->query("SELECT * FROM elections WHERE status = 'open' ORDER BY id DESC LIMIT 1")->fetch();

if (!$election) {
    http_response_code(500);
    exit('No open election.');
}

$users = $pdo->query("SELECT id, display_name FROM users WHERE is_active = 1 ORDER BY display_name")->fetchAll();

$stmt = $pdo->prepare(
    'SELECT m.*
     FROM election_movies em
     JOIN movies m ON m.id = em.movie_id
     WHERE em.election_id = :election_id
     ORDER BY m.id'
);
$stmt->execute(['election_id' => $election['id']]);
$movies = array_map([Movies::class, 'publicView'], $stmt->fetchAll());
usort($movies, static fn(array $a, array $b): int => strcasecmp($a['title'], $b['title']));

$currentBallotsSql = <<<'SQL'
SELECT br.user_id, br.id AS revision_id, brc.movie_id, brc.rank
FROM ballot_revisions br
JOIN (
    SELECT election_id, user_id, MAX(id) AS latest_id
    FROM ballot_revisions
    WHERE election_id = :election_id
    GROUP BY election_id, user_id
) latest ON latest.latest_id = br.id
JOIN ballot_revision_choices brc ON brc.ballot_revision_id = br.id
ORDER BY br.user_id, brc.rank
SQL;
$stmt = $pdo->prepare($currentBallotsSql);
$stmt->execute(['election_id' => $election['id']]);
$rows = $stmt->fetchAll();

$committedBallots = [];
foreach ($rows as $row) {
    $committedBallots[(int) $row['user_id']][] = (int) $row['movie_id'];
}

$candidateIds = array_map(static fn (array $m): int => (int) $m['id'], $movies);
$authoritative = Rcv::calculate(array_values($committedBallots), $candidateIds);

$movieNames = [];
foreach ($movies as $movie) {
    $movieNames[(int) $movie['id']] = $movie['title'];
}

$bootstrap = [
    'election' => ['id' => (int) $election['id'], 'name' => $election['name']],
    'users' => $users,
    'movies' => $movies,
    'committedBallots' => $committedBallots,
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Little Gay Film Club™</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<main>
    <header>
        <h1>Little Gay Film Club™</h1>
        <a href="movies.php">Nominate or reveal a film</a>
        <p><?= htmlspecialchars($election['name']) ?></p>
    </header>

    <div class="voting-layout">
    <section>
        <h2>Cast a ballot</h2>
        <label for="user-select">Vote as</label>
        <select id="user-select">
            <option value="">Choose a voter…</option>
            <?php foreach ($users as $user): ?>
                <option value="<?= (int) $user['id'] ?>"><?= htmlspecialchars($user['display_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <p>Drag films into preference order. This draft is speculative until you submit it.</p>
        <ol id="ranking-list">
            <?php foreach ($movies as $movie): ?>
                <li draggable="true" data-movie-id="<?= (int) $movie['id'] ?>">
                    <span class="handle" aria-hidden="true">☰</span>
                    <div class="movie-art" aria-hidden="true">
                        <?php if ($movie['image_url']): ?><img src="<?= htmlspecialchars($movie['image_url'], ENT_QUOTES) ?>" alt="" draggable="false" loading="lazy" referrerpolicy="no-referrer"><?php else: ?><span><?= $movie['is_mystery'] ? '?' : '▶' ?></span><?php endif; ?>
                    </div>
                    <div class="movie-card-copy">
                        <strong><?= htmlspecialchars($movie['title']) ?></strong>
                        <small><?= $movie['is_mystery'] ? 'Mystery film · judge it by the pitch' : ($movie['release_year'] ?? 'Year not added') ?></small>
                        <button type="button" class="movie-details-button" data-details="<?= $movie['id'] ?>" aria-haspopup="dialog">Details<span class="sr-only">: <?= htmlspecialchars($movie['title']) ?></span></button>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
        <button id="submit-ballot" type="button">Submit ballot</button>
        <p id="message" role="status"></p>
    </section>

    <section>
        <h2>Draft round preview</h2>
        <p>Uses the committed ballots loaded with this page, replacing your saved vote with this draft. Reordering does not save. Reload deliberately to refresh other voters’ ballots.</p>
        <div id="speculative-result">Choose a voter to start meddling with democracy.</div>
    </section>

    </div>
    <section>
        <h2>Committed result at page load</h2>
        <p>This authoritative result stays unchanged until you reload, including after submitting.</p>
        <?php if ($authoritative['winner'] === null): ?>
            <p>No winner yet.</p>
        <?php else: ?>
            <p class="winner">Winner: <strong><?= htmlspecialchars($movieNames[$authoritative['winner']] ?? 'Unknown') ?></strong></p>
        <?php endif; ?>

        <?php foreach ($authoritative['rounds'] as $index => $round): ?>
            <article class="round">
                <h3>Round <?= $index + 1 ?></h3>
                <ul>
                    <?php foreach ($round['counts'] as $movieId => $count): ?>
                        <li><?= htmlspecialchars($movieNames[(int) $movieId] ?? ('Movie ' . $movieId)) ?>: <?= (int) $count ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($round['eliminated'] !== null): ?>
                    <p>Eliminated: <?= htmlspecialchars($movieNames[$round['eliminated']] ?? 'Unknown') ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
</main>
<dialog id="movie-dialog" aria-labelledby="movie-dialog-title">
    <button type="button" id="close-movie-dialog" aria-label="Close film details">Close ×</button>
    <div id="movie-dialog-art" class="movie-art detail-art" aria-hidden="true"></div>
    <h2 id="movie-dialog-title"></h2>
    <p id="movie-dialog-meta"></p>
    <h3>The pitch</h3><p id="movie-dialog-pitch"></p>
    <div id="movie-dialog-synopsis"><h3>Synopsis</h3><p></p></div>
    <p id="movie-dialog-mystery">The identity stays hidden until the nominator deliberately reveals it, even if it wins.</p>
</dialog>
<script>window.LGFC_BOOTSTRAP = <?= json_encode($bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;</script>
<script type="module" src="../frontend/dist/app.js"></script>
</body>
</html>
