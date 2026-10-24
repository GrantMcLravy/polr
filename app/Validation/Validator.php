<?php
namespace App\Validation;

use Illuminate\Validation\Validator as BaseValidator;

/**
 * Laravel's validator with the rules whose meaning changed since Lumen 5.1
 * restored to their Polr 2.x behavior.
 */
class Validator extends BaseValidator {
    /**
     * Lumen 5.1's "url" rule was PHP's FILTER_VALIDATE_URL. Laravel's is a
     * regular expression that accepts and rejects different URLs (e.g.
     * internationalized or underscored hosts, mailto:), which would change
     * which links Polr accepts.
     */
    public function validateUrl($attribute, $value, $parameters = []) {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
