<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\Quality\AssertsEngineeringMustHaves;
use Src\Quality\EngineeringMustHaves;

final class EngineeringMustHavesTest extends TestCase
{
    use AssertsEngineeringMustHaves;

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/mh_' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/vendor/x', 0777, true);
        mkdir($this->dir . '/app', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->dir);
    }

    private function put(string $rel, string $body): void
    {
        file_put_contents($this->dir . '/' . $rel, $body);
    }

    public function testCleanProjectPasses(): void
    {
        $this->put('app/ok.php', "<?php\n\$s = \$db->prepare('SELECT id FROM t WHERE id = ?');\n");
        $this->assertSame([], EngineeringMustHaves::scan($this->dir));
    }

    public function testPostFormWithoutCsrfIsFlagged(): void
    {
        $this->put('app/f.html', '<form method="post"><input name="a"></form>');
        $v = EngineeringMustHaves::scan($this->dir);
        $this->assertCount(1, $v);
        $this->assertStringContainsString('#1', $v[0]['rule']);
    }

    public function testPostFormWithCsrfAndGetFormPass(): void
    {
        $this->put('app/f.html', '<form method="post">@csrf</form><form method="get"></form>');
        $this->assertSame([], EngineeringMustHaves::scan($this->dir));
    }

    public function testSqlInterpolationFlaggedButCommentIgnored(): void
    {
        $this->put('app/bad.php', "<?php\n\$q = \"SELECT * FROM users WHERE n = '\$id'\";\n// \$c = \"SELECT * FROM users WHERE n = '\$id'\";\n");
        $v = EngineeringMustHaves::scan($this->dir);
        $this->assertCount(1, $v);
        $this->assertSame(2, $v[0]['line']);
    }

    public function testWeakHashOnPasswordFlagged(): void
    {
        $this->put('app/h.php', "<?php\n\$h = md5(\$password);\n");
        $v = EngineeringMustHaves::scan($this->dir);
        $this->assertCount(1, $v);
        $this->assertStringContainsString('#3', $v[0]['rule']);
    }

    public function testExcludedDirectoriesAreNotScanned(): void
    {
        $this->put('vendor/x/bad.php', "<?php\n\$h = md5(\$password);\n");
        $this->assertSame([], EngineeringMustHaves::scan($this->dir));
    }

    public function testReportExitCodes(): void
    {
        $this->assertSame(0, EngineeringMustHaves::report([], $this->dir, false)['exit']);
        $this->put('app/h.php', "<?php\n\$h = sha1(\$token);\n");
        $this->assertSame(1, EngineeringMustHaves::report(EngineeringMustHaves::scan($this->dir), $this->dir, false)['exit']);
    }
}
