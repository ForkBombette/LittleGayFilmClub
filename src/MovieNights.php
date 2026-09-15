<?php
declare(strict_types=1);
namespace LGFC;
use PDO;
use DomainException;
use InvalidArgumentException;

final class MovieNights
{
    public static function migrate(PDO $pdo): void { $pdo->exec(file_get_contents(dirname(__DIR__).'/db/movie-nights.sql')); }
    public static function latestId(PDO $pdo): int { return (int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM movie_night_announcements')->fetchColumn(); }
    public static function openId(PDO $pdo): int { return (int)$pdo->query("SELECT id FROM elections WHERE status='open' ORDER BY id DESC LIMIT 1")->fetchColumn(); }
    public static function cancelled(PDO $pdo,int $id): bool {
        $stmt=$pdo->prepare('SELECT 1 FROM election_cancellations WHERE election_id=?');$stmt->execute([$id]);return (bool)$stmt->fetchColumn();
    }
    public static function current(PDO $pdo): ?array {
        $row=$pdo->query('SELECT * FROM movie_night_announcements ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if(!$row || $row['source']==='cleared') return null;
        $stmt=$pdo->prepare('SELECT * FROM movies WHERE id=?');$stmt->execute([$row['movie_id']]);
        $row['movie']=Movies::publicView($stmt->fetch(PDO::FETCH_ASSOC));
        return $row;
    }
    private static function date(string $date): string {
        $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        $today=(new \DateTimeImmutable('now',new \DateTimeZone('Europe/London')))->format('Y-m-d');
        if(!$parsed || $parsed->format('Y-m-d')!==$date || $date<$today) throw new InvalidArgumentException('Choose a valid movie-night date, today or later.');
        return $date;
    }
    private static function write(PDO $pdo,int $actor,int $expectedPlan,int $expectedOpen,callable $operation): int {
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            Auth::requireOrganiser($pdo,$actor);
            if(self::latestId($pdo)!==$expectedPlan || self::openId($pdo)!==$expectedOpen) throw new DomainException('The announcement or open election changed. Reload and review before saving.');
            $id=$operation();$pdo->exec('COMMIT');return $id;
        } catch(\Throwable $error){$pdo->exec('ROLLBACK');throw $error;}
    }
    private static function insert(PDO $pdo,int $actor,?int $movie,?string $date,string $source,?int $election): int {
        $stmt=$pdo->prepare('INSERT INTO movie_night_announcements(movie_id,scheduled_on,source,election_id,organised_by) VALUES(?,?,?,?,?)');
        $stmt->execute([$movie,$date,$source,$election,$actor]);return (int)$pdo->lastInsertId();
    }
    public static function announce(PDO $pdo,int $actor,string $mode,int $choice,string $date,int $expectedPlan,int $expectedOpen): int {
        $date=self::date($date);
        if(!in_array($mode,['election','direct'],true)) throw new InvalidArgumentException('Choose how the film is selected.');
        return self::write($pdo,$actor,$expectedPlan,$expectedOpen,static function()use($pdo,$actor,$mode,$choice,$date,$expectedOpen):int{
            $election=null;
            if($mode==='election') {
                if($expectedOpen && $expectedOpen!==$choice) throw new DomainException('An election is still open. Announce its winner or choose a film directly to cancel it.');
                if(self::cancelled($pdo,$choice)) throw new DomainException('A cancelled election cannot select the next film.');
                Elections::closeInTransaction($pdo,$choice);
                $movie=Elections::finalResult($pdo,$choice)['winner'];
                if($movie===null) throw new DomainException('This election has no winner. Choose a film directly, or collect ballots first.');
                $election=$choice;
            } else {
                $movie=$choice;
            }
            $stmt=$pdo->prepare('SELECT id FROM movies WHERE id=?');$stmt->execute([$movie]);
            if(!$stmt->fetchColumn()) throw new InvalidArgumentException('Choose an existing catalogue film. Add its details under Films first if needed.');
            $announcement=self::insert($pdo,$actor,$movie,$date,$mode,$election);
            if($mode==='direct' && $expectedOpen) {
                Elections::closeInTransaction($pdo,$expectedOpen);
                $stmt=$pdo->prepare('INSERT INTO election_cancellations(election_id,announcement_id) VALUES(?,?)');$stmt->execute([$expectedOpen,$announcement]);
            }
            return $announcement;
        });
    }
    public static function reschedule(PDO $pdo,int $actor,string $date,int $expectedPlan,int $expectedOpen): int {
        $date=self::date($date);
        return self::write($pdo,$actor,$expectedPlan,$expectedOpen,static function()use($pdo,$actor,$date):int{
            $current=self::current($pdo);
            if(!$current) throw new DomainException('There is no announcement to reschedule.');
            return self::insert($pdo,$actor,(int)$current['movie_id'],$date,$current['source'],$current['election_id']===null?null:(int)$current['election_id']);
        });
    }
    public static function clear(PDO $pdo,int $actor,int $expectedPlan,int $expectedOpen): int {
        return self::write($pdo,$actor,$expectedPlan,$expectedOpen,static fn():int=>self::insert($pdo,$actor,null,null,'cleared',null));
    }
}
