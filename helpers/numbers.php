<?php 


/**
 * Convert an integer to its English word representation.
 *
 * @param int $number
 * @return string
 */
function number2word(int $number): string
{
    if ($number === 0) return 'zero';

    $f = new NumberFormatter('en', NumberFormatter::SPELLOUT);

    // Handle negative numbers
    if ($number < 0) {
        return 'minus ' . $f->format(abs($number));
    }

    return $f->format($number);
}

/**
 * Convert an integer to its ordinal English word representation.
 *
 * @param int $number
 * @return string
 */
function number2ordinalword(int $number): string
{
    if ($number === 0) return 'zeroth';

    $f = new NumberFormatter('en', NumberFormatter::ORDINAL);

    return $f->format($number);
}

/**
 * Pluralize a word based on the given count.
 *
 * @param string $word The word to pluralize
 * @param int $count The count determining singular/plural
 * @return string The pluralized word if count is not 1, otherwise the original word
 */
function pluralize(string $word, int $count): string
{
    return ($count === 1) ? $word : $word . 's';
}
/**
 * Generate a random alphanumeric string of specified length.
 *
 * @param int $length Length of the generated string
 * @return string Random alphanumeric string
 */
function generateRandomString(int $length = 16): string
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}