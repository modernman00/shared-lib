<?php

declare(strict_types=1);

namespace Src\Sanitise;

/**
 * Class Validate.
 *
 * A utility class for validating form input arrays.
 * Designed for use within a sanitisation library.
 *
 * Usage:
 * - Accepts a structured array defining fields to validate.
 * - Checks for empty values and adds user-friendly error messages.
 * - Handles password confirmation as a special case.
 */
class Validate
{
    /**
     * Validates structured input data against required fields.
     * Returns a string containing human-readable error messages.
     *
     * @param array $array Nested array of required field names grouped logically (e.g. ['group' => ['field1', 'field2']])
     *
     * @return string Error messages formatted for display
     */
    public static function cleanArray(array $array): string
    {
        $error = '';

        foreach ($array as $group) {
            // Only process arrays (e.g. skip anything not grouped properly)
            if (!is_array($group)) {
                continue;
            }

            foreach ($group as $fieldName) {
                // Check for missing POST values and trim whitespace
                if (!isset($_POST[$fieldName]) || trim($_POST[$fieldName]) === '') {
                    // Clean field name for display (e.g. 'user_name' => 'USER NAME')
                    $cleanNameKey = strtoupper(preg_replace('/[^0-9A-Za-z@.]/', ' ', $fieldName));

                    $error .= htmlspecialchars("$cleanNameKey is required") . '<br>';
                }
            }
        }

        // Special case: check if passwords match in 'createAccount' group
        if (
            isset($array['createAccount']['password'], $array['createAccount']['confirm_password']) &&
            ($_POST[$array['createAccount']['password']] !== $_POST[$array['createAccount']['confirm_password']])
        ) {
            $error .= 'Your passwords do not match';
        }

        return $error;
    }
}
