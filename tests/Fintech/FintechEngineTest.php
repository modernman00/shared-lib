<?php

declare(strict_types=1);

namespace Tests\Fintech;

use PHPUnit\Framework\TestCase;
use Src\Fintech\NeuroCategoriser;
use Src\Fintech\UniversalStatementParser;
use Src\Fintech\FinancialRuleMemoryEngine;

class FintechEngineTest extends TestCase
{
    private NeuroCategoriser $categoriser;
    private UniversalStatementParser $parser;
    private FinancialRuleMemoryEngine $memory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->categoriser = new NeuroCategoriser(null);
        $this->parser = new UniversalStatementParser(null);
        $this->memory = new FinancialRuleMemoryEngine();
    }

    public function testNeuroCategoriserDirectTaxonomy(): void
    {
        $this->assertEquals('GROCERIES', $this->categoriser->categorise('TESCO STORES 2819'));
        $this->assertEquals('GROCERIES', $this->categoriser->categorise('SAINSBURYS S/MKTS'));
        $this->assertEquals('TRANSPORT', $this->categoriser->categorise('TFL TRAVEL CH'));
        $this->assertEquals('TRANSPORT', $this->categoriser->categorise('UBER *TRIP 9812'));
        $this->assertEquals('EATING_OUT', $this->categoriser->categorise('STARBUCKS COFFEE LONDON'));
        $this->assertEquals('EATING_OUT', $this->categoriser->categorise('DELIVEROO.CO.UK'));
        $this->assertEquals('BILLS_AND_SERVICES', $this->categoriser->categorise('OCTOPUS ENERGY DIRECT DEBIT'));
        $this->assertEquals('BILLS_AND_SERVICES', $this->categoriser->categorise('SPOTIFY UK'));
        $this->assertEquals('SHOPPING', $this->categoriser->categorise('AMZN MKTP UK*91283'));
    }

    public function testNeuroCategoriserFuzzyMatch(): void
    {
        $this->assertEquals('GROCERIES', $this->categoriser->categorise('SAINSBURY S S/MKT'));
        $this->assertEquals('EATING_OUT', $this->categoriser->categorise('MCDONALDS RESTAURANT'));
        $this->assertEquals('SHOPPING', $this->categoriser->categorise('ZARA CLOTHING STORE'));
    }

    public function testUniversalStatementParserPdfLineExtraction(): void
    {
        $samplePdfLines = [
            "BARCLAYS BANK STATEMENT",
            "Account Number: 12345678",
            "12/04/2026 TESCO STORES 4210 45.50 CR 1,240.50",
            "14/04/2026 TFL TRAVEL CHARGE 3.40 1,237.10",
            "15/04/2026 OCTOPUS ENERGY 112.00 1,125.10",
            "16/04/2026 SALARY EMPLOYER INC 3,500.00 CR 4,625.10",
        ];

        $results = $this->parser->extractTransactionsFromPdfLines($samplePdfLines);

        $this->assertCount(4, $results);
        $this->assertEquals('2026-04-12', $results[0]['date']);
        $this->assertEquals(45.50, $results[0]['amount']);
        $this->assertEquals('GROCERIES', $results[0]['category']);

        $this->assertEquals('2026-04-14', $results[1]['date']);
        $this->assertEquals('TRANSPORT', $results[1]['category']);

        $this->assertEquals('2026-04-15', $results[2]['date']);
        $this->assertEquals('BILLS_AND_SERVICES', $results[2]['category']);

        $this->assertEquals('2026-04-16', $results[3]['date']);
        $this->assertEquals(3500.00, $results[3]['amount']);
    }

    public function testFinancialRuleMemoryEngineExtraction(): void
    {
        $query1 = "I want to save £10,000 for a house deposit by December 2026";
        $mem1 = $this->memory->extractMemories($query1);
        $this->assertCount(1, $mem1);
        $this->assertEquals('savings_goal', $mem1[0]['key']);
        $this->assertStringContainsString('£10,000', $mem1[0]['value']);

        $query2 = "I owe £3,200 on my Barclaycard credit card and my salary is £4,500 per month";
        $mem2 = $this->memory->extractMemories($query2);
        $this->assertCount(2, $mem2);
        $this->assertEquals('outstanding_debt', $mem2[0]['key']);
        $this->assertEquals('income_declaration', $mem2[1]['key']);
    }
}
