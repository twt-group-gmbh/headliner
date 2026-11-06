<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Configuration;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The fields of the content elements: TCA, palettes and the columns the core derives from the TCA.
 */
final class HeaderTagFieldsTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['fluid_styled_content'];

    protected array $testExtensionsToLoad = ['twtgroup/headliner'];

    /**
     * @param array<string, mixed> $column
     * @return list<string>
     */
    private function values(array $column): array
    {
        return array_values(array_map(static fn(array $item): string => (string) $item['value'], $column['config']['items']));
    }

    #[Test]
    public function theTagFieldOffersTheHeadingTagsAndDivAndTheLayoutAsTheDefault(): void
    {
        $column = $GLOBALS['TCA']['tt_content']['columns']['header_tag'];

        // the empty choice is "from the layout": what content did before it had a tag. It is what a new element has, and what
        // the elements that were there before the extension was installed have.
        self::assertSame(['', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div'], $this->values($column));
        self::assertSame('', $column['config']['default']);
        self::assertTrue($column['exclude']);
    }

    #[Test]
    public function theSubheaderTagInheritsTheChoicesOfTheHeaderTag(): void
    {
        $columns = $GLOBALS['TCA']['tt_content']['columns'];

        self::assertSame($this->values($columns['header_tag']), $this->values($columns['subheader_tag']));
        // empty: one level below the header
        self::assertSame('', $columns['subheader_tag']['config']['default']);
        self::assertTrue($columns['subheader_tag']['exclude']);
        // the layout of the header decides how the subheader looks, there is no layout of its own
        self::assertArrayNotHasKey('subheader_layout', $columns);
    }

    #[Test]
    public function theTagComesBeforeTheLayoutInTheHeaderPalettes(): void
    {
        foreach (['header', 'headers'] as $palette) {
            $showItem = (string) preg_replace('/\s+/', '', (string) $GLOBALS['TCA']['tt_content']['palettes'][$palette]['showitem']);

            self::assertStringContainsString('header_tag,header_layout', $showItem, $palette);
        }
    }

    #[Test]
    public function theSubheaderTagIsOnlyInThePaletteWithASubheader(): void
    {
        $palettes = $GLOBALS['TCA']['tt_content']['palettes'];

        self::assertStringContainsString('subheader_tag', (string) $palettes['headers']['showitem']);
        self::assertStringNotContainsString('subheader_layout', (string) $palettes['headers']['showitem']);
        self::assertStringNotContainsString('subheader_tag', (string) $palettes['header']['showitem']);
    }

    #[Test]
    public function theCoreCreatesTheColumnsFromTheTca(): void
    {
        $table = $this->getConnectionPool()->getConnectionForTable('tt_content')->createSchemaManager()->introspectTable('tt_content');

        foreach (['header_tag', 'subheader_tag'] as $column) {
            self::assertTrue($table->hasColumn($column), $column);
        }
    }

    #[Test]
    public function theColumnsHaveNoTagUntilSomebodyChoosesOne(): void
    {
        // Elements that exist when the extension is installed get the default of the new column. It must be empty: with a
        // tag in it, the layout of every one of them (h3 for layout 3) would be lost.
        $table = $this->getConnectionPool()->getConnectionForTable('tt_content')->createSchemaManager()->introspectTable('tt_content');

        self::assertSame('', $table->getColumn('header_tag')->getDefault());
        self::assertSame('', $table->getColumn('subheader_tag')->getDefault());
    }

    #[Test]
    public function aRowThatSetsNoTagHasNone(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $connection->insert('tt_content', ['uid' => 1, 'pid' => 1, 'CType' => 'text', 'header' => 'Older', 'header_layout' => 3]);

        $row = $connection->select(['header_tag', 'subheader_tag'], 'tt_content', ['uid' => 1])->fetchAssociative();

        self::assertSame(['header_tag' => '', 'subheader_tag' => ''], $row);
    }
}
