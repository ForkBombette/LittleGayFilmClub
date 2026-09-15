<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use LGFC\Database;
use LGFC\Auth;
use LGFC\Rcv;
use LGFC\Movies;
use LGFC\Elections;
use LGFC\Watched;
LGFC\Web::start();

$pdo = Database::connect();
$viewer = Auth::requireUser($pdo);
$isOrganiser = $viewer['role'] === 'organiser';
if (isset($_GET['electionId'])) {
    $id = filter_var($_GET['electionId'], FILTER_VALIDATE_INT);
    $stmt = $pdo->prepare('SELECT * FROM elections WHERE id = ?');
    $stmt->execute([$id ?: 0]);
    $election = $stmt->fetch();
    if (!$election) { http_response_code(404); exit('Election not found.'); }
} else {
    $election = $pdo->query("SELECT * FROM elections ORDER BY CASE WHEN status = 'open' THEN 0 ELSE 1 END, id DESC LIMIT 1")->fetch();
}
$electionId = $election ? (int) $election['id'] : 0;
$isOpen = $election && $election['status'] === 'open';



$stmt = $pdo->prepare(
    'SELECT m.*
     FROM election_movies em
     JOIN movies m ON m.id = em.movie_id
     WHERE em.election_id = :election_id AND NOT EXISTS (SELECT 1 FROM election_removals er WHERE er.election_id=em.election_id AND er.movie_id=em.movie_id)
     ORDER BY m.id'
);
$stmt->execute(['election_id' => $electionId]);
$movies = array_map([Movies::class, 'publicView'], $stmt->fetchAll());
usort($movies, static fn(array $a, array $b): int => strcasecmp($a['title'], $b['title']));

$browseLists = Movies::browseLists($pdo, $electionId);

$committedBallots = Elections::ballots($pdo, $electionId);
$authoritative = !$election ? ['winner' => null, 'rounds' => []]
    : ($isOpen ? Elections::currentResult($pdo, $electionId) : Elections::finalResult($pdo, $electionId));

$watchedForElection = array_values(array_filter(Watched::history($pdo), static fn(array $entry): bool => $entry['election_id'] === $electionId));

$movieNames = [];
foreach ($movies as $movie) {
    $movieNames[(int) $movie['id']] = $movie['title'];
}

