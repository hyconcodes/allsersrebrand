<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Username implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^[a-z0-9_]+$/', $value) !== 1) {
            $fail('The username may only contain lowercase letters, numbers, and underscores.');
        }
    }
}
