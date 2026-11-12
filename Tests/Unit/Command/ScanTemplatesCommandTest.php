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
        self::assertStringContainsString('<headliner:headline record="{record}" default="h2" />', $output);
        self::assertStringContainsString("  - <h2>{record -> f:render.text(field: 'header')}</h2>", $output);
        self::assertStringContainsString('  + <headliner:headline record="{record}" default="h2" />', $output);
        self::assertStringContainsString('Templates/Text.html:3', $output);
        self::assertStringNotContainsString('Templates/Plain.html', $output, 'a file without Fluid is no template');
        self::assertStringNotContainsString('Skipped.html', $output, 'vendor is skipped');
        self::assertStringNotContainsString('Fixed', $output, 'a static heading is not listed without --with-static');
        self::assertStringContainsString('1 can be replaced, 0 components, 1 needs a decision, 1 static', $output);
    }

    #[Test]
    public function withStaticAlsoListsTheFixedHeadings(): void
    {
        $tester = new CommandTester(new ScanTemplatesCommand(new TemplateScanner()));
        $tester->execute(['path' => $this->directory, '--with-static' => true]);

        self::assertStringContainsString('Fixed', $tester->getDisplay());
    }

    #[Test]
    public function backendTemplatesAreCountedAndOnlyListedWithWithBackend(): void
    {
        mkdir($this->directory . '/Card/templates', 0o777, true);
        file_put_contents($this->directory . '/Card/templates/backend-preview.html', '<f:render /><h2>{data.header}</h2>');
        $tester = new CommandTester(new ScanTemplatesCommand(new TemplateScanner()));

        $tester->execute(['path' => $this->directory]);
        self::assertStringNotContainsString('backend-preview', $tester->getDisplay());
        self::assertStringContainsString('1 in backend templates (not listed)', $tester->getDisplay());

        $tester->execute(['path' => $this->directory, '--with-backend' => true]);
        self::assertStringContainsString('Card/templates/backend-preview.html:1', $tester->getDisplay());

        unlink($this->directory . '/Card/templates/backend-preview.html');
        rmdir($this->directory . '/Card/templates');
        rmdir($this->directory . '/Card');
    }

    #[Test]
    public function attributesOfTheHeadingAreKeptInTheReplacement(): void
    {
        file_put_contents($this->directory . '/Templates/Card.html', "<f:render />\n<h3 class=\"card\" id=\"x\">{record -> f:render.text(field: 'header')}</h3>");
        $tester = new CommandTester(new ScanTemplatesCommand(new TemplateScanner()));
        $tester->execute(['path' => $this->directory]);

        self::assertStringContainsString('  + <headliner:headline record="{record}" default="h3" class="card" id="x" />', $tester->getDisplay());
        self::assertStringNotContainsString('  ! ', $tester->getDisplay());
        unlink($this->directory . '/Templates/Card.html');
    }

    #[Test]
    public function whatIsTrueForEveryReplacementIsSaidOnceAndNotPerHeading(): void
    {
        $tester = new CommandTester(new ScanTemplatesCommand(new TemplateScanner()));
        $tester->execute(['path' => $this->directory]);
        $output = $tester->getDisplay();

        self::assertSame(1, substr_count($output, 'For every replacement'));
        self::assertStringContainsString('header_tag and header_layout of the record', $output);
        self::assertStringNotContainsString('subheader_tag', $output, 'no subheader in the templates, so nothing about it');
    }

    #[Test]
    public function aSubheaderReplacementSaysWhereItsTagComesFrom(): void
    {
        file_put_contents($this->directory . '/Templates/Sub.html', "<f:render />\n<h3>{record -> f:render.text(field: 'subheader')}</h3>");
        $tester = new CommandTester(new ScanTemplatesCommand(new TemplateScanner()));
        $tester->execute(['path' => $this->directory]);

        self::assertStringContainsString('subheader_tag', $tester->getDisplay());
        unlink($this->directory . '/Templates/Sub.html');
    }

    #[Test]
    public function aSwitchOfTagsIsOneLineWithTheNumberOfHeadings(): void
    {
        file_put_contents(
            $this->directory . '/Templates/Component.html',
            "<f:switch expression=\"{l}\"><f:case value=\"1\"><h1>{c}</h1></f:case><f:case value=\"2\"><h2>{c}</h2></f:case><f:case value=\"3\"><h3>{c}</h3></f:case><f:case value=\"9\">x</f:case></f:switch>"
        );
        $tester = new CommandTester(new ScanTemplatesCommand(new TemplateScanner()));
        $tester->execute(['path' => $this->directory]);

        self::assertStringContainsString('Templates/Component.html:1  (3 headings: h1/h2/h3)', $tester->getDisplay());
        self::assertStringContainsString('1 component', $tester->getDisplay());
        self::assertStringContainsString('  + <headliner:headline.values tag="h{l}">{c}</headliner:headline.values>', $tester->getDisplay());
        self::assertStringContainsString('  ? the switch has other cases (9) than these headings', $tester->getDisplay());
        unlink($this->directory . '/Templates/Component.html');
    }

    #[Test]
    public function excludedPathsAreSkipped(): void
    {
        mkdir($this->directory . '/app/typo3conf/ext/news', 0o777, true);
        file_put_contents($this->directory . '/app/typo3conf/ext/news/List.html', '<f:render /><h3>{newsItem.title}</h3>');
        $tester = new CommandTester(new ScanTemplatesCommand(new TemplateScanner()));

        $tester->execute(['path' => $this->directory]);
        self::assertStringContainsString('app/typo3conf/ext/news/List.html:1', $tester->getDisplay());

        $tester->execute(['path' => $this->directory, '--exclude' => ['app/typo3conf/ext/', 'Templates/Plain.html']]);
        self::assertStringNotContainsString('typo3conf', $tester->getDisplay());
        self::assertStringContainsString('Templates/Text.html:2', $tester->getDisplay(), 'what is not excluded is still scanned');

        // a name that only starts like the path is not the path
        $tester->execute(['path' => $this->directory, '--exclude' => ['app/typo3conf/ext/new']]);
        self::assertStringContainsString('app/typo3conf/ext/news/List.html:1', $tester->getDisplay());

        unlink($this->directory . '/app/typo3conf/ext/news/List.html');
        foreach (['/app/typo3conf/ext/news', '/app/typo3conf/ext', '/app/typo3conf', '/app'] as $directory) {
            rmdir($this->directory . $directory);
        }
    }

    #[Test]
    public function decisionsAreGroupedByReasonAndEveryPlaceKeepsItsFileAndLine(): void
    {
        file_put_contents($this->directory . '/Templates/Reasons.html', "<f:render />\n<h2><f:translate key=\"a\" /></h2>\n<h2>{newsItem.title}</h2>");
        file_put_contents($this->directory . '/Templates/Other.html', "<f:render />\n<h3><f:translate key=\"b\" /></h3>");
        $tester = new CommandTester(new ScanTemplatesCommand(new TemplateScanner()));
        $tester->execute(['path' => $this->directory]);
        $output = $tester->getDisplay();

        self::assertStringContainsString('Needs a decision, translation (2)', $output);
        self::assertStringContainsString('Needs a decision, a single variable, no record (1)', $output);
        self::assertMatchesRegularExpression('#translation \(2\):\n(Templates/\w+\.html:\d+ .*\n){2}#', $output);
        self::assertStringContainsString('Templates/Other.html:2', $output);
        self::assertStringContainsString('Templates/Reasons.html:2', $output);
        self::assertStringContainsString('Templates/Reasons.html:3', $output);
        unlink($this->directory . '/Templates/Reasons.html');
        unlink($this->directory . '/Templates/Other.html');
    }

    #[Test]
    public function aFormattedRecordFieldIsSaidToBeOutputDifferently(): void
    {
        file_put_contents($this->directory . '/Templates/Raw.html', "<f:render />\n<h2><f:format.raw>{data.header}</f:format.raw></h2>");
        $tester = new CommandTester(new ScanTemplatesCommand(new TemplateScanner()));
        $tester->execute(['path' => $this->directory]);

        self::assertStringContainsString('with its own formatting', $tester->getDisplay());
        self::assertStringContainsString('outputs it differently', $tester->getDisplay());
        unlink($this->directory . '/Templates/Raw.html');
    }

    #[Test]
    public function aPathThatIsNoDirectoryFails(): void
    {
        $tester = new CommandTester(new ScanTemplatesCommand(new TemplateScanner()));
        $tester->execute(['path' => $this->directory . '/missing']);

        self::assertSame(1, $tester->getStatusCode());
    }
}
