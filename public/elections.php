<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
use LGFC\Database;
use LGFC\Elections;
use LGFC\Movies;
use LGFC\Web;
Web::start();
$pdo = Database::connect();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Web::checkCsrf();
    try {
        if (($_POST['action'] ?? '') === 'open') {
            $id = Elections::open($pdo, is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
        } elseif (($_POST['action'] ?? '') === 'close' && ($_POST['confirm'] ?? '') === 'yes') {
            $id = filter_var($_POST['electionId'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) throw new InvalidArgumentException('Choose an election to close.');
            Elections::close($pdo, $id);
        } else throw new InvalidArgumentException('Confirm that you want to close voting.');
        header('Location: index.php?electionId=' . $id, true, 303);
        exit;
    } catch (DomainException | InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        $error = 'Could not change the election. Please try again.';
    }
}
$elections = $pdo->query('SELECT * FROM elections ORDER BY id DESC')->fetchAll();
$open = array_values(array_filter($elections, static fn(array $e): bool => $e['status'] === 'open'));
$pool = array_map([Movies::class, 'publicView'], $pdo->query("SELECT * FROM movies WHERE status = 'active'")->fetchAll());
usort($pool, static fn(array $a, array $b): int => strcasecmp($a['title'], $b['title']));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Elections · Little Gay Film Club™</title><link rel="stylesheet" href="styles.css"></head>
<body><main>
<header><h1>Election controls</h1><nav><a href="index.php">Voting and results</a> · <a href="movies.php">Nominations</a> · <a href="watched.php">Record watched films</a></nav><p>Development controls are available to everyone for now. Login and organiser permissions are still to come.</p></header>
<?php if ($error): ?><section><p role="alert"><?= Web::escape($error) ?></p></section><?php endif; ?>
<?php if ($open): ?>
<?php foreach ($open as $election): ?>
<section><h2><?= Web::escape($election['name']) ?></h2><p>Voting is open. Submitted ballots: <?= count(Elections::ballots($pdo, (int) $election['id'])) ?>.</p>
<p>Closing freezes the final result and stops further ballot submissions. It does not reveal mystery films or mark any film as watched.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="close"><input type="hidden" name="electionId" value="<?= (int) $election['id'] ?>">
<label><input type="checkbox" name="confirm" value="yes" required> Close voting permanently for this election.</label> <button>Close election</button></form>
<p><a href="index.php?electionId=<?= (int) $election['id'] ?>">Return to this ballot</a></p></section>
<?php endforeach; ?>
<?php else: ?>
<section><h2>Open the next election</h2><p>The <?= count($pool) ?> active films listed below will be eligible. New nominations made afterwards will wait for another election. Each voter starts with a fresh ballot.</p>
<?php if ($pool): ?>
<form method="post" class="nomination-form"><input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="open">
<label>Election name <input name="name" required maxlength="200" value="<?= Web::escape(is_string($_POST['name'] ?? null) ? $_POST['name'] : '') ?>" placeholder="Next movie night"></label><button>Open election</button></form>
<details><summary>Films in the current pool (<?= count($pool) ?>)</summary><ul><?php foreach ($pool as $movie): ?><li><?= Web::escape($movie['title']) ?><?= $movie['is_mystery'] ? ' · Mystery' : '' ?></li><?php endforeach; ?></ul></details>
<?php else: ?><p>No active films yet. <a href="movies.php">Nominate a film</a> before opening an election.</p><?php endif; ?>
</section>
<?php endif; ?>
<section><h2>All elections</h2><?php if (!$elections): ?><p>No elections yet.</p><?php endif; ?>
<ul class="election-history"><?php foreach ($elections as $election): ?><li><a href="index.php?electionId=<?= (int) $election['id'] ?>"><?= Web::escape($election['name']) ?></a> · <?= $election['status'] === 'open' ? 'Voting open' : 'Closed — final result' ?><?php if ($election['closed_at']): ?> · <?= Web::escape($election['closed_at']) ?> UTC<?php endif; ?></li><?php endforeach; ?></ul></section>
</main></body></html>
