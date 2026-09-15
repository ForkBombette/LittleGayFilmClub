<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
use LGFC\{Comments, Elections, FilmUi, Movies};
function check(bool $ok,string $label):void { if(!$ok) throw new RuntimeException($label); echo "PASS - $label\n"; }
function rejects(callable $fn,string $class,string $label):void {try{$fn();}catch(Throwable $e){check($e instanceof $class,$label);return;}throw new RuntimeException($label);}
$p=new PDO('sqlite::memory:');$p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$p->exec(file_get_contents(__DIR__.'/../db/schema.sql'));$p->exec(file_get_contents(__DIR__.'/../db/seed.sql'));
$p->exec('DROP TABLE movie_comments'); // Simulate an existing installation before this feature.
Comments::migrate($p);Comments::migrate($p);
check((int)$p->query('SELECT COUNT(*) FROM movies')->fetchColumn()===6,'upgrade preserves existing films');
check(Comments::listing($p,1)===[],'migration is repeatable and starts empty');
Comments::write($p,1,1,'An excellent proposal.','');$first=Comments::listing($p,1)[0];
check($first['body']==='An excellent proposal.' && $first['user_id']===1,'first comment is attached to film and author');
rejects(fn()=>Comments::write($p,1,1,'Duplicate',''),DomainException::class,'simultaneous first comment rejected');
Comments::write($p,1,2,'I respectfully object.','');
check(count(Comments::listing($p,1))===2,'different members each get one comment');
Comments::write($p,1,1,'Actually, an outstanding proposal.',$first['version']);$updated=Comments::listing($p,1)[0];
check(count(Comments::listing($p,1))===2 && $updated['version']!==$first['version'] && $updated['created_at']===$first['created_at'],'edit replaces own text and changes version without duplicating');
rejects(fn()=>Comments::write($p,1,1,'Stale',$first['version']),DomainException::class,'stale edit rejected');
rejects(fn()=>Comments::write($p,1,1,'',$first['version'],true),DomainException::class,'stale delete rejected');
rejects(fn()=>Comments::write($p,1,2,'',$updated['version'],true),DomainException::class,'another member cannot delete author comment using its version');
rejects(fn()=>Comments::write($p,1,3,'   ',''),InvalidArgumentException::class,'blank comments rejected');
rejects(fn()=>Comments::write($p,1,3,str_repeat('x',2001),''),InvalidArgumentException::class,'overlong comments rejected');
Comments::write($p,1,3,str_repeat('♥',2000),'');check(count(Comments::listing($p,1))===3,'Unicode character limit accepts 2000 multibyte characters');
rejects(fn()=>Comments::write($p,999,1,'Missing',''),InvalidArgumentException::class,'unknown film rejected');
$p->exec('UPDATE users SET is_active=0 WHERE id=3');
rejects(fn()=>Comments::write($p,2,3,'Inactive',''),InvalidArgumentException::class,'inactive author cannot write');
check(count(Comments::listing($p,1))===3,'deactivation preserves existing discussion');
Comments::write($p,1,1,'',$updated['version'],true);check(count(Comments::listing($p,1))===2,'author deletes own comment only');
Comments::write($p,1,1,'Back again.','');
rejects(fn()=>Comments::write($p,1,1,'Old draft',$updated['version']),DomainException::class,'delete and recreate cannot revive an old version');
$before=Comments::listing($p,1);
Elections::submit($p,1,1,Elections::candidateIds($p,1));Elections::close($p,1);
$p->exec("UPDATE movies SET status='watched' WHERE id=1");$next=Elections::open($p,'Next night');
check(Comments::listing($p,1)===$before,'watching and a new election preserve the discussion');
$p->exec("UPDATE movies SET status='removed' WHERE id=1");$own=array_values(array_filter($before,fn($c)=>$c['user_id']===1))[0];
Comments::write($p,1,1,'Still worth discussing.',$own['version']);
check(count(Comments::listing($p,1))===3,'removed films retain editable discussion');
check(Elections::finalResult($p,1)['winner']===1 && Elections::ballots($p,$next)===[],'comments do not affect frozen results or new ballots');
$p->exec("UPDATE movies SET mystery_alias='The secret option',title='PRIVATE TITLE',release_year=1986 WHERE id=6");
$stmt=$p->query('SELECT * FROM movies WHERE id=6');$public=Movies::publicView($stmt->fetch());
check(FilmUi::label($public)==='The secret option','mystery labels hide the year');
check(FilmUi::label(['title'=>'A film','release_year'=>1984])==='A film (1984)','known years appear in shared labels');
check(!str_contains(FilmUi::button(1,'<script>'),'<script>'),'film buttons escape titles');
