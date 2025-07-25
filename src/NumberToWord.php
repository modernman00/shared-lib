<?php

declare(strict_types=1);

namespace Src;

class NumberTwoWords
{
    /*************  ✨ Windsurf Command ⭐  *************/
    /**
     * Convert a number to its written form. e.g. 1 -> One, 2 -> Two, 3 -> Three.
     *
     * @param int $number
     *
     * @return string
     */
    /*******  6553e91b-dacd-4bb8-af3a-27161a718c16  *******/
    public static function number2word(int $number)
    {
        try {
            $fool = new \NumberFormatter('en', \NumberFormatter::SPELLOUT);
            $fool->setTextAttribute(\NumberFormatter::DEFAULT_RULESET, '%spellout-numbering-verbose');
            $output = $fool->format($number);

            return ucfirst($output);
        } catch (\TypeError $e) {
            Utility::showError($e);
        }
    }

    /*************  ✨ Windsurf Command ⭐  *************/
    /**
     * Convert a number to its written form and append the currency. e.g. 1 -> One USD, 2 -> Two USD, 3 -> Three USD.
     *
     * @param int $number
     * @param string $currency
     *
     * @return string
     */
    /*******  22935518-7000-4b46-a242-09e35c999b68  *******/
    public static function number2wordWithCurrency(int $number, string $currency = 'USD')
    {
        try {
            $fool = new \NumberFormatter('en', \NumberFormatter::SPELLOUT);
            $fool->setTextAttribute(\NumberFormatter::DEFAULT_RULESET, '%spellout-numbering-verbose');
            $output = $fool->format($number);

            return ucfirst($output) . ' ' . strtoupper($currency);
        } catch (\TypeError $e) {
            Utility::showError($e);
        }
    }

    public static function number2wordWithCurrencyAndDecimal(int $number, string $currency = 'USD')
    {
        try {
            $fool = new \NumberFormatter('en', \NumberFormatter::SPELLOUT);
            $fool->setTextAttribute(\NumberFormatter::DEFAULT_RULESET, '%spellout-numbering-verbose');
            $output = $fool->format($number);

            return ucfirst($output) . ' ' . strtoupper($currency) . ' only';
        } catch (\TypeError $e) {
            Utility::showError($e);
        }
    }
}
