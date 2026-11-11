<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Twtgroup\Headliner\Command\ScanTemplatesCommand;
use Twtgroup\Headliner\Migration\TemplateScanner;

final class ScanTemplatesCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/headliner-scan-' . bin2hex(random_bytes(4));
        mkdir($this->directory . '/Templates', 0o777, true);
        mkdir($this->directory . '/vendor/pkg', 0o777, true);
        file_put_contents(
            $this->directory . '/Templates/Text.html',
            "<f:layout name=\"Default\" />\n<h2>{record -> f:render.text(field: 'header')}</h2>\n<h3>{title} ({count})</h3>\n<h4>Fixed</h4>\n"
        );
        file_put_contents($this->directory . '/Templates/Plain.html', '<h2>{data.header}</h2>');
        file_put_contents($this->directory . '/vendor/pkg/Skipped.html', '<f:render /><h2>{data.header}</h2>');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/{Templates,vendor/pkg}/*', GLOB_BRACE) ?: [] as $file) {
            unlink($file);
        }
        foreach (['/Templates', '/vendor/pkg', '/vendor', ''] as $directory) {
            rmdir($this->directory . $directory);
        }
    }

    #[Test]
    public function itReportsTheHeadingsOfFluidTemplatesAndSkipsOthers(): void
    {
        $tester = new CommandTester(new ScanTemplatesCommand(new TemplateScanner()));
        $tester->execute(['path' => $this->directory]);
        $output = $tester->getDisplay();

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Templates/Text.html:2', $output);
        self::assertStringContainsString('<headliner:headline record="{record}" />', $output);
        self::assertStringContainsString('Templates/Text.html:3', $output);
        self::assertStringNotContainsString('Templates/Plain.html', $output, 'a file without Fluid is no template');
        self::assertStringNotContainsString('Skipped.html', $output, 'vendor is skipped');
        self::assertStringNotContainsString('Fixed', $output, 'a static heading is not listed without --with-static');
        self::assertStringContainsString('1 can be replaced, 1 needs a decision, 1 static', $output);
    }

    #[Test]
    public function withStaticAlsoListsTheFixedHeadings(): void
    {
        $tester = new CommandTester(new ScanTemplatesCommand(new TemplateScanner()));
        $tester->execute(['path' => $this->directory, '--with-static' => true]);

        self::assertStringContainsString('Fixed', $tester->getDisplay());
    }

    #[Test]
    public function aPathThatIsNoDirectoryFails(): void
    {
        $tester = new CommandTester(new ScanTemplatesCommand(new TemplateScanner()));
        $tester->execute(['path' => $this->directory . '/missing']);

        self::assertSame(1, $tester->getStatusCode());
    }
}
