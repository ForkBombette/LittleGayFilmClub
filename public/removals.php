<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/bootstrap.php';
use LGFC\Auth;
use LGFC\Database;
use LGFC\Movies;
use LGFC\Removals;
use LGFC\Web;
Web::start();$pdo=Database::connect();$viewer=Auth::requireUser($pdo);
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    Web::checkCsrf();
    try {
        $id=filter_var($_POST['movieId']??null,FILTER_VALIDATE_INT);
        $choice=$_POST['support']??null;
        if (!$id || !in_array($choice,['1','0'],true)) throw new InvalidArgumentException('Choose a film and a response.');
        Removals::vote($pdo,$id,$viewer['id'],$choice==='1',is_string($_POST['reason']??null)?$_POST['reason']:'');
        $_SESSION['removal_notice']='Response saved. Requests pass immediately when a majority of active members supports removal.';
        header('Location: removals.php',true,303);exit;
    } catch(InvalidArgumentException $exception) {$error=$exception->getMessage();}
    catch(Throwable $exception) {$error='Could not save your response. Please try again.';}
}
$notice=$_SESSION['removal_notice']??'';unset($_SESSION['removal_notice']);
$requests=Removals::listing($pdo,$viewer['id']);$needed=Removals::threshold($pdo);
$eligible=$pdo->query("SELECT m.* FROM movies m WHERE NOT EXISTS(SELECT 1 FROM removal_requests r WHERE r.movie_id=m.id)
    AND (m.status='active' OR (m.status='watched' AND EXISTS(SELECT 1 FROM election_movies em JOIN elections e ON e.id=em.election_id WHERE em.movie_id=m.id AND e.status='open')))")->fetchAll();
$films=array_map([Movies::class,'publicView'],$eligible);usort($films,static fn(array $a,array $b):int=>strcasecmp($a['title'],$b['title']));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Removal votes · Little Gay Film Club™</title><link rel="stylesheet" href="styles.css"></head><body><main>
<header><?php Auth::accountBar($viewer); ?><h1>Removal votes</h1><a href="index.php">Voting and results</a> · <a href="movies.php">Nominations</a>
<p>Removal needs more than half of all active members: currently <?= $needed ?> supporters. Proposing a removal counts as your support. You can change your response until it passes.</p>
<p>A passed removal eliminates the film from the current open election and excludes it from future elections. Existing ballots transfer to the next remaining choice; closed results stay unchanged.</p></header>
<?php if ($error || $notice): ?><section><p role="<?= $error?'alert':'status' ?>"><?= Web::escape($error?:$notice) ?></p></section><?php endif; ?>
<section><h2>Propose removal</h2>
<?php if (!$films): ?><p>No films are available for a new removal request.</p><?php else: ?>
<form method="post" class="nomination-form"><input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>"><input type="hidden" name="support" value="1">
<label>Film <select name="movieId" required><option value="">Choose a film…</option><?php foreach($films as $film): ?><option value="<?= $film['id'] ?>"><?= Web::escape($film['title']) ?></option><?php endforeach; ?></select></label>
<label>Reason <textarea name="reason" required maxlength="2000" rows="3"></textarea></label><p>Your reason is public to the club. Keep mystery spoilers out of it.</p><button>Propose and support removal</button></form>
<?php endif; ?></section>
<section><h2>Requests and decisions</h2>
<?php if (!$requests): ?><p>No removal requests yet.</p><?php endif; ?>
<?php foreach($requests as $request): $film=$request['movie']; ?>
<article class="pool-movie"><h3><?= Web::escape($film['title']) ?></h3><p><?= Web::escape($request['reason']) ?></p>
<?php if($request['passed_at']!==null): ?><p>Removed · <?= (int)$request['support_at_pass'] ?> supporters; <?= (int)$request['threshold_at_pass'] ?> needed when passed.</p>
<?php else: ?><p><?= $request['support'] ?> supporters · <?= $needed ?> needed.</p><p>Your response: <?= $request['my_vote']===null?'Not voted':((int)$request['my_vote']===1?'Support removal':'Keep film') ?>.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>"><input type="hidden" name="movieId" value="<?= $film['id'] ?>"><button name="support" value="1">Support removal</button> <button name="support" value="0">Keep film</button></form>
<?php endif; ?></article><?php endforeach; ?></section></main></body></html>
