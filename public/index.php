<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use LGFC\Database;
use LGFC\Rcv;

$pdo = Database::connect();
$election = $pdo->query("SELECT * FROM elections WHERE status = 'open' ORDER BY id DESC LIMIT 1")->fetch();

if (!$election) {
    http_response_code(500);
    exit('No open election.');
}

$users = $pdo->query("SELECT id, display_name FROM users WHERE is_active = 1 ORDER BY display_name")->fetchAll();

$stmt = $pdo->prepare(
    'SELECT m.id, m.title, m.release_year
     FROM election_movies em
     JOIN movies m ON m.id = em.movie_id
     WHERE em.election_id = :election_id
     ORDER BY m.title'
);
$stmt->execute(['election_id' => $election['id']]);
$movies = $stmt->fetchAll();

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
        <p><?= htmlspecialchars($election['name']) ?></p>
    </header>

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
                    <span class="handle">☰</span>
                    <strong><?= htmlspecialchars($movie['title']) ?></strong>
                    <?php if ($movie['release_year']): ?>
                        <small>(<?= (int) $movie['release_year'] ?>)</small>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
        <button id="submit-ballot" type="button">Submit ballot</button>
        <p id="message" role="status"></p>
    </section>

    <section>
        <h2>Speculative result</h2>
        <div id="speculative-result">Choose a voter to start meddling with democracy.</div>
    </section>

    <section>
        <h2>Current committed result</h2>
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
<script>window.LGFC_BOOTSTRAP = <?= json_encode($bootstrap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
<script type="module" src="../frontend/dist/app.js"></script>
</body>
</html>
