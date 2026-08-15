<?php

namespace App\Services;

use App\Models\VisaFee;
use App\Services\Exceptions\AmbiguousFeeRuleException;
use App\Services\Exceptions\FeeRuleNotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Approved service (Tech_stack.md §9.2): dated-rule precedence with
 * ambiguity detection. Resolution never silently picks between competing
 * rules — a genuine conflict is a configuration error surfaced to an
 * administrator (AmbiguousFeeRuleException), not resolved by guessing.
 */
class FeeResolver
{
    public function resolve(int $visaTypeId, ?int $nationalityCountryId, ?Carbon $asOf = null): VisaFee
    {
        $asOf ??= Carbon::now();
        $date = $asOf->toDateString();

        if ($nationalityCountryId !== null) {
            $specific = $this->activeRulesQuery($visaTypeId, $date)
                ->where('nationality_country_id', $nationalityCountryId)
                ->get();

            if ($specific->count() > 1) {
                throw AmbiguousFeeRuleException::forCriteria($visaTypeId, $nationalityCountryId, $asOf);
            }

            if ($specific->count() === 1) {
                return $specific->first();
            }
        }

        $general = $this->activeRulesQuery($visaTypeId, $date)
            ->whereNull('nationality_country_id')
            ->get();

        if ($general->count() > 1) {
            throw AmbiguousFeeRuleException::forCriteria($visaTypeId, null, $asOf);
        }

        if ($general->count() === 1) {
            return $general->first();
        }

        throw FeeRuleNotFoundException::forCriteria($visaTypeId, $nationalityCountryId, $asOf);
    }

    /**
     * @return Builder<VisaFee>
     */
    private function activeRulesQuery(int $visaTypeId, string $date): Builder
    {
        return VisaFee::query()
            ->where('visa_type_id', $visaTypeId)
            ->where('is_active', true)
            ->where('valid_from', '<=', $date) // inclusive
            ->where(function (Builder $query) use ($date) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>', $date); // exclusive
            });
    }
}
