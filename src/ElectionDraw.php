<?php
declare(strict_types=1);
namespace LGFC;
use PDO;
use DomainException;
use InvalidArgumentException;

final class ElectionDraw
{
    public static function migrate(PDO $pdo): void {
        $pdo->exec(file_get_contents(dirname(__DIR__).'/db/election-draws.sql'));
    }

    /** One eligibility definition for both the displayed pool and the authoritative draw. */
    public static function pool(PDO $pdo): array {
        return $pdo->query("SELECT m.* FROM movies m LEFT JOIN
            (SELECT movie_id FROM movie_night_announcements ORDER BY id DESC LIMIT 1) a
            ON a.movie_id=m.id WHERE m.status='active' AND a.movie_id IS NULL ORDER BY m.id")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Count consecutive misses among recorded eligible, non-cancelled elections only. */
    public static function skips(PDO $pdo): array {
        $rows=$pdo->query("SELECT d.movie_id,d.selection FROM election_draw_movies d
            WHERE NOT EXISTS (SELECT 1 FROM election_cancellations c WHERE c.election_id=d.election_id)
            ORDER BY d.election_id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $counts=[]; $done=[];
        foreach($rows as $row) {
            $id=(int)$row['movie_id'];
            if(isset($done[$id])) continue;
            if($row['selection']!=='skipped') { $done[$id]=true; continue; }
            $counts[$id]=($counts[$id]??0)+1;
        }
        return $counts;
    }

    /** Pure selection policy; injectable integer source allows deterministic edge-case tests. */
    public static function choose(array $ids,array $skips,int $size,?int $guaranteed=null,?callable $random=null): array {
        if(!in_array($size,[5,8],true)) throw new InvalidArgumentException('Choose 5 or 8 films.');
        if(!$ids) throw new DomainException('No eligible films are available.');
        if($guaranteed!==null && !in_array($guaranteed,$ids,true)) throw new DomainException('The guaranteed film is no longer eligible. Reload and choose again.');
        $random ??= static fn(int $max): int => random_int(0,$max);
        $selected=[];
        if($guaranteed!==null) $selected[$guaranteed]='guaranteed';
        foreach(['priority','random'] as $tier) {
            $bag=array_values(array_filter($ids,static fn(int $id):bool =>
                !isset($selected[$id]) && (($skips[$id]??0)>=3)===($tier==='priority')));
            while($bag && count($selected)<$size) {
                $index=$random(count($bag)-1);
                if(!is_int($index) || $index<0 || $index>=count($bag)) throw new \RuntimeException('Invalid random selection.');
                $id=$bag[$index]; array_splice($bag,$index,1);
                $selected[$id]=$tier;
            }
        }
        return $selected;
    }

    /** Caller holds the election-opening transaction. Persist the whole pool, not just winners. */
    public static function record(PDO $pdo,int $election,int $size,array $ids,array $skips,array $selected): void {
        $stmt=$pdo->prepare('INSERT INTO election_draws(election_id,requested_size) VALUES(?,?)');
        $stmt->execute([$election,$size]);
        $stmt=$pdo->prepare('INSERT INTO election_draw_movies(election_id,movie_id,skipped_before,selection) VALUES(?,?,?,?)');
        foreach($ids as $id) $stmt->execute([$election,$id,$skips[$id]??0,$selected[$id]??'skipped']);
    }
}
