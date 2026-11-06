<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Frontend;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Middleware\InspectRequest;
use Twtgroup\Headliner\Tests\Functional\AbstractFrontendTestCase;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The page module renders a page with the mark "headliner.inspect"; only then the headers say which record they come
 * from. That must never reach a visitor.
 */
final class InspectionTest extends AbstractFrontendTestCase
{
    private function inspect(): InternalRequest
    {
        return (new InternalRequest('https://website.local/'))->withAttribute(InspectRequest::ATTRIBUTE, true);
    }

    #[Test]
    public function aVisitorGetsNoMarks(): void
    {
        $this->writeSite();

        self::assertStringNotContainsString('data-headliner-edit', $this->render());
    }

    #[Test]
    public function aRequestFromOutsideCannotSwitchTheMarksOnWithAHeader(): void
    {
        $this->writeSite();
        $request = (new InternalRequest('https://website.local/'))->withHeader('X-Headliner-Inspect', 'anything');

        self::assertStringNotContainsString('data-headliner-edit', $this->render($request));
    }

    #[Test]
    public function theMarkedRequestSaysWhichRecordEveryHeaderComesFrom(): void
    {
        $this->writeSite();
        $html = $this->render($this->inspect());

        self::assertStringContainsString('<h4 class="ce-headline-layout-2" data-headliner-edit="tt_content:1:header_tag">Chosen tag</h4>', $html);
        // a div can be turned back into a heading, so it is marked too
        self::assertStringContainsString('<div class="ce-headline-layout-3" data-headliner-edit="tt_content:2:header_tag">Only a div</div>', $html);
        self::assertStringContainsString('data-headliner-edit="tt_content:4:subheader_tag">Own subheader</h5>', $html);
    }

    #[Test]
    public function withTheSubheaderFieldsSwitchedOffTheSubheaderCannotBeEdited(): void
    {
        $this->writeSite(['headliner' => ['subheader' => ['enabled' => false]]]);
        $html = $this->render($this->inspect());

        self::assertStringContainsString('data-headliner-edit="tt_content:4:header_tag"', $html);
        self::assertStringNotContainsString('subheader_tag"', $html);
    }

    #[Test]
    public function theMarkedPageNeverEndsUpInThePageCache(): void
    {
        $this->writeSite();
        $this->render($this->inspect());

        // the next visitor must not get the marks, whatever was rendered before
        self::assertStringNotContainsString('data-headliner-edit', $this->render());
    }

    #[Test]
    public function aVisitorGetsNothingOfAHiddenPage(): void
    {
        $this->writeSite();

        $response = $this->executeFrontendSubRequest(new InternalRequest('https://website.local/not-published'));

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function thePageModuleCanRenderAHiddenPageToShowItsStructure(): void
    {
        $this->writeSite();

        $html = $this->render((new InternalRequest('https://website.local/not-published'))->withAttribute(InspectRequest::ATTRIBUTE, true));

        self::assertStringContainsString('<h3 class="ce-headline-layout-2" data-headliner-edit="tt_content:7:header_tag">On a hidden page</h3>', $html);
    }

    #[Test]
    public function aHiddenPageIsStillGoneForTheNextVisitor(): void
    {
        $this->writeSite();
        $this->render((new InternalRequest('https://website.local/not-published'))->withAttribute(InspectRequest::ATTRIBUTE, true));

        $response = $this->executeFrontendSubRequest(new InternalRequest('https://website.local/not-published'));

        self::assertSame(404, $response->getStatusCode());
    }
}
