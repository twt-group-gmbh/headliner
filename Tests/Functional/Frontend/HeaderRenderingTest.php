<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Frontend;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Tests\Functional\AbstractFrontendTestCase;

/**
 * Renders the frontend through the real application, with the site set of the extension and fluid_styled_content.
 */
final class HeaderRenderingTest extends AbstractFrontendTestCase
{
    #[Test]
    public function theTagComesFromTheFieldAndTheLayoutOnlySetsTheClass(): void
    {
        $this->writeSite();
        $html = $this->render();

        self::assertStringContainsString('<h4 class="ce-headline-layout-2">Chosen tag</h4>', $html);
    }

    #[Test]
    public function aDivHeaderIsNoHeading(): void
    {
        $this->writeSite();
        $html = $this->render();

        self::assertStringContainsString('<div class="ce-headline-layout-3">Only a div</div>', $html);
        self::assertDoesNotMatchRegularExpression('#<h[1-6][^>]*>Only a div#', $html);
    }

    #[Test]
    public function contentWithoutATagKeepsTheLayoutAsTheTag(): void
    {
        $this->writeSite();
        $html = $this->render();

        self::assertStringContainsString('<h3 class="ce-headline-layout-3">Older content</h3>', $html);
    }

    #[Test]
    public function theSubheaderHasItsOwnTagAndLayout(): void
    {
        $this->writeSite();
        $html = $this->render();

        self::assertStringContainsString('<h2 class="ce-headline-layout-2">With subheader</h2>', $html);
        self::assertStringContainsString('<h5 class="ce-headline-layout-4">Own subheader</h5>', $html);
    }

    #[Test]
    public function theHiddenSubheaderLayoutHidesTheSubheader(): void
    {
        $this->writeSite();
        $html = $this->render();

        self::assertStringContainsString('Hidden subheader</h2>', $html);
        self::assertStringNotContainsString('Gone', $html);
    }

    #[Test]
    public function theHiddenHeaderLayoutHidesTheWholeHeader(): void
    {
        $this->writeSite();
        $html = $this->render();

        self::assertStringNotContainsString('Hidden header', $html);
        self::assertStringNotContainsString('Only the subheader', $html);
    }

    #[Test]
    public function withTheSubheaderFieldsSwitchedOffTheSubheaderFollowsTheHeader(): void
    {
        $this->writeSite(['headliner' => ['subheader' => ['enabled' => false]]]);
        $html = $this->render();

        // header h2 (layout 2), so the subheader is an h3 with the layout class of the header, not h5 / layout 4
        self::assertStringContainsString('<h3 class="ce-headline-layout-2">Own subheader</h3>', $html);
        self::assertStringNotContainsString('<h5', $html);
        // the subheader layout "Hidden" of the field is not looked at when the fields are off
        self::assertStringContainsString('Gone', $html);
    }
}
