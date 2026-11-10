<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Configuration;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The fields of the content elements without fluid_styled_content: the TCA does not need it, and the core derives the
 * columns from it.
 */
final class HeaderTagFieldsWithoutFluidStyledContentTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [];

    protected array $testExtensionsToLoad = ['twtgroup/headliner'];

    #[Test]
    public function theFieldsAreThereAndInThePalettesOfTheCore(): void
    {
        $tca = $GLOBALS['TCA']['tt_content'];

        self::assertSame(['', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div'], array_map(static fn(array $item): string => (string) $item['value'], $tca['columns']['header_tag']['config']['items']));
        self::assertTrue($tca['columns']['subheader_tag']['exclude']);
        self::assertStringContainsString('header_tag', (string) $tca['palettes']['headers']['showitem']);
        self::assertStringContainsString('subheader_tag', (string) $tca['palettes']['headers']['showitem']);
    }

    #[Test]
    public function theCoreCreatesTheColumnsFromTheTca(): void
    {
        $table = $this->getConnectionPool()->getConnectionForTable('tt_content')->createSchemaManager()->introspectTable('tt_content');

        self::assertTrue($table->hasColumn('header_tag'));
        self::assertTrue($table->hasColumn('subheader_tag'));
    }
}
