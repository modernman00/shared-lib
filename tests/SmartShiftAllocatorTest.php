<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\Healthcare\SmartShiftAllocator;

class SmartShiftAllocatorTest extends TestCase
{
    private SmartShiftAllocator $allocator;

    protected function setUp(): void
    {
        $this->allocator = new SmartShiftAllocator();
    }

    public function testCarerShiftEligibilityPassesForCompliantCarer(): void
    {
        $shiftDate = date('Y-m-d', strtotime('+3 days'));
        $shiftStart = "{$shiftDate} 08:00:00";
        $shiftEnd = "{$shiftDate} 20:00:00"; // 12h shift

        $carer = [
            'dbs_verified' => true,
            'dbs_expires_at' => date('Y-m-d', strtotime('+1 year')),
            'right_to_work_verified' => true,
            'training_certifications' => [
                'safeguarding_adults' => date('Y-m-d', strtotime('+6 months')),
                'moving_and_handling' => date('Y-m-d', strtotime('+6 months')),
                'medication_admin'    => date('Y-m-d', strtotime('+6 months')),
                'infection_control'   => date('Y-m-d', strtotime('+6 months')),
            ],
        ];

        $shift = [
            'start_time' => $shiftStart,
            'end_time' => $shiftEnd,
        ];

        $result = $this->allocator->validateCarerShiftEligibility($carer, $shift);

        $this->assertTrue($result['eligible']);
        $this->assertEmpty($result['hard_stops']);
        $this->assertEquals(100, $result['compliance_score']);
        $this->assertStringContainsString('Fully Compliant', $result['cqc_regulation_status']);
    }

    public function testCarerShiftEligibilityBlocksExpiredDbsAndRestViolations(): void
    {
        $shiftDate = date('Y-m-d', strtotime('+2 days'));
        $shiftStart = "{$shiftDate} 08:00:00";
        $shiftEnd = "{$shiftDate} 16:00:00";

        $carer = [
            'dbs_verified' => false, // Expired DBS
            'dbs_expires_at' => '2023-01-01',
            'right_to_work_verified' => true,
            'training_certifications' => [],
        ];

        // Previous shift finished only 4 hours before (violates 11h mandatory rest period)
        $previousShiftEnd = "{$shiftDate} 04:00:00";
        $previousShiftStart = date('Y-m-d H:i:s', strtotime('-8 hours', strtotime($previousShiftEnd)));

        $existing = [
            [
                'id' => 101,
                'start_time' => $previousShiftStart,
                'end_time' => $previousShiftEnd,
            ],
        ];

        $shift = [
            'start_time' => $shiftStart,
            'end_time' => $shiftEnd,
        ];

        $result = $this->allocator->validateCarerShiftEligibility($carer, $shift, $existing);

        $this->assertFalse($result['eligible']);
        $this->assertGreaterThanOrEqual(3, count($result['hard_stops']));
        $this->assertStringContainsString('CQC Reg 19 Hard Stop', $result['hard_stops'][0]);
        $this->assertStringContainsString('Working Time Regs Hard Stop', implode(' ', $result['hard_stops']));
    }

    public function testAccruedEwaCalculation(): void
    {
        $timesheets = [
            ['total_hours' => 12.0, 'status' => 'approved'],
            ['total_hours' => 8.0,  'status' => 'approved'],
            ['total_hours' => 6.0,  'status' => 'pending'], // Pending shouldn't count
        ];

        $hourlyRate = 18.50; // £18.50/hr
        $alreadyDrawn = 50.00;

        $ewa = $this->allocator->calculateAccruedEwa($timesheets, $hourlyRate, $alreadyDrawn);

        $this->assertEquals(20.0, $ewa['total_hours_worked']);
        $this->assertEquals(370.0, $ewa['gross_accrued_earnings']); // 20 * 18.50 = 370
        $this->assertEquals(185.0, $ewa['max_allowed_drawdown']);    // 50% of 370 = 185
        $this->assertEquals(135.0, $ewa['available_to_withdraw']);   // 185 - 50 = 135
        $this->assertTrue($ewa['is_eligible_for_drawdown']);
    }
}
