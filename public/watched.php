<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
use LGFC\Database;
use LGFC\Auth;
use LGFC\Movies;
use LGFC\Watched;
use LGFC\Web;
Web::start();
$pdo = Database::connect();
$viewer = Auth::requireUser($pdo);
$isOrganiser = $viewer['role'] === 'organiser';
$error = '';
$newDraft = ['title'=>'','year'=>'','image_url'=>'','summary'=>'','watched_on'=>'','election_id'=>''];
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='new') {
    foreach($newDraft as $key=>$value) $newDraft[$key]=is_string($_POST[$key]??null)?$_POST[$key]:'';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::guardOrganiser($pdo,$viewer);
    Web::checkCsrf();
    try {
        $user = $viewer['id'];
        $action = $_POST['action'] ?? '';
        if (!in_array($action, ['existing','new'], true)) throw new InvalidArgumentException('Choose a valid watched-record action.');
        $movie = $action === 'new' ? null : filter_var($_POST['movieId'] ?? null, FILTER_VALIDATE_INT);
        if ($movie === false || $movie === 0) throw new InvalidArgumentException('Choose a film.');
        Watched::record($pdo, $user, $movie, $_POST);
        $_SESSION['watched_notice'] = 'Watched record saved. Future elections will exclude this film; existing ballots and results are unchanged.';
        header('Location: watched.php', true, 303); exit;
    } catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); }
    catch (Throwable $exception) { $error = 'Could not save the watched record. Please try again.'; }
}
$notice = $_SESSION['watched_notice'] ?? '';
unset($_SESSION['watched_notice']);
$elections = $pdo->query('SELECT id, name FROM elections ORDER BY id DESC')->fetchAll();
$movies = array_map([Movies::class, 'publicView'], $pdo->query('SELECT * FROM movies')->fetchAll());
usort($movies, static fn(array $a, array $b): int => strcasecmp($a['title'], $b['title']));
$selected = (int) ($_GET['movieId'] ?? 0);
$context = (int) ($_GET['electionId'] ?? 0);
$existing = null;
if ($selected) {
    $stmt = $pdo->prepare('SELECT * FROM movie_watches WHERE movie_id = ?');
    $stmt->execute([$selected]); $existing = $stmt->fetch();
}
$history = Watched::history($pdo);
function fields(array $elections, int $context, ?string $date): void { ?>

<label>Date watched (optional) <input type="date" name="watched_on" value="<?= Web::escape($date ?? '') ?>"></label>
<label>Related election (optional) <select name="election_id"><option value="">No election — chosen independently</option><?php foreach ($elections as $election): ?><option value="<?= (int) $election['id'] ?>" <?= $context === (int) $election['id'] ? 'selected' : '' ?>><?= Web::escape($election['name']) ?></option><?php endforeach; ?></select></label>
<?php }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Watched films · Little Gay Film Club™</title><link rel="stylesheet" href="styles.css"></head><body><?php LGFC\Navigation::render($viewer, 'watched.php'); ?><main id="main-content" tabindex="-1">
<header><h1>What we watched</h1><p>Record what actually happened, whether it won, was chosen independently, or happened before this app existed. No vote is needed.</p><nav class="page-jumps" aria-label="On this page"><span>On this page</span><a href="#watched-history">Watched history</a><?php if ($isOrganiser): ?><a href="#record-existing">Record or correct a film</a><a href="#record-new">Add a past film</a><?php endif; ?></nav></header>
<?php if ($notice || $error): ?><section><p role="<?= $error ? 'alert' : 'status' ?>"><?= Web::escape($error ?: $notice) ?></p></section><?php endif; ?>
<?php if ($isOrganiser): ?>
<section id="record-existing"><h2>Record an existing film</h2><p>Leave the date blank if you don’t know it. Linking an election records the occasion, not its winning film. Re-recording a film corrects its date and election link.</p>
<form method="post" class="nomination-form"><input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="existing">
<label>Film <select name="movieId" required><option value="">Choose a film…</option><?php foreach ($movies as $movie): ?><option value="<?= $movie['id'] ?>" <?= $selected === $movie['id'] ? 'selected' : '' ?>><?= Web::escape($movie['title']) ?><?= $movie['is_mystery'] ? ' · Mystery' : '' ?></option><?php endforeach; ?></select></label>
<?php fields($elections, (int) ($existing['election_id'] ?? $context), $existing['watched_on'] ?? null); ?>
<button>Save watched record</button></form></section>
<section id="record-new"><h2>Add a film we already watched</h2><p>For a film not yet in the catalogue. If it is already listed above, use that entry instead.</p>
<?php if (LGFC\MovieMetadata::configured()->available()): ?>
<form id="metadata-search" method="post" class="nomination-form"><label>Search film title <input name="query" required maxlength="200"></label><label>Search release year (optional) <input name="year" type="number" min="1888" max="2100"></label><button>Search TMDB</button></form>
<p>Selecting a result copies its title, year, poster and synopsis into the form below. Your watched date and election link stay as entered. Nothing is saved until you add it to watched films.</p>
<p id="metadata-message" role="status"></p><div id="metadata-results"></div><noscript>Search needs JavaScript. You can enter the film manually below.</noscript>
<?php else: ?><p>Movie search is unavailable. You can enter the film manually below.</p><?php endif; ?>
<p>Movie data and images from TMDB. <a href="credits.php">Credits</a></p>
<form method="post" id="watched-new-form" class="nomination-form"><input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="new">
<label>Film title <input name="title" required maxlength="300" value="<?= Web::escape($newDraft['title']) ?>"></label><label>Release year (optional) <input type="number" name="year" min="1888" max="2100" value="<?= Web::escape($newDraft['year']) ?>"></label>
<label>Poster URL or local images/ path <input name="image_url" maxlength="2000" value="<?= Web::escape($newDraft['image_url']) ?>"></label>
<label>Synopsis <textarea name="summary" rows="3" maxlength="8000"><?= Web::escape($newDraft['summary']) ?></textarea></label>
<?php fields($elections, (int)$newDraft['election_id'], $newDraft['watched_on']); ?>
<button>Add to watched films</button></form></section>
<?php endif; ?>
<section id="watched-history"><h2>Watched history</h2><p>These films will be excluded when the next election opens. A film already on an open ballot stays there. Marking a mystery watched does not reveal it.</p>
<?php if (!$history): ?><p>No watched films recorded yet.</p><?php endif; ?>
<?php foreach ($history as $entry): $movie = $entry['movie']; ?>
<article class="pool-movie"><h3><?= Web::escape($movie['title']) ?></h3><p><?= $entry['watched_on'] ? 'Watched ' . Web::escape($entry['watched_on']) : 'Watched — date unknown' ?><?php if ($entry['election_id']): ?> · <a href="index.php?electionId=<?= $entry['election_id'] ?>"><?= Web::escape($entry['election_name']) ?></a><?php else: ?> · No election linked<?php endif; ?></p><p><?= Web::escape($movie['nomination_pitch'] ?: '') ?></p><?php if ($isOrganiser): ?><a href="watched.php?movieId=<?= $movie['id'] ?>">Correct watched details</a><?php endif; ?><?php if ($movie['is_mystery']): ?><p>Mystery identity still hidden. <a href="movies.php">The nominator can reveal it separately.</a></p><?php endif; ?></article>
<?php endforeach; ?></section></main><?php if($isOrganiser): ?><script type="module" src="../frontend/dist/watched.js"></script><?php endif; ?></body></html>
