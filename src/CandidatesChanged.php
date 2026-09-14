<?php
declare(strict_types=1);
namespace LGFC;

final class CandidatesChanged extends \DomainException
{
    public function __construct(public readonly array $candidateIds)
    {
        parent::__construct('A film was removed while you were voting. Please check your ranking and submit again.');
    }
}
