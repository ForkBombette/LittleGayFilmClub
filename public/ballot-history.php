<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
use LGFC\{Auth, BallotHistory, Database, Web};
Web::start();
$pdo = Database::connect();
$viewer = Auth::requireUser($pdo);
$id = filter_var($_GET['electionId'] ?? null, FILTER_VALIDATE_INT);
try {
    $history = BallotHistory::snapshot($pdo, $id ?: 0);
} catch (InvalidArgumentException $error) {
    http_response_code(404);
    exit('Election not found.');
}
$steps = $history['steps'];
$count = count($steps);
$requested = filter_var($_GET['step'] ?? null, FILTER_VALIDATE_INT);
$position = $requested === false || $requested === null ? $count : max(0, min($count, $requested));
$step = $position > 0 ? $steps[$position - 1] : null;
$result = $step['result'] ?? ['winner' => null, 'rounds' => []];
$name = static fn(int $movieId): string => Web::escape($history['movies'][$movieId] ?? 'Unknown film');
$link = static fn(int $number): string => 'ballot-history.php?electionId=' . (int) $id . '&amp;step=' . $number;
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ballot history · Little Gay Film Club™</title><link rel="stylesheet" href="styles.css"></head>
<body><main>
<header><?php Auth::accountBar($viewer); ?><h1>Ballot history</h1>
<nav><a href="index.php?electionId=<?= (int) $id ?>">Voting and results</a> · <a href="elections.php">All elections</a></nav>
<p><?= Web::escape($history['election']['name']) ?> · <?= $history['election']['status'] === 'closed' ? 'Closed' : 'Voting open — reload for new submissions' ?></p>
<p>Each submission replaces that member’s previous ballot. Revisions never give anyone an extra vote. Names and film details reflect their current public display.</p>
<?php if ($history['removed']): ?><p><strong>Recalculated with removed films excluded throughout.</strong> This shows how the saved ballots compare using this election’s remaining films, rather than the exact results displayed before each removal. Removed: <?= implode(', ', array_map($name, $history['removed'])) ?>.</p><?php endif; ?>
<?php if ($history['final'] !== null): ?><p>Stored final winner: <strong><?= $history['final']['winner'] === null ? 'No winner' : $name($history['final']['winner']) ?></strong>. <a href="index.php?electionId=<?= (int) $id ?>">View the frozen final rounds</a>.</p><?php endif; ?>
</header>
<section aria-labelledby="step-heading"><h2 id="step-heading">Step <?= $position ?> of <?= $count ?></h2>
<nav aria-label="Ballot history steps" class="round-controls">
<?php if ($position > 0): ?><a href="<?= $link(0) ?>">Start</a> · <a href="<?= $link($position - 1) ?>">Previous</a><?php endif; ?>
<?php if ($position < $count): ?><a href="<?= $link($position + 1) ?>">Next</a> · <a href="<?= $link($count) ?>">Latest submission</a><?php endif; ?>
</nav>
<?php if (!$step): ?><p>No ballots submitted<?= $count ? ' at this step' : ' yet' ?>. No winner.</p>
<?php else: ?>
<p><strong><?= Web::escape($step['name']) ?></strong> · <?= $step['version'] === 1 ? 'First submission' : 'Revision ' . $step['version'] ?> · <?= Web::escape($step['created_at']) ?> UTC</p>
<p><?= $step['voters'] ?> member ballot<?= $step['voters'] === 1 ? '' : 's' ?> counted.</p>
<details><summary>Submitted ranking</summary><ol><?php foreach ($step['ranking'] as $movie): ?><li><?= $name($movie) ?><?= in_array($movie, $history['removed'], true) ? ' — removed; skipped in this calculation' : '' ?></li><?php endforeach; ?></ol></details>
<p class="winner">Calculated winner: <strong><?= $result['winner'] === null ? 'No winner' : $name($result['winner']) ?></strong></p>
<?php foreach ($result['rounds'] as $index => $round): ?><article class="round"><h3>Round <?= $index + 1 ?></h3>
<table class="round-totals"><thead><tr><th scope="col">Film</th><th scope="col">Votes</th></tr></thead><tbody><?php foreach ($round['counts'] as $movie => $votes): ?><tr><th scope="row"><?= $name((int) $movie) ?></th><td><?= (int) $votes ?></td></tr><?php endforeach; ?></tbody></table>
<p>Majority needed: <?= (int) $round['majority'] ?> · Exhausted ballots: <?= (int) $round['exhausted'] ?></p>
<?php if ($round['eliminated'] !== null): ?><p>Eliminated: <?= $name($round['eliminated']) ?>. Its ballots continue to their next remaining preference.</p><?php endif; ?>
<?php if ($round['winner'] !== null): ?><p>Majority winner: <?= $name($round['winner']) ?>.</p><?php endif; ?>
</article><?php endforeach; ?>
<?php endif; ?></section>
<section><h2>Submission timeline</h2><p>Ordered by submission ID, including when timestamps share the same second.</p>
<ol class="election-history"><?php foreach ($steps as $index => $entry): ?><li><a href="<?= $link($index + 1) ?>"<?= $position === $index + 1 ? ' aria-current="step"' : '' ?>><?= Web::escape($entry['name']) ?> · <?= $entry['version'] === 1 ? 'First submission' : 'Revision ' . $entry['version'] ?></a> · <?= Web::escape($entry['created_at']) ?> UTC · <?= $entry['voters'] ?> ballot<?= $entry['voters'] === 1 ? '' : 's' ?> · Winner: <?= $entry['result']['winner'] === null ? 'None' : $name($entry['result']['winner']) ?></li><?php endforeach; ?></ol>
</section></main></body></html>
