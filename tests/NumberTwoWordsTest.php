<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Src\NumberToWord;

class NumberTwoWordsTest extends TestCase
{
    public function testNumber2Word()
    {
        $this->assertEquals('One', NumberToWord::number2word(1));
        $this->assertEquals('Twenty-one', NumberToWord::number2word(21));
    }

    public function testNumber2WordWithCurrency()
    {
        $this->assertEquals('One USD', NumberToWord::number2wordWithCurrency(1));
        $this->assertEquals('Fifty CAD', NumberToWord::number2wordWithCurrency(50, 'CAD'));
    }

    public function testNumber2WordWithCurrencyAndDecimal()
    {
        $this->assertEquals('One USD only', NumberToWord::number2wordWithCurrencyAndDecimal(1));
        $this->assertEquals('Seventy-five EUR only', NumberToWord::number2wordWithCurrencyAndDecimal(75, 'EUR'));
    }
}
