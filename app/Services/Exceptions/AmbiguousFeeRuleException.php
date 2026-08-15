<?php

namespace App\Services\Exceptions;

use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * More than one fee rule matched the same resolution criteria. This is a
 * configuration error to be surfaced to an administrator — never resolved by
 * silently picking one (PRD FR-CF-02 acceptance criteria).
 */
class AmbiguousFeeRuleException extends RuntimeException
{
    public static function forCriteria(int $visaTypeId, ?int $nationalityCountryId, Carbon $asOf): self
    {
        $nationality = $nationalityCountryId !== null ? "nationality country #{$nationalityCountryId}" : 'the general (all-nationalities) rule';

        return new self(
            "Ambiguous fee configuration: more than one active fee rule matches visa type #{$visaTypeId} for {$nationality} on {$asOf->toDateString()}. This must be resolved by an administrator, not guessed."
        );
    }
}
