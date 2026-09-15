<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/bootstrap.php';
use LGFC\{Auth, Database, FilmUi, MovieNights, Movies, Web};
Web::start();$pdo=Database::connect();$viewer=Auth::requireUser($pdo);$organiser=$viewer['role']==='organiser';
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    Auth::guardOrganiser($pdo,$viewer);Web::checkCsrf();
    try {
        $plan=filter_var($_POST['expectedPlan']??null,FILTER_VALIDATE_INT);
        $open=filter_var($_POST['expectedOpen']??null,FILTER_VALIDATE_INT);
        if($plan===false || $open===false || $plan<0 || $open<0) throw new InvalidArgumentException('Reload before changing the announcement.');
        $action=$_POST['action']??'';$date=is_string($_POST['date']??null)?$_POST['date']:'';
        if($action==='reschedule') MovieNights::reschedule($pdo,$viewer['id'],$date,$plan,$open);
        elseif($action==='clear' && ($_POST['confirm']??'')==='yes') MovieNights::clear($pdo,$viewer['id'],$plan,$open);
        elseif(in_array($action,['election','direct'],true) && ($_POST['confirm']??'')==='yes') {
            $choice=filter_var($_POST['choice']??null,FILTER_VALIDATE_INT);
            if(!$choice || $choice<1) throw new InvalidArgumentException('Choose a film or election.');
            MovieNights::announce($pdo,$viewer['id'],$action,$choice,$date,$plan,$open);
        } else throw new InvalidArgumentException('Confirm the announcement before saving.');
        header('Location: movie-night.php',true,303);exit;
    } catch(DomainException | InvalidArgumentException $e){$error=$e->getMessage();}
    catch(Throwable $e){$error='Could not update the announcement. Please try again.';}
}
// Do not silently rebase a rejected form onto newer announcement/election state.
$plan=$error ? (int)($_POST['expectedPlan']??0) : MovieNights::latestId($pdo);
$open=$error ? (int)($_POST['expectedOpen']??0) : MovieNights::openId($pdo);
$current=MovieNights::current($pdo);
$films=array_map([Movies::class,'publicView'],$pdo->query('SELECT * FROM movies ORDER BY id')->fetchAll());
usort($films,static fn($a,$b)=>strcasecmp(FilmUi::label($a),FilmUi::label($b)));
$elections=$pdo->query('SELECT e.* FROM elections e WHERE NOT EXISTS(SELECT 1 FROM election_cancellations c WHERE c.election_id=e.id) ORDER BY e.id DESC')->fetchAll();
$today=(new DateTimeImmutable('now',new DateTimeZone('Europe/London')))->format('Y-m-d');
function nightFields(string $action,int $plan,int $open):void { ?>
<input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="<?= $action ?>"><input type="hidden" name="expectedPlan" value="<?= $plan ?>"><input type="hidden" name="expectedOpen" value="<?= $open ?>">
<?php }
function nightDate(string $action,string $today,?array $current):void { $date=($_POST['action']??'')===$action && is_string($_POST['date']??null)?$_POST['date']:($current['scheduled_on']??''); ?>
<label>Movie night date <input type="date" name="date" min="<?= $today ?>" required value="<?= Web::escape($date) ?>"></label>
<?php }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Next movie night · Little Gay Film Club™</title><link rel="stylesheet" href="styles.css"></head>
<body><?php LGFC\Navigation::render($viewer,'movie-night.php'); ?><main id="main-content" tabindex="-1">
<header><p class="eyebrow">The actual plan</p><h1>Next movie night</h1><p>The announced film may be an election winner or a direct organiser choice. Recording what we watched is a separate step.</p></header>
<?php if($error): ?><section><p role="alert"><?= Web::escape($error) ?></p><p><a href="movie-night.php">Reload the current announcement</a> before retrying a stale form.</p></section><?php endif; ?>
<?php if($current): $movie=$current['movie']; ?>
<section id="chosen-film"><h2><?= FilmUi::movie($movie) ?></h2><p><time datetime="<?= Web::escape($current['scheduled_on']) ?>"><?= Web::escape((new DateTimeImmutable($current['scheduled_on']))->format('l j F Y')) ?></time> · <?= $current['source']==='election'?'Democratically selected':'Chosen directly by an organiser' ?></p>
<?php if($movie['image_url']): ?><div class="movie-art detail-art"><img src="<?= Web::escape($movie['image_url']) ?>" alt="" referrerpolicy="no-referrer"></div><?php endif; ?>
<p><?= Web::escape($movie['nomination_pitch']?:'No pitch yet.') ?></p><?php if(!$movie['is_mystery']): ?><p><?= Web::escape($movie['summary']?:'No synopsis added yet.') ?></p><?php else: ?><p>Mystery film: its identity remains hidden until deliberately revealed.</p><?php endif; ?>
<?php if($current['election_id']): ?><p><a href="index.php?electionId=<?= (int)$current['election_id'] ?>">View the selecting election</a></p><?php endif; ?>
<?php if($organiser): ?><p><a href="watched.php?movieId=<?= (int)$current['movie_id'] ?><?= $current['election_id']?'&amp;electionId='.(int)$current['election_id']:'' ?>">Record what we actually watched</a></p><?php endif; ?></section>
<?php else: ?><section><p>No movie night has been announced yet.</p></section><?php endif; ?>
<?php if($organiser): ?>
<?php if($elections): ?><section><h2>Announce an election winner</h2><p>If voting is open, this closes it and announces the winner calculated at that moment. An election with no winner cannot be announced.</p>
<form method="post" class="nomination-form"><?php nightFields('election',$plan,$open); ?>
<label>Election <select name="choice" required><?php foreach($elections as $e): ?><option value="<?= (int)$e['id'] ?>" <?= (int)((($_POST['action']??'')==='election')?($_POST['choice']??0):$open)===(int)$e['id']?'selected':'' ?>><?= Web::escape($e['name']) ?> · <?= $e['status']==='open'?'Voting open':'Closed' ?></option><?php endforeach; ?></select></label>
<?php nightDate('election',$today,$current); ?><label><span><input type="checkbox" name="confirm" value="yes" required> Close voting if open and announce this election’s winner, replacing the previous announcement.</span></label><button>Announce winner</button></form></section><?php endif; ?>
<section><h2>Choose the film directly</h2><p>This replaces the current announcement and cancels any open election. Its ballots and tally are archived; they do not choose the announced film. Film voting status is unchanged.</p>
<?php if($films): ?><form method="post" class="nomination-form"><?php nightFields('direct',$plan,$open); ?>
<label>Film <select id="night-film-select" name="choice" required><option value="">Choose a film…</option><?php foreach($films as $film): ?><option value="<?= $film['id'] ?>" <?= ($_POST['action']??'')==='direct' && (int)($_POST['choice']??0)===$film['id']?'selected':'' ?>><?= Web::escape(FilmUi::label($film)) ?></option><?php endforeach; ?></select></label>
<button type="button" data-film-select="night-film-select" aria-haspopup="dialog">Details of selected film</button>
<?php nightDate('direct',$today,$current); ?><label><span><input type="checkbox" name="confirm" value="yes" required> Announce this film directly and cancel any open election.</span></label><button>Announce direct choice</button></form><?php endif; ?>
<p>Film missing? <a href="movies.php#find-film">Add it under Films</a>, then return here to announce it.</p></section>
<?php if($current): ?><section><h2>Change or clear the announcement</h2><form method="post" class="nomination-form"><?php nightFields('reschedule',$plan,$open); nightDate('reschedule',$today,$current); ?><button>Change date only</button></form>
<form method="post"><?php nightFields('clear',$plan,$open); ?><label><input type="checkbox" name="confirm" value="yes" required> Clear the announcement. This does not reopen voting or mark a film watched.</label> <button>Clear announcement</button></form></section><?php endif; ?>
<?php endif; ?></main><?php require dirname(__DIR__).'/src/movie-dialog.php'; ?></body></html>
