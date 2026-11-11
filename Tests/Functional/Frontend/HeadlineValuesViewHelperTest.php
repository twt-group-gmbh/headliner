<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Frontend;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Middleware\InspectRequest;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The ViewHelper for values: an array, a partial without a record, a fixed heading. It sets the tag and the class from
 * what it is given and marks nothing for the page module unless it is told where the tag is edited.
 */
final class HeadlineValuesViewHelperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['twtgroup/headliner'];

    /**
     * @param array<string, mixed> $parent The content element as an array, the header the subheaders are counted from
     * @param array<string, mixed> $settings
     */
    private function render(bool $forThePageModule, array $parent = ['header_tag' => 'h2'], array $settings = []): string
    {
        $request = new ServerRequest('https://website.local/', 'GET');
        $request = $forThePageModule ? $request->withAttribute(InspectRequest::ATTRIBUTE, true) : $request;
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: [__DIR__ . '/../Fixtures/Templates'],
            request: $request,
        ));
        $view->assignMultiple([
            'parent' => $parent,
            'settings' => $settings,
            // the content element, which an automatic mark must never be taken from
            'data' => ['uid' => 12, 'CType' => 'text', 'header_tag' => 'h2', 'header_layout' => 2],
        ]);

        return trim($view->render('Values'));
    }

    #[Test]
    public function theTagAndTheLayoutClassComeFromWhatIsGiven(): void
    {
        $html = $this->render(false);

        self::assertStringContainsString('<h4 class="ce-headline-layout-2">Marked</h4>', $html);
        self::assertStringContainsString('<h5 class="ce-headline-layout-2">Unmarked</h5>', $html);
        // no tag: the layout decides, as for content from before the extension
        self::assertStringContainsString('<h3 class="ce-headline-layout-3">Layout only</h3>', $html);
        self::assertStringContainsString('<div class="x">Div</div>', $html);
    }

    #[Test]
    public function withoutATagAndLayoutTheDefaultOrH2Counts(): void
    {
        self::assertStringContainsString('<h5>Default</h5>', $this->render(false));
        self::assertStringContainsString('<h2>Nothing</h2>', $this->render(false));
        // the setting of the content element, without a default of its own
        self::assertStringContainsString('<h4>Nothing</h4>', $this->render(false, settings: ['defaultHeaderType' => 4]));
        self::assertStringContainsString('<h5>Default</h5>', $this->render(false, settings: ['defaultHeaderType' => 4]));
    }

    #[Test]
    public function onlyAnExplicitEditMarksTheHeadingForThePageModule(): void
    {
        $html = $this->render(true);

        self::assertStringContainsString('<h4 class="ce-headline-layout-2" data-headliner-edit="tt_content:12:header_tag" data-headliner-element="12">Marked</h4>', $html);
        // the variable data has a content element, and still nothing is taken from it
        self::assertStringContainsString('<h5 class="ce-headline-layout-2" data-headliner-element="12">Unmarked</h5>', $html);
        self::assertSame(1, substr_count($html, 'data-headliner-edit'));
    }

    #[Test]
    public function aVisitorNeverGetsAMark(): void
    {
        self::assertStringNotContainsString('data-headliner', $this->render(false));
    }

    #[Test]
    public function aSubheaderIsItsOwnTagOrBelowTheHeaderOfItsParent(): void
    {
        $html = $this->render(false, ['header_tag' => 'h4', 'header_layout' => 3]);

        self::assertStringContainsString('<h3 class="ce-headline-layout-3">Own</h3>', $html);
        // the layout of the parent, as the subheader looks like its header
        self::assertStringContainsString('<h5 class="ce-headline-layout-2">Below</h5>', $html);
        self::assertStringContainsString('<h6 class="ce-headline-layout-3">Offset</h6>', $html);
    }

    #[Test]
    public function aHeadingWithoutATagIsBelowItsParentAndAChosenTagWins(): void
    {
        $html = $this->render(false, ['header_tag' => 'h4']);

        self::assertStringContainsString('<h5>Under</h5>', $html);
        self::assertStringContainsString('<h2>Chosen</h2>', $html);
    }
}