$bootstrap = [
    'election' => $election ? ['id' => $electionId, 'name' => $election['name'], 'status' => $election['status']] : null,
    'viewer' => $viewer,
    'csrf' => $_SESSION['csrf'],
    'movies' => $movies,
    'browseMovies' => array_merge($browseLists['ineligible'], $browseLists['watched']),
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
<body><?php LGFC\Navigation::render($viewer, 'index.php'); ?>
<main id="main-content" tabindex="-1">
    <header>
        <p class="eyebrow">The voting chamber</p><h1><?= $isOpen ? 'Tonight’s vote' : ($election ? 'Election result' : 'Between movie nights') ?></h1>

        <p><?= $election ? htmlspecialchars($election['name']) : 'Between movie nights' ?></p>
        <?php if (!$isOpen): ?><p><?= $election ? 'Voting is closed. This result is frozen.' : 'No election has opened yet. Nominate films, then open an election when ready.' ?></p><?php endif; ?>
    </header>

    <div class="<?= $isOpen ? 'voting-layout' : 'election-readonly' ?>">
    <section>
        <h2><?= $isOpen ? 'Cast a ballot' : ($election ? 'Films in this election' : 'Film pool') ?></h2>
        <?php if ($isOpen): ?>
        <p>Your ballot, <?= htmlspecialchars($viewer['display_name']) ?>.</p>

        <p>Drag films into preference order. This draft is speculative until you submit it.</p>
        <?php endif; ?>
        <?php if ($election): ?>
        <?php $removed = $pdo->prepare('SELECT m.* FROM election_removals er JOIN movies m ON m.id=er.movie_id WHERE er.election_id=?'); $removed->execute([$electionId]); $removedFilms=array_map([Movies::class,'publicView'],$removed->fetchAll()); ?>
        <?php if ($removedFilms): ?><p>Eliminated by removal vote: <?= htmlspecialchars(implode(', ',array_column($removedFilms,'title'))) ?>. Ballots skip these films. <a href="removals.php">View decisions</a></p><?php endif; ?>
        <?php endif; ?>
        <ol id="ranking-list">
            <?php foreach ($movies as $movie): ?>
                <li draggable="<?= $isOpen ? 'true' : 'false' ?>" data-movie-id="<?= (int) $movie['id'] ?>">
                    <?php if ($isOpen): ?><span class="handle" aria-hidden="true">☰</span><?php endif; ?>
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
        <?php if ($isOpen): ?><button id="submit-ballot" type="button">Submit ballot</button>
        <p id="message" role="status"></p><?php endif; ?>
        <div class="film-library" aria-label="More films">
            <?php foreach (['ineligible' => 'Not in this election', 'watched' => 'Watched films'] as $key => $label): ?>
            <details class="film-shelf">
                <summary><?= htmlspecialchars($label) ?> <span>(<?= count($browseLists[$key]) ?>)</span></summary>
                <p><?= $key === 'ineligible' ? 'These nominations are outside this election’s frozen list. Browse their details here; they cannot be ranked in this vote.' : 'Previously watched films, with their details kept for revisiting.' ?></p>
                <?php if (!$browseLists[$key]): ?>
                    <p class="shelf-empty"><?= $key === 'ineligible' ? 'No films waiting outside this election.' : 'No watched films yet.' ?></p>
                <?php else: ?>
                <ul class="browse-movies">
                    <?php foreach ($browseLists[$key] as $movie): ?>
                    <li>
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
                </ul>
                <?php endif; ?>
            </details>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($isOpen): ?>
    <section>
        <h2>Draft round preview</h2>
        <p>Uses the committed ballots loaded with this page, replacing your saved vote with this draft. Reordering does not save. Reload deliberately to refresh other voters’ ballots.</p>
        <div id="speculative-result">Calculating your draft…</div>
    </section>

    <?php endif; ?>
    </div>
    <?php if ($election): ?>
    <section>
        <h2><?= $isOpen ? 'Committed result at page load' : 'Final result' ?></h2>
        <p><a href="ballot-history.php?electionId=<?= $electionId ?>">Explore ballot history</a></p>
        <p><?= $isOpen ? 'This authoritative result stays unchanged until you reload, including after submitting.' : 'Voting closed at ' . htmlspecialchars($election['closed_at'] ?? 'an earlier date') . ' UTC. The totals below were stored when this election closed.' ?></p>
        <?php if ($authoritative['winner'] === null): ?>
            <p><?= $isOpen ? 'No winner yet.' : 'No winner — no ballots or no remaining candidates.' ?></p>
        <?php else: ?>
            <p class="winner">Winner: <strong><?= htmlspecialchars($movieNames[$authoritative['winner']] ?? 'Unknown') ?></strong></p>
        <?php endif; ?>

        <?php if (!$isOpen): ?>
        <h3>What we actually watched</h3>
        <?php if (!$watchedForElection): ?><p>No watched film recorded for this election yet.</p><?php endif; ?>
        <ul><?php foreach ($watchedForElection as $entry): ?><li><?= htmlspecialchars($entry['movie']['title']) ?> · <?= $entry['watched_on'] ? htmlspecialchars($entry['watched_on']) : 'Date unknown' ?></li><?php endforeach; ?></ul>
        <?php if ($isOrganiser): ?><p><a href="watched.php?electionId=<?= $electionId ?><?= $authoritative['winner'] !== null ? '&amp;movieId=' . (int) $authoritative['winner'] : '' ?>">Record what we actually watched</a> — choose the winner or a different film.</p><?php endif; ?><?php endif; ?>
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
<?php endif; ?>
</main>
<?php require dirname(__DIR__) . '/src/movie-dialog.php'; ?>
<script>window.LGFC_BOOTSTRAP = <?= json_encode($bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;</script>
<script type="module" src="../frontend/dist/app.js"></script>
</body>
</html>
