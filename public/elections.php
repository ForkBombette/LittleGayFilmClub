<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
use LGFC\Database;
use LGFC\Auth;
use LGFC\Elections;
use LGFC\Movies;
use LGFC\Web;
Web::start();
$pdo = Database::connect();
$viewer = Auth::requireUser($pdo);
$isOrganiser = $viewer['role'] === 'organiser';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::guardOrganiser($pdo,$viewer);
    Web::checkCsrf();
    try {
        if (($_POST['action'] ?? '') === 'open') {
            $size=filter_var($_POST['size']??null,FILTER_VALIDATE_INT);
            $guaranteed=($_POST['guaranteed']??'')==='' ? null : filter_var($_POST['guaranteed'],FILTER_VALIDATE_INT);
            if(!in_array($size,[5,8],true) || $guaranteed===false) throw new InvalidArgumentException('Choose 5 or 8 films and a valid guaranteed film.');
            $id = Elections::open($pdo, is_string($_POST['name'] ?? null) ? $_POST['name'] : '', $size, $guaranteed);
        } elseif (($_POST['action'] ?? '') === 'close' && ($_POST['confirm'] ?? '') === 'yes') {
            $id = filter_var($_POST['electionId'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) throw new InvalidArgumentException('Choose an election to close.');
            $date=is_string($_POST['nightDate']??null)?$_POST['nightDate']:'';
            if($date!=='') {
                $expected=filter_var($_POST['expectedPlan']??null,FILTER_VALIDATE_INT);
                if($expected===false || $expected<0) throw new InvalidArgumentException('Reload the election controls.');
                LGFC\MovieNights::announce($pdo,$viewer['id'],'election',$id,$date,$expected,$id);
            } else Elections::close($pdo, $id);
        } else throw new InvalidArgumentException('Confirm that you want to close voting.');
        header('Location: index.php?electionId=' . $id, true, 303);
        exit;
    } catch (DomainException | InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        $error = 'Could not change the election. Please try again.';
    }
}
$elections = $pdo->query('SELECT e.*, c.cancelled_at FROM elections e LEFT JOIN election_cancellations c ON c.election_id=e.id ORDER BY e.id DESC')->fetchAll();
$planVersion = $error ? (int)($_POST['expectedPlan']??0) : LGFC\MovieNights::latestId($pdo);
$open = array_values(array_filter($elections, static fn(array $e): bool => $e['status'] === 'open'));
$pool = array_map([Movies::class, 'publicView'], LGFC\ElectionDraw::pool($pdo));
$skips=LGFC\ElectionDraw::skips($pdo);
$drawHistory=[];
foreach($pdo->query('SELECT d.*,m.* FROM election_draw_movies d JOIN movies m ON m.id=d.movie_id ORDER BY d.election_id DESC,m.id')->fetchAll() as $row) {
 $drawHistory[$row['election_id']][]=['movie'=>Movies::publicView($row),'selection'=>$row['selection'],'skips'=>$row['skipped_before']];
}
usort($pool, static fn(array $a, array $b): int => strcasecmp($a['title'], $b['title']));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Elections · Little Gay Film Club™</title><link rel="stylesheet" href="styles.css"></head>
<body><?php LGFC\Navigation::render($viewer, 'elections.php'); ?><main id="main-content" tabindex="-1">
<header><p class="eyebrow">The electoral register</p><h1>Elections</h1><p>Organisers open and close elections. Members can inspect all election results.</p><p><a href="#all-elections">Browse election results and ballot histories ↓</a></p></header>
<?php if ($error): ?><section><p role="alert"><?= Web::escape($error) ?></p></section><?php endif; ?>
<?php if ($isOrganiser): ?>
<?php if ($open): ?>
<?php foreach ($open as $election): ?>
<section><h2><?= Web::escape($election['name']) ?></h2><p>Voting is open. Submitted ballots: <?= count(Elections::ballots($pdo, (int) $election['id'])) ?>.</p>
<p>Closing freezes the final result and stops further ballot submissions. It does not reveal mystery films or mark any film as watched.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="close"><input type="hidden" name="electionId" value="<?= (int) $election['id'] ?>">
<input type="hidden" name="expectedPlan" value="<?= $planVersion ?>">
<label>Movie night date (optional) <input type="date" name="nightDate" value="<?= Web::escape(is_string($_POST['nightDate']??null)?$_POST['nightDate']:'') ?>"></label>
<p>Set a date to announce the winner in the banner as voting closes. Leave it blank to close without changing the announcement. <a href="movie-night.php">Choose a film directly instead</a>.</p>
<label><input type="checkbox" name="confirm" value="yes" required> Close voting permanently for this election.</label> <button>Close election</button></form>
<p><a href="index.php?electionId=<?= (int) $election['id'] ?>">Return to this ballot</a></p></section>
<?php endforeach; ?>
<?php else: ?>
<section><h2>Open the next election</h2><p>The <?= count($pool) ?> films below form the eligible pool; the scheduled film is excluded. New nominations made afterwards will wait for another election. Each voter starts with a fresh ballot.</p>
<?php if ($pool): ?>
<form method="post" class="nomination-form"><input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="open">
<label>Election name <input name="name" required maxlength="200" value="<?= Web::escape(is_string($_POST['name'] ?? null) ? $_POST['name'] : '') ?>" placeholder="Next movie night"></label><label>Number of films <select name="size"><option value="5" <?= ($_POST['size']??'8')==='5'?'selected':'' ?>>5</option><option value="8" <?= ($_POST['size']??'8')==='8'?'selected':'' ?>>8</option></select></label>
<label>Guarantee one film (optional) <select name="guaranteed"><option value="">No guaranteed film</option><?php foreach($pool as $film): ?><option value="<?= (int)$film['id'] ?>" <?= (string)($_POST['guaranteed']??'')===(string)$film['id']?'selected':'' ?>><?= Web::escape(LGFC\FilmUi::label($film)) ?></option><?php endforeach; ?></select></label>
<p>The guaranteed film counts towards the total. Films skipped in at least three eligible elections get first access to the remaining places, randomly chosen if there are too many. Other places are random. If the pool is smaller, all eligible films are included. Cancelled elections do not count towards skips.</p>
<button>Draw films and open election</button></form>
<details><summary>Films in the current pool (<?= count($pool) ?>)</summary><ul><?php foreach ($pool as $movie): ?><li><?= LGFC\FilmUi::movie($movie) ?><?= $movie['is_mystery'] ? ' · Mystery' : '' ?> · <?= (int)($skips[$movie['id']]??0) ?> consecutive skips</li><?php endforeach; ?></ul></details>
<?php else: ?><p>No eligible films available. The scheduled film is excluded. <a href="movies.php">Nominate a film</a> before opening an election.</p><?php endif; ?>
</section>
<?php endif; ?>
<?php endif; ?>
<section id="all-elections"><h2>All elections</h2><?php if (!$elections): ?><p>No elections yet.</p><?php endif; ?>
<ul class="election-history"><?php foreach ($elections as $election): ?><li><a href="index.php?electionId=<?= (int) $election['id'] ?>"><?= Web::escape($election['name']) ?></a> · <?= $election['cancelled_at'] ? 'Cancelled — archived tally' : ($election['status'] === 'open' ? 'Voting open' : 'Closed — final result') ?><?php if ($election['closed_at']): ?> · <?= Web::escape($election['closed_at']) ?> UTC<?php endif; ?> · <a href="ballot-history.php?electionId=<?= (int) $election['id'] ?>">Ballot history</a><details><summary>Candidate selection</summary><?php if(!isset($drawHistory[$election['id']])): ?><p>Selection method was not recorded for this older election.</p><?php else: ?><ul><?php foreach($drawHistory[$election['id']] as $entry): ?><li><?= LGFC\FilmUi::movie($entry['movie']) ?> · <?= Web::escape(['random'=>'Random draw','priority'=>'Random draw — three-skip priority','guaranteed'=>'Guaranteed','skipped'=>'Not selected'][$entry['selection']]) ?> · <?= (int)$entry['skips'] ?> prior consecutive skips</li><?php endforeach; ?></ul><?php endif; ?></details></li><?php endforeach; ?></ul></section>
</main><?php require dirname(__DIR__) . '/src/movie-dialog.php'; ?></body></html>
