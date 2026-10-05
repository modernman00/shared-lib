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

    public function testStarlingPdfLineExtractionAndPolarity(): void
    {
        $starlingLines = [
            "Summary\t01/12/2025 - 22/01/2026",
            "DATE\tTYPE\tTRANSACTION\tIN\tOUT\tEND OF DAY ACCOUNT BALANCE",
            "\tOPENING BALANCE \t\t\t£1481.76",
            "01/12/2025 DIRECT DEBIT 24/7 Home Rescue (GC781913)\t £4.94\t",
            "01/12/2025 FASTER PAYMENT OLAOGUN E (Your money)\t£60.00\t\t",
            "01/12/2025 FASTER PAYMENT Segun Olaogun (From S Olaogun)\t£500.00\t £1807.05",
            "02/12/2025 ONLINE PAYMENT TESCO CREDIT CARDS\t £940.00\t",
            "22/12/2025 FASTER PAYMENT Eniola Olaogun (INSURANCE)\t£1500.00\t £1934.50",
            "21/01/2026 ONLINE PAYMENT AMAZON* 7T5G59HK5\t £11.99 £200.37"
        ];

        $results = $this->parser->extractTransactionsFromPdfLines($starlingLines);

        $this->assertCount(6, $results);
        // 1. OUT payment
        $this->assertEquals('2025-12-01', $results[0]['date']);
        $this->assertEquals(-4.94, $results[0]['amount']);
        $this->assertEquals('expense', $results[0]['type']);

        // 2. IN payment (\t£60.00\t\t)
        $this->assertEquals('2025-12-01', $results[1]['date']);
        $this->assertEquals(60.00, $results[1]['amount']);
        $this->assertEquals('income', $results[1]['type']);

        // 3. IN payment with End of day balance (\t£500.00\t £1807.05)
        $this->assertEquals('2025-12-01', $results[2]['date']);
        $this->assertEquals(500.00, $results[2]['amount']);
        $this->assertEquals('income', $results[2]['type']);

        // 4. OUT payment (\t £940.00\t)
        $this->assertEquals('2025-12-02', $results[3]['date']);
        $this->assertEquals(-940.00, $results[3]['amount']);
        $this->assertEquals('expense', $results[3]['type']);

        // 5. IN payment > 1000 without comma (\t£1500.00\t £1934.50)
        $this->assertEquals('2025-12-22', $results[4]['date']);
        $this->assertEquals(1500.00, $results[4]['amount']);
        $this->assertEquals('income', $results[4]['type']);

        // 6. 2026 roll-over OUT payment
        $this->assertEquals('2026-01-21', $results[5]['date']);
        $this->assertEquals(-11.99, $results[5]['amount']);
        $this->assertEquals('expense', $results[5]['type']);
    }

    public function testStarlingRealPdfParseIfExists(): void
    {
        $filePath = '/Users/waleolaogun/Downloads/StarlingStatement_01-12-2025_22-01-2026.pdf';
        if (!file_exists($filePath)) {
            $this->markTestSkipped('Starling statement file not found in Downloads.');
        }

        $results = $this->parser->parsePdf($filePath);
        $this->assertCount(142, $results);

        // First transaction verification
        $this->assertEquals('2025-12-01', $results[0]['date']);
        $this->assertEquals(-4.94, $results[0]['amount']);
        $this->assertEquals('expense', $results[0]['type']);

        // Last transaction verification
        $last = end($results);
        $this->assertEquals('2026-01-21', $last['date']);
        $this->assertEquals(-11.99, $last['amount']);
        $this->assertEquals('expense', $last['type']);
    }
}
