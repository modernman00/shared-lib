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
                    $cleanNameKey = strtoupper(preg_replace('/[^0-9A-Za-z@.]/', ' ', (string) $fieldName));

                    $error .= htmlspecialchars("$cleanNameKey is required") . '<br>';
                }
            }
        }

        // Special case: check if passwords match in 'createAccount' group.
        // The group is normally a list (['password', 'confirm_password']); the previous check
        // looked for string keys that never exist, so mismatched passwords were never caught.
        // An associative form (['password' => 'pwd', 'confirm_password' => 'pwd2']) is also supported.
        $group = $array['createAccount'] ?? null;
        if (is_array($group)) {
            $pwdField = $group['password'] ?? (in_array('password', $group, true) ? 'password' : null);
            $confirmField = $group['confirm_password'] ?? (in_array('confirm_password', $group, true) ? 'confirm_password' : null);
            if (
                is_string($pwdField) && is_string($confirmField) &&
                isset($_POST[$pwdField], $_POST[$confirmField]) &&
                (string) $_POST[$pwdField] !== (string) $_POST[$confirmField]
            ) {
                $error .= 'Your passwords do not match';
            }
        }

        return $error;
    }
}
