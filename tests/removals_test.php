<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/bootstrap.php';
use LGFC\Removals;
use LGFC\Elections;
use LGFC\Movies;
use LGFC\CandidatesChanged;
function check(bool $ok,string $label): void { if(!$ok)throw new RuntimeException($label);echo "PASS - {$label}\n"; }
function rejects(callable $fn,string $label): void { try{$fn();}catch(InvalidArgumentException $error){check(true,$label);return;}throw new RuntimeException($label); }
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$pdo->exec(file_get_contents(__DIR__.'/../db/schema.sql'));$pdo->exec(file_get_contents(__DIR__.'/../db/seed.sql'));
Removals::migrate($pdo);Removals::migrate($pdo);
check(Removals::threshold($pdo)===3,'four active members need three supporters');
$old=Elections::candidateIds($pdo,1);Elections::submit($pdo,1,1,$old);Elections::close($pdo,1);
$frozen=Elections::finalResult($pdo,1);$current=Elections::open($pdo,'Removal test');$ids=Elections::candidateIds($pdo,$current);[$a,$b]=$ids;
Elections::submit($pdo,$current,1,$ids);Elections::submit($pdo,$current,2,$ids);
$history=$pdo->query('SELECT * FROM ballot_revision_choices')->fetchAll();$snapshot=$pdo->query('SELECT * FROM election_movies')->fetchAll();
rejects(fn()=>Removals::vote($pdo,999,1,true,'Reason'),'unknown film rejected');
rejects(fn()=>Removals::vote($pdo,$a,1,true,''),'request requires reason');
rejects(fn()=>Removals::vote($pdo,$a,999,true,'Reason'),'unknown voter rejected');
Removals::vote($pdo,$a,1,true,'Time to move on');Removals::vote($pdo,$a,1,true);
check(Removals::listing($pdo,1)[0]['support']===1,'repeat support still counts as one person');
Removals::vote($pdo,$a,2,true);
check(in_array($a,Elections::candidateIds($pdo,$current),true),'half the members and all responding votes cannot remove');
Removals::vote($pdo,$a,2,false);check(Removals::listing($pdo,2)[0]['support']===1,'pending response can change to keep');
Removals::vote($pdo,$a,2,true);Removals::vote($pdo,$a,3,true);
check(!in_array($a,Elections::candidateIds($pdo,$current),true),'strict majority immediately excludes open candidate');
check($pdo->query('SELECT status FROM movies WHERE id='.$a)->fetchColumn()==='removed','passed request marks film removed');
check(Elections::currentResult($pdo,$current)['winner']===$b,'existing preferences transfer to next remaining film');
check($history===$pdo->query('SELECT * FROM ballot_revision_choices')->fetchAll(),'removal retains original ranked choices');
check($snapshot===$pdo->query('SELECT * FROM election_movies')->fetchAll(),'removal preserves original eligibility snapshots');
check(Elections::finalResult($pdo,1)===$frozen && Elections::candidateIds($pdo,1)===$old,'closed result and candidate display unchanged');
rejects(fn()=>Removals::vote($pdo,$a,3,false),'passed decision cannot be reversed by another response');
$count=(int)$pdo->query('SELECT COUNT(*) FROM ballot_revisions')->fetchColumn();
try{Elections::submit($pdo,$current,1,$ids);throw new RuntimeException('Stale ballot accepted');}catch(CandidatesChanged $error){check($error->candidateIds===array_values(array_diff($ids,[$a])),'stale ballot returns remaining candidates');}
check((int)$pdo->query('SELECT COUNT(*) FROM ballot_revisions')->fetchColumn()===$count,'stale submission creates no revision');
$remaining=Elections::candidateIds($pdo,$current);Elections::submit($pdo,$current,1,array_reverse($remaining));
check((int)$pdo->query('SELECT COUNT(*) FROM ballot_revisions')->fetchColumn()===$count+1,'reviewed remaining ranking can be submitted');
rejects(fn()=>Elections::submit($pdo,$current,1,array_merge($remaining,[999])),'unknown candidate is not treated as a removal');
$pdo->exec('UPDATE users SET is_active=0 WHERE id=4');
rejects(fn()=>Removals::vote($pdo,$b,4,true,'No'),'inactive members cannot vote');
$pdo->exec('UPDATE users SET is_active=1 WHERE id=4');
$secret=Movies::nominate($pdo,1,['title'=>'Hidden removal sentinel','summary'=>'Private synopsis','mystery'=>'1','alias'=>'Public mystery','pitch'=>'Public pitch']);
Removals::vote($pdo,$secret,1,true,'Public reason');
check(!str_contains(json_encode(Removals::listing($pdo,2)),'Hidden removal sentinel'),'removal list filters mystery metadata');
Removals::vote($pdo,$secret,2,true);Removals::vote($pdo,$secret,3,true);
check($pdo->query('SELECT revealed_at FROM movies WHERE id='.$secret)->fetchColumn()===null,'removal does not reveal mystery');
check(Elections::candidateIds($pdo,$current)===$remaining,'off-ballot removal does not alter current candidates');
$pdo->exec("UPDATE movies SET status='watched' WHERE id=".$b);
Removals::vote($pdo,$b,1,true,'Already watched');Removals::vote($pdo,$b,2,true);Removals::vote($pdo,$b,3,true);
check(!in_array($b,Elections::candidateIds($pdo,$current),true),'watched current candidate can be removed by majority');
Elections::close($pdo,$current);$closedAfter=Elections::finalResult($pdo,$current);
$next=Elections::open($pdo,'Future election');
check(!array_intersect([$a,$b,$secret],Elections::candidateIds($pdo,$next)),'future election excludes removed films');
foreach(Elections::candidateIds($pdo,$next) as $id) {Removals::vote($pdo,$id,1,true,'Remove all test');Removals::vote($pdo,$id,2,true);Removals::vote($pdo,$id,3,true);}
check(Elections::currentResult($pdo,$next)===['winner'=>null,'rounds'=>[]],'all candidates removed produces no winner');
check(Elections::finalResult($pdo,$current)===$closedAfter,'later removals cannot rewrite earlier closed results');
Elections::close($pdo,$next);check(Elections::finalResult($pdo,$next)['winner']===null,'empty election can close without inventing winner');
// Membership changes affect pending counts, never completed decisions.
$pdo->exec("INSERT INTO users(display_name) VALUES('Fifth member')");
check(Removals::threshold($pdo)===3,'five active members still need three');
$pdo->exec("INSERT INTO users(display_name) VALUES('Sixth member')");
check(Removals::threshold($pdo)===4,'six active members need four');
$new=Movies::nominate($pdo,1,['title'=>'Pending member test']);
Removals::vote($pdo,$new,1,true,'Membership test');Removals::vote($pdo,$new,2,true);Removals::vote($pdo,$new,3,true);
$pdo->exec('UPDATE users SET is_active=0 WHERE id=3');
$pending=array_values(array_filter(Removals::listing($pdo,1),static fn(array $row):bool=>$row['movie']['id']===$new))[0];
check($pending['support']===2 && $pending['passed_at']===null,'inactive endorsements excluded from pending support');
Removals::vote($pdo,$new,4,true);
check($pdo->query('SELECT passed_at FROM removal_requests WHERE movie_id='.$new)->fetchColumn()!==null,'next response evaluates current active membership');
// A removal cannot cross an election writer lock; both use BEGIN IMMEDIATE.
$file=tempnam(sys_get_temp_dir(),'lgfc-removal-');
try {
 $one=new PDO('sqlite:'.$file);$two=new PDO('sqlite:'.$file);
 foreach([$one,$two] as $db){$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('PRAGMA busy_timeout=1');}
 $one->exec(file_get_contents(__DIR__.'/../db/schema.sql'));$one->exec(file_get_contents(__DIR__.'/../db/seed.sql'));
 $first=Elections::candidateIds($one,1)[0];
 $one->exec('BEGIN IMMEDIATE');
 try{Removals::vote($two,$first,1,true,'Concurrent request');throw new RuntimeException('Writer lock crossed');}
 catch(PDOException $error){check(str_contains($error->getMessage(),'locked'),'removal shares serialized writer boundary with elections');}
 $one->exec('ROLLBACK');
 check((int)$two->query('SELECT COUNT(*) FROM removal_requests')->fetchColumn()===0,'blocked removal leaves no partial request');
} finally {$db=null;$error=null;$one=null;$two=null;unlink($file);}
