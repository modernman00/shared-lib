<?php 
/**
 * Convert an integer to its English word representation.
 *
 * @param int $number
 * @return string
 */

function number2word(int $num) {
    $words = [
        0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four',
        5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine',
        10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen',
        14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen',
        17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen',
        20 => 'twenty', 30 => 'thirty', 40 => 'forty', 50 => 'fifty',
        60 => 'sixty', 70 => 'seventy', 80 => 'eighty', 90 => 'ninety'
    ];

    if ($num < 20) return $words[$num];
    if ($num < 100) return $words[10 * floor($num / 10)] . ($num % 10 ? ' ' . $words[$num % 10] : '');
    if ($num < 1000) return $words[floor($num / 100)] . ' hundred ' . ($num % 100 ? number2word($num % 100) : '');
    if ($num < 1000000) return number2word(floor($num / 1000)) . ' thousand ' . ($num % 1000 ? number2word($num % 1000) : '');
    if ($num < 1000000000) return number2word(floor($num / 1000000)) . ' million ' . ($num % 1000000 ? number2word($num % 1000000) : '');

    return number2word(floor($num / 1000000000)) . ' billion ' . ($num % 1000000000 ? number2word($num % 1000000000) : '');
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