<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Upgrades;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Upgrades\MigrateSemantilizerFields;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The upgrade wizard on an instance without fluid_styled_content: it works on the table tt_content of the core.
 */
final class MigrateSemantilizerFieldsWithoutFluidStyledContentTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [];

    protected array $testExtensionsToLoad = ['twtgroup/headliner'];

    #[Test]
    public function takesTheTypeIntoTheTagOfTheHeader(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $connection->executeStatement('ALTER TABLE tt_content ADD header_type INT(1) DEFAULT 2 NOT NULL');
        $connection->insert('tt_content', ['uid' => 1, 'pid' => 1, 'CType' => 'text', 'header' => 'H', 'header_type' => 3, 'header_tag' => '']);
        $connection->insert('tt_content', ['uid' => 2, 'pid' => 1, 'CType' => 'text', 'header' => 'H', 'header_type' => 4, 'header_tag' => 'h2']);
        $wizard = new MigrateSemantilizerFields();

        self::assertTrue($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());

        $tags = $connection->select(['uid', 'header_tag'], 'tt_content', [], [], ['uid' => 'ASC'])->fetchAllKeyValue();
        // an empty tag is filled, one that somebody chose is kept
        self::assertSame([1 => 'h3', 2 => 'h2'], $tags);
    }
}
