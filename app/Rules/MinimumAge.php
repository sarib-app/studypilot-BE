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
        $dob = Carbon::parse($value);

        if ($dob->age < $this->years) {
            // Abuse-monitoring signal only — no email/name, just that a blocked attempt occurred.
            Log::warning('Signup blocked: under minimum age', ['age' => $dob->age]);
            $fail('under_13_blocked');
        }
    }
}
