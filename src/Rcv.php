<?php
declare(strict_types=1);

namespace LGFC;

final class Rcv
{
    /**
     * @param array<int, array<int, int>> $ballots ranked candidate IDs per voter
     * @param array<int, int> $candidateIds
     * @return array{winner:?int, rounds:array<int,array<string,mixed>>}
     */
    public static function calculate(array $ballots, array $candidateIds): array
    {
        $active = array_values(array_unique(array_map('intval', $candidateIds)));
        sort($active, SORT_NUMERIC);
        $rounds = [];

        if ($active === []) {
            return ['winner' => null, 'rounds' => []];
        }

        while (count($active) > 1) {
            $counts = array_fill_keys($active, 0);
            $exhausted = 0;

            foreach ($ballots as $ballot) {
                $choice = null;
                foreach ($ballot as $candidateId) {
                    $candidateId = (int) $candidateId;
                    if (in_array($candidateId, $active, true)) {
                        $choice = $candidateId;
                        break;
                    }
                }

                if ($choice === null) {
                    $exhausted++;
                } else {
                    $counts[$choice]++;
                }
            }

            $continuingVotes = array_sum($counts);
            $majority = intdiv($continuingVotes, 2) + 1;

            foreach ($counts as $candidateId => $count) {
                if ($count >= $majority && $continuingVotes > 0) {
                    $rounds[] = [
                        'counts' => $counts,
                        'exhausted' => $exhausted,
                        'majority' => $majority,
                        'eliminated' => null,
                        'winner' => (int) $candidateId,
                    ];
                    return ['winner' => (int) $candidateId, 'rounds' => $rounds];
                }
            }

            $minimum = min($counts);
            $tiedForLast = array_keys(array_filter($counts, static fn (int $count): bool => $count === $minimum));
            sort($tiedForLast, SORT_NUMERIC);
            $eliminated = (int) $tiedForLast[0];

            $rounds[] = [
                'counts' => $counts,
                'exhausted' => $exhausted,
                'majority' => $majority,
                'eliminated' => $eliminated,
                'winner' => null,
            ];

            $active = array_values(array_filter(
                $active,
                static fn (int $candidateId): bool => $candidateId !== $eliminated
            ));
        }

        return ['winner' => $active[0] ?? null, 'rounds' => $rounds];
    }
}
