<?php
declare(strict_types=1);
require_once __DIR__.'/../src/bootstrap.php';
use LGFC\ElectionDraw;
use LGFC\Elections;
use LGFC\Movies;
function check(bool $ok,string $label):void { if(!$ok) throw new RuntimeException($label); echo "PASS - $label\n"; }
function rejects(callable $fn,string $label):void { try {$fn();} catch(DomainException|InvalidArgumentException $e){check(true,$label);return;} throw new RuntimeException($label); }
$p=new PDO('sqlite::memory:');$p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$p->exec(file_get_contents(__DIR__.'/../db/schema.sql'));$p->exec(file_get_contents(__DIR__.'/../db/seed.sql'));
$p->exec('DROP TABLE election_draw_movies; DROP TABLE election_draws');
ElectionDraw::migrate($p);ElectionDraw::migrate($p);
check($p->query('SELECT COUNT(*) FROM election_draws')->fetchColumn()==0,'migration does not invent historical selection methods');
$first=static fn(int $max):int=>0;$last=static fn(int $max):int=>$max;
$ids=range(1,12);
$draw=ElectionDraw::choose($ids,[9=>3,10=>5],5,12,$first);
check(count($draw)===5 && $draw[12]==='guaranteed' && $draw[9]==='priority' && $draw[10]==='priority','guarantee uses one place and overdue candidates take precedence');
check(count(ElectionDraw::choose($ids,[],8,null,$last))===8,'eight distinct films are selected');
check(count(ElectionDraw::choose([1,2],[],5,1,$first))===2,'small pool includes every eligible film');
$overdue=array_fill_keys($ids,3);
$a=ElectionDraw::choose($ids,$overdue,5,null,$first);$b=ElectionDraw::choose($ids,$overdue,5,null,$last);
check(count($a)===5 && count(array_filter($a,fn($v)=>$v==='priority'))===5 && array_keys($a)!==array_keys($b),'overfull priority tier is randomly sampled');
rejects(fn()=>ElectionDraw::choose($ids,[],6),'unsupported election size rejected');
rejects(fn()=>ElectionDraw::choose($ids,[],5,99),'ineligible guarantee rejected');
rejects(fn()=>ElectionDraw::choose([],[],5),'empty pool rejected');
$old=Elections::candidateIds($p,1);
$p->exec("INSERT INTO movie_night_announcements(movie_id,scheduled_on,source,organised_by) VALUES(1,'2099-01-01','direct',1)");
check(!in_array(1,array_column(ElectionDraw::pool($p),'id')),'latest scheduled film excluded from pool');
check(!in_array(1,array_column(Movies::browseLists($p,0)['ineligible'],'id')),'scheduled film excluded from not-in-election shelf');
check(Elections::candidateIds($p,1)===$old,'schedule does not change existing candidate snapshots');
$p->exec("INSERT INTO movie_night_announcements(movie_id,source,organised_by) VALUES(NULL,'cleared',1)");
check(in_array(1,array_column(ElectionDraw::pool($p),'id')) && in_array(1,array_column(Movies::browseLists($p,0)['ineligible'],'id')),'clearing restores active film to pool and shelf');
$p->exec("INSERT INTO movie_night_announcements(movie_id,source,organised_by) VALUES(2,'direct',1); UPDATE movies SET status='watched' WHERE id=1");
check(!in_array(1,array_column(ElectionDraw::pool($p),'id')) && !in_array(2,array_column(ElectionDraw::pool($p),'id')),'watched and newest scheduled films stay excluded');
Elections::close($p,1);
for($i=0;$i<8;$i++) Movies::nominate($p,1,['title'=>'Extra '.$i]);
$before=$p->query('SELECT COUNT(*) FROM elections')->fetchColumn();
rejects(fn()=>Elections::open($p,'Bad guarantee',5,2),'opening rechecks scheduled guarantee');
check($p->query('SELECT COUNT(*) FROM elections')->fetchColumn()===$before,'failed draw rolls back without creating election');
$e=Elections::open($p,'Random election',5,3);
check(count(Elections::candidateIds($p,$e))===5 && in_array(3,Elections::candidateIds($p,$e)),'opening stores selected five and guaranteed film');
$rows=$p->query("SELECT * FROM election_draw_movies WHERE election_id=$e")->fetchAll();
check(count($rows)===count(ElectionDraw::pool($p)),'whole eligible pool recorded');
check(count(array_filter($rows,fn($r)=>$r['selection']==='guaranteed' && $r['movie_id']==3))===1,'guaranteed provenance persists');
$skipped=array_values(array_filter($rows,fn($r)=>$r['selection']==='skipped'))[0]['movie_id'];
check((ElectionDraw::skips($p)[$skipped]??0)===1,'eligible missed film accrues a skip');
Elections::close($p,$e);
// Recorded subsequent eligible elections; absence from a pool does not count as a skip.
for($i=0;$i<2;$i++){
 $p->exec("INSERT INTO elections(name,status) VALUES('Recorded draw','closed')");$n=(int)$p->lastInsertId();
 ElectionDraw::record($p,$n,5,[$skipped],[],[]);
}
check(ElectionDraw::skips($p)[$skipped]===3,'three eligible misses qualify for priority');
$p->exec("INSERT INTO elections(name,status) VALUES('Cancelled draw','closed')");$n=(int)$p->lastInsertId();
ElectionDraw::record($p,$n,5,[$skipped],[],[$skipped=>'random']);
$p->exec("INSERT INTO election_cancellations(election_id,announcement_id) VALUES($n,1)");
check(ElectionDraw::skips($p)[$skipped]===3,'cancelled draw neither increments nor resets skips');
$p->exec("INSERT INTO elections(name,status) VALUES('Selected draw','closed')");$n=(int)$p->lastInsertId();
ElectionDraw::record($p,$n,5,[$skipped],[],[$skipped=>'priority']);
check((ElectionDraw::skips($p)[$skipped]??0)===0,'selection resets consecutive skips');
