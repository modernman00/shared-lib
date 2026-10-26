<?php

declare(strict_types=1);

namespace Tests\Fintech\Vision;

use PHPUnit\Framework\TestCase;
use Src\Fintech\Vision\DocumentVisionEngine;

class DocumentVisionEngineTest extends TestCase
{
    private DocumentVisionEngine $engine;
    private string $dummyPayslipPath;
    private string $dummyIdPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new DocumentVisionEngine(['simulate' => true]);

        $this->dummyPayslipPath = sys_get_temp_dir() . '/dummy_payslip.png';
        file_put_contents($this->dummyPayslipPath, 'fake_png_binary_data');

        $this->dummyIdPath = sys_get_temp_dir() . '/dummy_passport.jpg';
        file_put_contents($this->dummyIdPath, 'fake_jpg_binary_data');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dummyPayslipPath)) {
            unlink($this->dummyPayslipPath);
        }
        if (file_exists($this->dummyIdPath)) {
            unlink($this->dummyIdPath);
        }
        parent::tearDown();
    }

    public function testAnalyzePayslipExtraction(): void
    {
        $declaredIncome = 2850.00;
        $result = $this->engine->analyzePayslip($this->dummyPayslipPath, 'image/png', $declaredIncome);

        $this->assertTrue($result['success']);
        $this->assertEquals(2850.00, $result['net_pay']);
        $this->assertGreaterThan(2850.00, $result['gross_pay']);
        $this->assertEquals(0.0, $result['variance_pct']);
        $this->assertFalse($result['tampering_detected']);
        $this->assertNotEmpty($result['employer_name']);
        $this->assertGreaterThanOrEqual(90, $result['confidence']);
    }

    public function testAnalyzeGovernmentIdExtraction(): void
    {
        $applicantName = 'Samantha Miller';
        $result = $this->engine->analyzeGovernmentId($this->dummyIdPath, 'image/jpeg', $applicantName);

        $this->assertTrue($result['success']);
        $this->assertEquals('PASSPORT', $result['document_type']);
        $this->assertEquals('Samantha Miller', $result['extracted_full_name']);
        $this->assertFalse($result['is_expired']);
        $this->assertTrue($result['mrz_detected']);
        $this->assertEquals(100, $result['name_match_score']);
        $this->assertGreaterThanOrEqual(95, $result['confidence']);
    }

    public function testCrossReferenceCleanMatch(): void
    {
        $declared = [
            'monthly_income' => 3200.00,
            'applicant_name' => 'Alexander Montgomery',
        ];

        $payslip = [
            'success' => true,
            'net_pay' => 3200.00,
            'variance_pct' => 0.0,
            'tampering_detected' => false,
        ];

        $idDoc = [
            'success' => true,
            'document_type' => 'PASSPORT',
            'extracted_full_name' => 'Alexander Montgomery',
            'name_match_score' => 100,
            'is_expired' => false,
            'tampering_detected' => false,
        ];

        $outcome = $this->engine->crossReference($declared, $payslip, $idDoc);

        $this->assertTrue($outcome['income_verified']);
        $this->assertTrue($outcome['identity_verified']);
        $this->assertGreaterThanOrEqual(95, $outcome['confidence_score']);
        $this->assertEmpty($outcome['flags']);
        $this->assertNotEmpty($outcome['reasoning']);
    }

    public function testCrossReferenceVarianceAndAnomalyFlags(): void
    {
        $declared = [
            'monthly_income' => 4500.00,
            'applicant_name' => 'David Brown',
        ];

        // Extracted net pay is £2000 (more than 50% variance!)
        $payslip = [
            'success' => true,
            'net_pay' => 2000.00,
            'variance_pct' => 55.6,
            'tampering_detected' => false,
        ];

        $idDoc = [
            'success' => true,
            'document_type' => 'DRIVING_LICENCE',
            'extracted_full_name' => 'David Brown',
            'name_match_score' => 100,
            'is_expired' => true, // Expired ID
            'expiry_date' => '2020-01-01',
            'tampering_detected' => false,
        ];

        $outcome = $this->engine->crossReference($declared, $payslip, $idDoc);

        $this->assertFalse($outcome['identity_verified']);
        $this->assertNotEmpty($outcome['flags']);
        $this->assertLessThan(60, $outcome['confidence_score']);

        $flagsStr = implode(' ', $outcome['flags']);
        $this->assertStringContainsString('Income discrepancy', $flagsStr);
        $this->assertStringContainsString('expired', $flagsStr);
    }

    public function testHuggingFaceVisionFailover(): void
    {
        $mockGateway = $this->createMock(\Src\Ai\HuggingFaceInferenceGateway::class);
        $mockGateway->expects($this->once())
            ->method('analyzeImageWithPrompt')
            ->willReturn(json_encode([
                'net_pay' => 3100.50,
                'gross_pay' => 4100.00,
                'pay_date' => '2026-10-01',
                'employer_name' => 'Acme Global Ltd',
                'employee_name' => 'John Doe',
                'tax_period' => 'Month 07',
                'tampering_detected' => false,
                'tamper_flags' => [],
                'confidence' => 97,
            ]));

        $engine = new DocumentVisionEngine([
            'simulate' => false,
            'api_key' => '', // Gemini unconfigured -> fails over to HF
            'hf_gateway' => $mockGateway,
        ]);

        $result = $engine->analyzePayslip($this->dummyPayslipPath, 'image/png', 3100.50);

        $this->assertTrue($result['success']);
        $this->assertEquals(3100.50, $result['net_pay']);
        $this->assertEquals(4100.00, $result['gross_pay']);
        $this->assertEquals('Acme Global Ltd', $result['employer_name']);
        $this->assertEquals(97, $result['confidence']);
    }
}
