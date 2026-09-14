<?php
declare(strict_types=1);
namespace LGFC;

use PDO;
use InvalidArgumentException;

final class BallotHistory
{
    /** Read one consistent snapshot; recalculations use this election's remaining candidates. */
    public static function snapshot(PDO $pdo, int $electionId): array
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM elections WHERE id = ?');
            $stmt->execute([$electionId]);
            $election = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$election) throw new InvalidArgumentException('Election not found.');
            $candidates = Elections::candidateIds($pdo, $electionId);
            $stmt = $pdo->prepare('SELECT m.* FROM movies m JOIN election_movies em ON em.movie_id=m.id WHERE em.election_id=? ORDER BY m.id');
            $stmt->execute([$electionId]);
            $movies = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $movie) {
                $public = Movies::publicView($movie);
                $movies[$public['id']] = $public['title'];
            }
            $final = null;
            $frozenIds = null;
            if ($election['status'] === 'closed') {
                $final = Elections::finalResult($pdo, $electionId);
                $stmt = $pdo->prepare('SELECT ballot_revision_ids_json FROM election_results WHERE election_id=?');
                $stmt->execute([$electionId]);
                $frozenIds = json_decode($stmt->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
            }
            $stmt = $pdo->prepare('SELECT br.id, br.user_id, br.created_at, u.display_name, c.movie_id
                FROM ballot_revisions br JOIN users u ON u.id=br.user_id
                LEFT JOIN ballot_revision_choices c ON c.ballot_revision_id=br.id
                WHERE br.election_id=? ORDER BY br.id, c.rank');
            $stmt->execute([$electionId]);
            $revisions = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $id = (int) $row['id'];
                $revisions[$id] ??= ['id' => $id, 'user_id' => (int) $row['user_id'],
                    'name' => $row['display_name'], 'created_at' => $row['created_at'], 'ranking' => []];
                if ($row['movie_id'] !== null) $revisions[$id]['ranking'][] = (int) $row['movie_id'];
            }
            // Closed history ends at each voter's exact frozen revision, not today's latest row.
            $cutoffs = [];
            foreach ($frozenIds ?? [] as $id) {
                if (isset($revisions[$id])) $cutoffs[$revisions[$id]['user_id']] = (int) $id;
            }
            $ballots = $versions = $steps = [];
            foreach ($revisions as $revision) {
                $user = $revision['user_id'];
                if ($frozenIds !== null && $revision['id'] > ($cutoffs[$user] ?? 0)) continue;
                $versions[$user] = ($versions[$user] ?? 0) + 1;
                $ballots[$user] = $revision['ranking'];
                $steps[] = $revision + ['version' => $versions[$user], 'voters' => count($ballots),
                    'result' => Rcv::calculate(array_values($ballots), $candidates)];
            }
            $pdo->commit();
            return ['election' => $election, 'movies' => $movies, 'candidates' => $candidates,
                'removed' => array_values(array_diff(array_keys($movies), $candidates)),
                'steps' => $steps, 'final' => $final];
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }
}
