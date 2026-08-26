<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;
use Illuminate\Translation\PotentiallyTranslatedString;

class MinimumAge implements ValidationRule
{
    public function __construct(private readonly int $years = 13) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $dob = Carbon::parse($value);
        } catch (\Exception) {
            // Malformed dates (e.g. day 90) are already caught by the 'date' rule elsewhere in
            // the field's rule list — nothing more to check here since there's no valid date.
            return;
        }

        if ($dob->age < $this->years) {
            // Abuse-monitoring signal only — no email/name, just that a blocked attempt occurred.
            Log::warning('Signup blocked: under minimum age', ['age' => $dob->age]);
            $fail('under_13_blocked');
        }
    }
}
