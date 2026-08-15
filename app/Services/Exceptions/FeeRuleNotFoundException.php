<?php

namespace App\Services\Exceptions;

use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * No active fee rule matches the given criteria at all — distinct from
 * AmbiguousFeeRuleException, which is too many matches rather than none.
 */
class FeeRuleNotFoundException extends RuntimeException
{
    public static function forCriteria(int $visaTypeId, ?int $nationalityCountryId, Carbon $asOf): self
    {
        $nationality = $nationalityCountryId !== null ? "nationality country #{$nationalityCountryId}" : 'no specific nationality';

        return new self(
            "No active fee rule found for visa type #{$visaTypeId}, {$nationality}, as of {$asOf->toDateString()}."
        );
    }
}
