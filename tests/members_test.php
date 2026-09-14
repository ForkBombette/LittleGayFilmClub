<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/bootstrap.php';
use LGFC\Members;
use LGFC\Auth;
use LGFC\Elections;
use LGFC\Movies;
use LGFC\Removals;
function check(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);echo "PASS - {$label}\n";}
function rejects(callable $fn,string $label):void {try{$fn();}catch(DomainException $error){check(true,$label);return;}throw new RuntimeException($label);}
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$pdo->exec(file_get_contents(__DIR__.'/../db/schema.sql'));$pdo->exec(file_get_contents(__DIR__.'/../db/seed.sql'));$pdo->exec("UPDATE users SET role='organiser' WHERE id=1");
function member(PDO $pdo,int $id):array {$stmt=$pdo->prepare('SELECT id,display_name,role,is_active FROM users WHERE id=?');$stmt->execute([$id]);return $stmt->fetch();}
function save(PDO $pdo,int $actor,int $id,string $name,string $role,bool $active):void {Members::update($pdo,$actor,$id,$name,$role,$active,Members::version(member($pdo,$id)));}
rejects(fn()=>Members::create($pdo,2,'Unauthorized'),'members cannot create accounts');
rejects(fn()=>save($pdo,2,2,'Nicola','organiser',true),'members cannot promote themselves');
rejects(fn()=>Members::create($pdo,1,'  '),'blank names rejected');
rejects(fn()=>Members::create($pdo,1,str_repeat('x',101)),'overlong names rejected');
rejects(fn()=>Members::create($pdo,1,"Line\nBreak"),'control characters rejected');
rejects(fn()=>Members::create($pdo,1,'sOPHIE'),'case-variant duplicates rejected');
$id=Members::create($pdo,1,'  New member  ');$new=member($pdo,$id);
check($new['display_name']==='New member' && $new['role']==='member' && (int)$new['is_active']===1,'new members have trimmed name and least-privilege defaults');
$link=Auth::issue($pdo,1,$id);$session=Auth::exchange($pdo,$link);$unused=Auth::issue($pdo,1,$id);
Elections::submit($pdo,1,$id,Elections::candidateIds($pdo,1));$film=Movies::nominate($pdo,$id,['title'=>'Member-owned film']);Removals::vote($pdo,$film,$id,true,'Member request');
$oldVersion=Members::version($new);
save($pdo,1,$id,'Renamed member','member',true);
check(Auth::user($pdo,$session)['display_name']==='Renamed member','name change reaches an existing login');
rejects(fn()=>Members::update($pdo,1,$id,'Overwrite','organiser',true,$oldVersion),'stale form cannot overwrite newer member details');
rejects(fn()=>save($pdo,1,$id,'Sophie','member',true),'rename cannot take another account name');
rejects(fn()=>save($pdo,1,$id,'Renamed member','admin',true),'unknown role rejected');
rejects(fn()=>Members::update($pdo,1,999,'Missing','member',true,''),'unknown target rejected');
$votes=$pdo->query('SELECT * FROM ballot_revisions')->fetchAll();$choices=$pdo->query('SELECT * FROM ballot_revision_choices')->fetchAll();
save($pdo,1,$id,'Renamed member','member',false);
check(Auth::user($pdo,$session)===null,'deactivation immediately invalidates login');
rejects(fn()=>Auth::exchange($pdo,$unused),'deactivation cancels unused links');
rejects(fn()=>Auth::issue($pdo,1,$id),'inactive member cannot receive a login link');
check($votes===$pdo->query('SELECT * FROM ballot_revisions')->fetchAll() && $choices===$pdo->query('SELECT * FROM ballot_revision_choices')->fetchAll(),'deactivation preserves ballots and revisions');
check((int)$pdo->query('SELECT nominator_id FROM movies WHERE id='.$film)->fetchColumn()===$id,'deactivation preserves nomination ownership');
check(Removals::listing($pdo,1)[0]['support']===0,'inactive member no longer counts toward pending removal support');
rejects(fn()=>Members::create($pdo,1,'Renamed member'),'inactive names cannot create duplicate identities');
save($pdo,1,$id,'Renamed member','member',true);
check(Auth::user($pdo,$session)===null,'reactivation cannot resurrect revoked devices');
$fresh=Auth::exchange($pdo,Auth::issue($pdo,1,$id));check(Auth::user($pdo,$fresh)['id']===$id,'fresh link restores the same member identity');
rejects(fn()=>save($pdo,1,1,'Sophie','member',true),'last active organiser cannot be demoted');
rejects(fn()=>save($pdo,1,1,'Sophie','organiser',false),'last active organiser cannot be deactivated');
save($pdo,1,$id,'Renamed member','organiser',false);
rejects(fn()=>save($pdo,1,1,'Sophie','member',true),'inactive organiser does not satisfy last-organiser safeguard');
save($pdo,1,$id,'Renamed member','organiser',true);
$fresh=Auth::exchange($pdo,Auth::issue($pdo,1,$id));check(Auth::user($pdo,$fresh)['role']==='organiser','promotion grants organiser permission');
save($pdo,1,1,'Sophie','member',true);
rejects(fn()=>Members::create($pdo,1,'No longer allowed'),'demoted actor loses write permission immediately');
rejects(fn()=>save($pdo,$id,$id,'Renamed member','member',true),'successive changes cannot remove the remaining organiser');
save($pdo,$id,1,'Sophie','organiser',true);
save($pdo,1,$id,'Renamed member','member',true);
check(Auth::user($pdo,$fresh)['role']==='member','demotion updates role on remembered device');
