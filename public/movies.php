<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
use LGFC\Database;
use LGFC\Auth;
use LGFC\Movies;
use LGFC\Web;
Web::start();
$pdo = Database::connect();
$viewer = Auth::requireUser($pdo);
$userId = $viewer['id'];
$error = '';
$draft = ['title'=>'','year'=>'','image_url'=>'','summary'=>'','alias'=>'','pitch'=>'','mystery'=>''];
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='nominate') {
    foreach($draft as $key=>$value) $draft[$key]=is_string($_POST[$key]??null)?$_POST[$key]:'';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Web::checkCsrf();
    try {
        if (($_POST['action'] ?? '') === 'nominate') {
            Movies::nominate($pdo, $userId, $_POST);
            $_SESSION['notice'] = 'Nomination saved for the next election. The current ballot stays unchanged.';
        } elseif (($_POST['action'] ?? '') === 'reveal' && ($_POST['confirm'] ?? '') === 'yes') {
            Movies::reveal($pdo, (int) ($_POST['movieId'] ?? 0), $userId);
            $_SESSION['notice'] = 'Film revealed. Reload any open voting pages to see its identity.';
        } else {
            throw new InvalidArgumentException('Confirm that you want to reveal this film to everyone.');
        }
        header('Location: movies.php', true, 303);
        exit;
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        $error = 'Could not save this change. Please try again.';
    }
}
$notice = $_SESSION['notice'] ?? '';
unset($_SESSION['notice']);
$movies = array_map([Movies::class, 'publicView'], $pdo->query("SELECT * FROM movies WHERE status IN ('active', 'watched')")->fetchAll());
usort($movies, static fn(array $a, array $b): int => strcasecmp($a['title'], $b['title']));
$mine = array_values(array_filter($movies, static fn(array $movie): bool => $movie['nominator_id'] === $userId));
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nominations · Little Gay Film Club™</title><link rel="stylesheet" href="styles.css"></head>
<body><?php LGFC\Navigation::render($viewer, 'movies.php'); ?><main id="main-content" tabindex="-1">
<header><h1>Film nominations</h1><p>New films join the next election. An open election keeps its original list.</p><nav class="page-jumps" aria-label="On this page"><span>On this page</span><a href="#find-film">Find & nominate</a><a href="#your-nominations">Your nominations</a><a href="#catalogue">Catalogue & reveals</a></nav></header>
<?php if ($notice || $error): ?><section>
<?php if ($notice): ?><p role="status"><?= Web::escape($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p role="alert"><?= Web::escape($error) ?></p><?php endif; ?>
</section><?php endif; ?>
<section id="your-nominations"><h2>Your nominations (<?= count($mine) ?>)</h2>
<?php if (!$mine): ?><p>You haven’t nominated any films yet.</p><?php else: ?>
<ul><?php foreach ($mine as $movie): ?><li><?= LGFC\FilmUi::movie($movie) ?> · <a href="edit-movie.php?movieId=<?= $movie['id'] ?>">Edit</a><?= $movie['is_mystery'] ? ' · Mystery' : '' ?></li><?php endforeach; ?></ul>
<p>Select a film to read its details and discussion; use Edit to change your nomination. Mystery reveals are separate, in the catalogue below.</p>
<?php endif; ?></section>
<section id="find-film"><h2>Find a film</h2>
<?php if (LGFC\MovieMetadata::configured()->available()): ?>
<form id="metadata-search" method="post" class="nomination-form"><label>Film title <input name="query" required maxlength="200"></label><label>Release year (optional) <input name="year" type="number" min="1888" max="2100" value="<?= Web::escape($draft['year']) ?>"></label><button>Search TMDB</button></form>
<p>Selecting a result replaces the title, year, poster and synopsis below. Your pitch and mystery choices stay as you set them. Nothing is saved until you save the nomination.</p>
<p id="metadata-message" role="status"></p><div id="metadata-results"></div>
<noscript>Search needs JavaScript. You can still nominate a film manually below.</noscript>
<?php else: ?><p>Movie search is not available yet. You can enter a film manually below.</p><?php endif; ?>
<p>Movie data and images from TMDB. <a href="credits.php">Credits</a></p></section>
<section id="nominate"><h2>Nominate a film</h2>
<form method="post" id="nomination-form" class="nomination-form" autocomplete="off">
<input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="nominate">
<label>Real film title <input name="title" required maxlength="300" value="<?= Web::escape($draft['title']) ?>"></label>
<label>Release year <input name="year" type="number" min="1888" max="2100"></label>
<label>Poster URL or local images/ path <input name="image_url" maxlength="2000" placeholder="https://…" value="<?= Web::escape($draft['image_url']) ?>"></label>
<label>Synopsis <textarea name="summary" rows="3" maxlength="8000"><?= Web::escape($draft['summary']) ?></textarea></label>
<label><span><input name="mystery" type="checkbox" value="1" <?= $draft['mystery']==='1'?'checked':'' ?>> Keep this film a mystery</span></label>
<p>A mystery shows only your alias and pitch. Its title, year, poster and synopsis stay hidden until you deliberately reveal it. Avoid spoilers in your pitch.</p>
<label>Public mystery alias <input name="alias" maxlength="200" placeholder="An entirely sensible selection" value="<?= Web::escape($draft['alias']) ?>"></label>
<label>Your pitch <textarea name="pitch" rows="4" maxlength="4000"><?= Web::escape($draft['pitch']) ?></textarea></label>
<button>Save nomination</button>
</form></section>
<section id="catalogue"><h2>Film catalogue</h2>
<?php foreach ($movies as $movie): ?>
<article class="pool-movie catalogue-movie"><div class="catalogue-heading">
<div class="movie-art" aria-hidden="true"><?php if ($movie['image_url']): ?><img src="<?= Web::escape($movie['image_url']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php else: ?><span><?= $movie['is_mystery'] ? '?' : '▶' ?></span><?php endif; ?></div>
<h3><button type="button" class="film-title" data-details="<?= $movie['id'] ?>" aria-haspopup="dialog"><?= Web::escape($movie['title']) ?><?= $movie['release_year'] !== null ? ' (' . $movie['release_year'] . ')' : '' ?></button><?php if ($movie['is_mystery']): ?><small>Mystery film · judge it by the pitch</small><?php endif; ?></h3></div>
<p><?= Web::escape($movie['nomination_pitch'] ?: 'No pitch yet.') ?></p>
<?php if ($movie['nominator_id'] === $userId): ?><p><a href="edit-movie.php?movieId=<?= $movie['id'] ?>">Edit nomination</a></p><?php endif; ?>
<?php if ($movie['is_mystery'] && $movie['nominator_id'] === $userId): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="reveal"><input type="hidden" name="movieId" value="<?= $movie['id'] ?>">
<label><input type="checkbox" name="confirm" value="yes" required> Reveal this film’s identity to everyone. This cannot be undone.</label> <button>Reveal film</button>
</form>
<?php elseif ($movie['is_mystery']): ?><p>Only the nominator can deliberately reveal this film. Winning or closing an election will not reveal it.</p><?php endif; ?>
</article>
<?php endforeach; ?>
</section></main>
<?php require dirname(__DIR__) . '/src/movie-dialog.php'; ?>
<script type="application/json" id="catalogue-data"><?= json_encode($movies, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script type="module" src="../frontend/dist/nomination.js"></script></body></html>
