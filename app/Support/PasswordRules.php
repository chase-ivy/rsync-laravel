<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * One password rule for the whole app (registration, forgot password, change password):
 * at least 8 characters, with an uppercase letter, a lowercase letter and a number.
 */
class PasswordRules
{
    public const HINT = 'At least 8 characters, with an uppercase letter, a lowercase letter and a number.';

    public static function rules(): array
    {
        return ['required', 'string', 'max:100', 'confirmed', Password::min(8)->mixedCase()->numbers()];
    }

    /** Friendlier messages than Laravel's defaults. */
    public static function messages(string $field = 'password'): array
    {
        return [
            "$field.required" => 'Please enter a password.',
            "$field.confirmed" => 'The passwords do not match.',
            "$field.min" => 'The password must be at least 8 characters.',
            "$field.mixed" => 'The password must have at least one uppercase and one lowercase letter.',
            "$field.numbers" => 'The password must have at least one number.',
        ];
    }
}
