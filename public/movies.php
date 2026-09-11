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
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nominations · Little Gay Film Club™</title><link rel="stylesheet" href="styles.css"></head>
<body><main>
<header><?php Auth::accountBar($viewer); ?><h1>Film nominations</h1><a href="index.php">Back to voting</a> · <a href="watched.php">Watched films</a><p>New films join the next election. An open election keeps its original list.</p></header>
<section>

<?php if ($notice): ?><p role="status"><?= Web::escape($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p role="alert"><?= Web::escape($error) ?></p><?php endif; ?>
</section>
<section><h2>Nominate a film</h2>
<form method="post" class="nomination-form" autocomplete="off">
<input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="nominate">
<label>Real film title <input name="title" required maxlength="300"></label>
<label>Release year <input name="year" type="number" min="1888" max="2100"></label>
<label>Poster URL or local images/ path <input name="image_url" maxlength="2000" placeholder="https://…"></label>
<label>Synopsis <textarea name="summary" rows="3" maxlength="8000"></textarea></label>
<label><span><input name="mystery" type="checkbox" value="1"> Keep this film a mystery</span></label>
<p>A mystery shows only your alias and pitch. Its title, year, poster and synopsis stay hidden until you deliberately reveal it. Avoid spoilers in your pitch.</p>
<label>Public mystery alias <input name="alias" maxlength="200" placeholder="An entirely sensible selection"></label>
<label>Your pitch <textarea name="pitch" rows="4" maxlength="4000"></textarea></label>
<button>Save nomination</button>
</form></section>
<section><h2>Film catalogue</h2>
<?php foreach ($movies as $movie): ?>
<article class="pool-movie"><h3><?= Web::escape($movie['title']) ?><?= $movie['is_mystery'] ? ' · Mystery' : '' ?></h3>
<p><?= Web::escape($movie['nomination_pitch'] ?: 'No pitch yet.') ?></p>
<?php if ($movie['is_mystery'] && $movie['nominator_id'] === $userId): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="reveal"><input type="hidden" name="movieId" value="<?= $movie['id'] ?>">
<label><input type="checkbox" name="confirm" value="yes" required> Reveal this film’s identity to everyone. This cannot be undone.</label> <button>Reveal film</button>
</form>
<?php elseif ($movie['is_mystery']): ?><p>Only the nominator can deliberately reveal this film. Winning or closing an election will not reveal it.</p><?php endif; ?>
</article>
<?php endforeach; ?>
</section></main></body></html>
