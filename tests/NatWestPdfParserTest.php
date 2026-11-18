<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\Fintech\NatWestPdfParser;
use Src\Fintech\UniversalStatementParser;

final class NatWestPdfParserTest extends TestCase
{
    private function fixture(): string
    {
        $path = getenv('NATWEST_PDF_FIXTURE') ?: __DIR__ . '/../../iaccountapp/scratch/Statement_524108_41113845_01_Jun_2026.pdf';
        if (!is_file($path)) {
            $this->markTestSkipped('NatWest PDF fixture not present (set NATWEST_PDF_FIXTURE).');
        }
        return $path;
    }

    public function testNonPdfInputReturnsNull(): void
    {
        $this->assertNull((new NatWestPdfParser())->parse(__FILE__));
        $this->assertNull((new NatWestPdfParser())->parse('/nonexistent.pdf'));
    }

    public function testMerchantExtractionAndCashCategory(): void
    {
        $p = new NatWestPdfParser();
        $this->assertSame('CASH', $p->categorise('Cash Withdrawal LINK ATM', new \Src\Fintech\NeuroCategoriser()));
        $this->assertSame('INSURANCE', $p->categorise('NWI HOME INSURANCE', new \Src\Fintech\NeuroCategoriser()));
    }

    public function testUniversalParserReconcilesNatWestStatement(): void
    {
        $diag = (new UniversalStatementParser())->parsePdfWithDiagnostics($this->fixture());
        $this->assertTrue($diag['success']);
        $this->assertSame('NatWest', $diag['bank']);
        $this->assertTrue($diag['reconciled']);
        $this->assertSame(254, $diag['total_count']);
        $this->assertEqualsWithDelta(9323.26, $diag['total_inflow'], 0.01);
        $this->assertEqualsWithDelta(9565.27, $diag['total_outflow'], 0.01);
        $this->assertCount(254, (new UniversalStatementParser())->parsePdf($this->fixture()));
    }
}
