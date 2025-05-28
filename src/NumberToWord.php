<?php

namespace App\shared;


class NumberTwoWords
{
  public static function number2word(int $number)
  {

    try {
      $fool = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);
      $fool->setTextAttribute(\NumberFormatter::DEFAULT_RULESET, "%spellout-numbering-verbose");
      $output = $fool->format($number);
      return ucfirst($output);
    } catch (\TypeError $e) {
      Utility::showError($e);
    }
  }
  public static function number2wordWithCurrency(int $number, string $currency = 'USD')
  {
    try {
      $fool = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);
      $fool->setTextAttribute(\NumberFormatter::DEFAULT_RULESET, "%spellout-numbering-verbose");
      $output = $fool->format($number);
      return ucfirst($output) . ' ' . strtoupper($currency);
    } catch (\TypeError $e) {
      Utility::showError($e);
    }
  }
  public static function number2wordWithCurrencyAndDecimal(int $number, string $currency = 'USD')
  {
    try {
      $fool = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);
      $fool->setTextAttribute(\NumberFormatter::DEFAULT_RULESET, "%spellout-numbering-verbose");
      $output = $fool->format($number);
      return ucfirst($output) . ' ' . strtoupper($currency) . ' only';
    } catch (\TypeError $e) {
      Utility::showError($e);
    }
  }
}
