<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
use LGFC\Auth;
use LGFC\Database;
use LGFC\Movies;
use LGFC\Web;
Web::start();
$pdo = Database::connect();
$viewer = Auth::requireUser($pdo);
$id = filter_var($_GET['movieId'] ?? null, FILTER_VALIDATE_INT);
try {
    $movie = Movies::editable($pdo, $id ?: 0, $viewer['id']);
} catch (InvalidArgumentException $error) {
    http_response_code(403);
    exit('Only this film’s nominator can edit it.');
}
$error = '';
$values = ['title' => $movie['title'], 'year' => $movie['release_year'],
    'image_url' => $movie['image_url'], 'summary' => $movie['summary'],
    'pitch' => $movie['nomination_pitch'], 'alias' => $movie['mystery_alias'],
    'version' => Movies::editVersion($movie)];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Web::checkCsrf();
    // Retain typed text on validation errors, including the original concurrency token.
    foreach ($values as $key => $value) $values[$key] = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    try {
        Movies::edit($pdo, $movie['id'], $viewer['id'], $values);
        $_SESSION['notice'] = 'Nomination updated. Reload other open pages to see the changes.';
        header('Location: movies.php', true, 303);
        exit;
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        $error = 'Could not save this change. Please try again.';
    }
}
$hidden = $movie['mystery_alias'] !== null && $movie['revealed_at'] === null;
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Edit nomination · Little Gay Film Club™</title><link rel="stylesheet" href="styles.css"></head>
<body><main><header><?php Auth::accountBar($viewer); ?><h1>Edit your nomination</h1><a href="movies.php">Back to nominations</a></header>
<section>
<?php if ($hidden): ?><p>This editor shows your private film details. Everyone else sees only the mystery alias and pitch. Saving here does not reveal the film.</p><?php endif; ?>
<p>Correct this film’s details here. To suggest a different film, add a new nomination. Changes appear wherever this film is shown, including election history.</p>
<?php if ($error): ?><p role="alert"><?= Web::escape($error) ?></p><?php endif; ?>
<form method="post" class="nomination-form" autocomplete="off">
<input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>">
<input type="hidden" name="version" value="<?= Web::escape($values['version']) ?>">
<label>Real film title <input name="title" required maxlength="300" value="<?= Web::escape($values['title']) ?>"></label>
<label>Release year <input name="year" type="number" min="1888" max="2100" value="<?= Web::escape($values['year']) ?>"></label>
<label>Poster URL or local images/ path <input name="image_url" maxlength="2000" value="<?= Web::escape($values['image_url']) ?>"></label>
<label>Synopsis <textarea name="summary" rows="3" maxlength="8000"><?= Web::escape($values['summary']) ?></textarea></label>
<?php if ($movie['mystery_alias'] !== null): ?>
<label>Public mystery alias <input name="alias" required maxlength="200" value="<?= Web::escape($values['alias']) ?>"></label>
<?php endif; ?>
<label>Your pitch <textarea name="pitch" rows="4" maxlength="4000" <?= $movie['mystery_alias'] !== null ? 'required' : '' ?>><?= Web::escape($values['pitch']) ?></textarea></label>
<button>Save changes</button> <a href="movies.php">Cancel</a>
</form></section></main></body></html>
