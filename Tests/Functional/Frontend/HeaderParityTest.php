<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Frontend;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Tests\Functional\AbstractFrontendTestCase;

/**
 * Content from before the extension has no tag (the field is empty): the site set must not change its tags. The same
 * elements are rendered with the partial of fluid_styled_content and with the one of the extension.
 */
final class HeaderParityTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $connection->truncate('tt_content');
        foreach ([0, 1, 2, 3, 4, 5, 6] as $layout) {
            $connection->insert('tt_content', [
                'uid' => 100 + $layout, 'pid' => 1, 'CType' => 'text', 'colPos' => 0, 'sorting' => 100 + $layout,
                'header' => 'Header ' . $layout, 'header_layout' => $layout, 'header_tag' => '',
                'subheader' => 'Sub ' . $layout, 'subheader_tag' => '', 'bodytext' => 'x',
            ]);
        }
    }

    /**
     * @return array<string, string> The tag of every header and subheader, by its text
     */
    private function tags(string $html): array
    {
        preg_match_all('#<(h[1-6]|div)[^>]*>\s*(?:<a[^>]*>)?\s*((?:Header|Sub) \d)#', $html, $matches, PREG_SET_ORDER);

        return array_column(array_map(static fn(array $m): array => [$m[2], $m[1]], $matches), 1, 0);
    }

    /**
     * @return array{array<string, string>, array<string, string>} The tags with the partial of fluid_styled_content, and with ours
     */
    private function bothWays(): array
    {
        $this->writeSite([], null, ['typo3/fluid-styled-content']);
        $core = $this->tags($this->render());
        $this->writeSite();

        return [$core, $this->tags($this->render())];
    }

    #[Test]
    public function contentWithoutATagKeepsTheTagsOfFluidStyledContent(): void
    {
        [$core, $ours] = $this->bothWays();

        self::assertCount(14, $core);
        // every header, and the subheaders of the layouts 0 to 5: the same tags as before the extension
        foreach ([0, 1, 2, 3, 4, 5, 6] as $layout) {
            self::assertSame($core['Header ' . $layout], $ours['Header ' . $layout], 'header of the layout ' . $layout);
        }
        foreach ([0, 1, 2, 3, 4, 5] as $layout) {
            self::assertSame($core['Sub ' . $layout], $ours['Sub ' . $layout], 'subheader of the layout ' . $layout);
        }
    }

    #[Test]
    public function theSubheaderOfTheLayout6IsTheOneDifference(): void
    {
        [$core, $ours] = $this->bothWays();

        // fluid_styled_content has nothing below h6 and falls back to its default layout; the extension makes a div
        self::assertSame('h3', $core['Sub 6']);
        self::assertSame('div', $ours['Sub 6']);
    }
}
