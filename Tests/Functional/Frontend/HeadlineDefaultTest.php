<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Frontend;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Tests\Functional\AbstractFrontendTestCase;
use TYPO3\CMS\Core\Domain\RawRecord;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Domain\Record\ComputedProperties;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * The tag for a heading that has neither a tag of its own nor a layout: `default`, a tag or a layout number, in all the
 * ViewHelpers for a heading.
 */
final class HeadlineDefaultTest extends AbstractFrontendTestCase
{
    protected array $testExtensionsToLoad = [
        'twtgroup/headliner',
        __DIR__ . '/../Fixtures/Extensions/headliner_test_cards',
    ];

    /**
     * @param array<string, mixed> $properties
     */
    private static function record(string $type, int $uid, array $properties): Record
    {
        return new Record(new RawRecord($uid, 1, $properties, new ComputedProperties(), $type), $properties);
    }

    private function renderDefaults(): string
    {
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: [__DIR__ . '/../Fixtures/Templates'],
            request: new ServerRequest('https://website.local/', 'GET'),
        ));
        $view->assignMultiple([
            // no tag, and no layout
            'untagged' => self::record('tx_headlinertestcards_single', 5, ['title' => 'T', 'level' => '']),
            'tagged' => self::record('tx_headlinertestcards_single', 6, ['title' => 'T', 'level' => 'h4']),
            // no tag, but a layout
            'layouted' => self::record('tt_content.text', 7, ['CType' => 'text', 'header_tag' => '', 'header_layout' => 3]),
            'row' => ['uid' => 8, 'pid' => 1, 'title' => 'Row', 'level' => '', 'deleted' => 0, 't3ver_oid' => 0, 't3ver_wsid' => 0, 't3ver_state' => 0],
        ]);

        return trim($view->render('Default'));
    }

    #[Test]
    public function aDefaultThatIsATagIsTheTagOfAHeadingWithoutOne(): void
    {
        $html = $this->renderDefaults();

        self::assertStringContainsString('<h3>A tag</h3>', $html);
        self::assertStringContainsString('<div>A div</div>', $html);
    }

    #[Test]
    public function aDefaultThatIsALayoutNumberStillWorks(): void
    {
        self::assertStringContainsString('<h4>A layout number</h4>', $this->renderDefaults());
    }

    #[Test]
    public function aDefaultThatIsNoTagIsIgnored(): void
    {
        // the setting of the content element, which this template does not have: h2
        self::assertStringContainsString('<h2>Not a tag</h2>', $this->renderDefaults());
    }

    #[Test]
    public function theTagOfTheRecordAndItsLayoutWinOverTheDefault(): void
    {
        $html = $this->renderDefaults();

        self::assertStringContainsString('>Own tag</h4>', $html);
        self::assertStringContainsString('>Own layout</h3>', $html);
    }

    #[Test]
    public function theViewHelpersForARowAndForValuesTakeTheDefaultToo(): void
    {
        $html = $this->renderDefaults();

        // the row has no label text here: the text comes from the label field "title"
        self::assertStringContainsString('<h6>Row</h6>', $html);
        self::assertStringContainsString('<h3>Values</h3>', $html);
    }
}
