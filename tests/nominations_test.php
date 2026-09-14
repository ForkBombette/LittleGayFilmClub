<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/bootstrap.php';
use LGFC\Movies;
use LGFC\Elections;
function check(bool $ok,string $label): void { if (!$ok) throw new RuntimeException($label); echo "PASS - {$label}\n"; }
function rejects(callable $fn,string $label): void { try {$fn();} catch (InvalidArgumentException $e) {check(true,$label);return;} throw new RuntimeException($label); }
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$pdo->exec(file_get_contents(__DIR__.'/../db/schema.sql'));$pdo->exec(file_get_contents(__DIR__.'/../db/seed.sql'));
$id=Movies::nominate($pdo,1,['title'=>'Private sentinel','mystery'=>'1','alias'=>'Mystery','pitch'=>'Original pitch']);
$record=Movies::editable($pdo,$id,1);
$input=['title'=>'Private correction','year'=>'1980','image_url'=>'https://example.com/poster.jpg','summary'=>'Private synopsis','alias'=>'New alias','pitch'=>'Improved pitch','version'=>Movies::editVersion($record)];
$pdo->exec("UPDATE users SET role='organiser' WHERE id=2");
rejects(fn()=>Movies::editable($pdo,$id,2),'organiser cannot read another member private editor');
rejects(fn()=>Movies::edit($pdo,$id,2,$input),'organiser cannot edit another member nomination');
rejects(fn()=>Movies::editable($pdo,999,1),'unknown nomination denied');
$pdo->exec('UPDATE users SET is_active=0 WHERE id=1');
rejects(fn()=>Movies::edit($pdo,$id,1,$input),'inactive owner cannot edit');
$pdo->exec('UPDATE users SET is_active=1 WHERE id=1');
// Snapshot and submit this film, then freeze the election before editing metadata.
$pdo->exec('INSERT INTO election_movies(election_id,movie_id) VALUES(1,'.$id.')');
$pdo->exec('INSERT INTO ballot_revisions(election_id,user_id) VALUES(1,1)');
$revision=(int)$pdo->lastInsertId();$rank=1;
foreach(Elections::candidateIds($pdo,1) as $candidate) $pdo->exec('INSERT INTO ballot_revision_choices VALUES('.$revision.','.$candidate.','.$rank++.')');
Elections::close($pdo,1);
function retained(PDO $pdo): string { $data=[];foreach(['election_movies','election_results','ballot_revisions','ballot_revision_choices'] as $table) $data[$table]=$pdo->query('SELECT * FROM '.$table)->fetchAll();return json_encode($data); }
$before=retained($pdo);
Movies::edit($pdo,$id,1,$input+['mystery'=>'','revealed_at'=>'now','status'=>'removed','nominator_id'=>2]);
$updated=Movies::editable($pdo,$id,1);$public=Movies::publicView($updated);
check($updated['title']==='Private correction' && $updated['nomination_pitch']==='Improved pitch','owner can correct details and pitch');
check($public['title']==='New alias' && $public['is_mystery'] && $public['summary']===null,'editing hidden mystery does not reveal private details');
check($updated['status']==='active' && (int)$updated['nominator_id']===1,'forged lifecycle and owner fields ignored');
check(retained($pdo)===$before,'editing preserves eligibility, revisions and frozen result');
rejects(fn()=>Movies::edit($pdo,$id,1,$input),'stale edit cannot overwrite newer changes');
$input['version']=Movies::editVersion($updated);
$invalid=$input;$invalid['image_url']='javascript:alert(1)';
rejects(fn()=>Movies::edit($pdo,$id,1,$invalid),'unsafe poster rejected on edit');
$invalid=$input;$invalid['alias']='';
rejects(fn()=>Movies::edit($pdo,$id,1,$invalid),'hidden mystery still requires an alias');
check(Movies::editable($pdo,$id,1)===$updated,'failed edits are atomic');
Movies::reveal($pdo,$id,1);
rejects(fn()=>Movies::edit($pdo,$id,1,$input),'reveal invalidates an already-open editor');
$revealed=Movies::editable($pdo,$id,1);$input['version']=Movies::editVersion($revealed);
Movies::edit($pdo,$id,1,$input+['mystery'=>'1','revealed_at'=>null]);
check(Movies::editable($pdo,$id,1)['revealed_at']===$revealed['revealed_at'],'editing cannot re-hide a revealed film');
$plain=Movies::nominate($pdo,1,['title'=>'Public film']);$record=Movies::editable($pdo,$plain,1);
Movies::edit($pdo,$plain,1,['title'=>'Public correction','mystery'=>'1','alias'=>'Hide me','pitch'=>'Pitch','version'=>Movies::editVersion($record)]);
check(!Movies::publicView(Movies::editable($pdo,$plain,1))['is_mystery'],'public nomination cannot become hidden through edit');
$pdo->exec("UPDATE movies SET status='watched' WHERE id=".$plain);$record=Movies::editable($pdo,$plain,1);
Movies::edit($pdo,$plain,1,['title'=>'Public correction','pitch'=>'Watched pitch','version'=>Movies::editVersion($record)]);
check(Movies::editable($pdo,$plain,1)['status']==='watched','watched metadata edits retain watched status');
