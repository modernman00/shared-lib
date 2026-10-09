<?php

declare(strict_types=1);

namespace Src\Quality;

/**
 * Mix into any PHPUnit TestCase so the Engineering Must-Haves scan runs on every test run:
 *
 *   final class EngineeringMustHavesTest extends \PHPUnit\Framework\TestCase {
 *       use \Src\Quality\AssertsEngineeringMustHaves;
 *       public function testRepositoryPassesMustHaves(): void { $this->assertMustHavesPass(dirname(__DIR__, 2)); }
 *   }
 */
trait AssertsEngineeringMustHaves
{
    protected function assertMustHavesPass(string $projectRoot): void
    {
        $violations = EngineeringMustHaves::scan($projectRoot);
        $report = EngineeringMustHaves::report($violations, $projectRoot, false);
        $this->assertSame(0, $report['exit'], "\n" . $report['text']);
    }
}
