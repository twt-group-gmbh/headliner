<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Upgrades;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Upgrades\MigrateSemantilizerFields;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The upgrade wizard that takes the field of the Semantilizer (header_type) into the tags of headliner.
 */
final class MigrateSemantilizerFieldsTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['fluid_styled_content'];

    protected array $testExtensionsToLoad = ['twtgroup/headliner'];

    protected function setUp(): void
    {
        parent::setUp();
        // the database is shared by the tests of this class: start without the column and without rows
        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $connection->truncate('tt_content');
        if ($connection->createSchemaManager()->introspectTable('tt_content')->hasColumn('header_type')) {
            $connection->executeStatement('ALTER TABLE tt_content DROP COLUMN header_type');
        }
    }

    /** The column the Semantilizer adds (ext_tables.sql of z7_semantilizer) */
    private function addSemantilizerColumn(): void
    {
        $this->getConnectionPool()->getConnectionForTable('tt_content')
            ->executeStatement('ALTER TABLE tt_content ADD header_type INT(1) DEFAULT 2 NOT NULL');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insert(int $uid, array $row): void
    {
        $this->getConnectionPool()->getConnectionForTable('tt_content')
            ->insert('tt_content', ['uid' => $uid, 'pid' => 1, 'CType' => 'text'] + $row);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $uid): array
    {
        $row = $this->getConnectionPool()->getConnectionForTable('tt_content')
            ->select(['header_tag', 'subheader_tag', 'header_layout'], 'tt_content', ['uid' => $uid])->fetchAssociative();
        self::assertIsArray($row);

        return $row;
    }

    #[Test]
    public function thereIsNothingToDoWithoutTheFieldOfTheSemantilizer(): void
    {
        self::assertFalse((new MigrateSemantilizerFields())->updateNecessary());
        // and running it does no harm
        self::assertTrue((new MigrateSemantilizerFields())->executeUpdate());
    }

    #[Test]
    public function takesTheTypeIntoTheTagOfTheHeader(): void
    {
        $this->addSemantilizerColumn();
        foreach ([0, 1, 2, 3, 4, 5, 6] as $uid => $type) {
            $this->insert($uid + 1, ['header_type' => $type, 'header' => 'H', 'header_tag' => 'h2']);
        }
        $wizard = new MigrateSemantilizerFields();

        self::assertTrue($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());

        self::assertSame(['div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'], array_map(fn(int $uid): string => $this->row($uid)['header_tag'], range(1, 7)));
        // the layout, how it looks, stays as it was
        self::assertSame(0, (int) $this->row(1)['header_layout']);
        // nothing is left to do
        self::assertFalse($wizard->updateNecessary());
    }

    #[Test]
    public function aTagThatAnEditorSetAfterwardsIsNotTouched(): void
    {
        $this->addSemantilizerColumn();
        // h4 is not the default of the field, so somebody chose it
        $this->insert(1, ['header_type' => 1, 'header' => 'H', 'header_tag' => 'h4']);
        $this->insert(2, ['header_type' => 3, 'header' => 'H', 'header_tag' => '']);

        (new MigrateSemantilizerFields())->executeUpdate();

        self::assertSame('h4', $this->row(1)['header_tag']);
        // an empty tag is filled
        self::assertSame('h3', $this->row(2)['header_tag']);
    }

    #[Test]
    public function leavesTheSubheaderAlone(): void
    {
        // the Semantilizer has no field for the subheader, so there is nothing to take over
        $this->addSemantilizerColumn();
        $this->insert(1, ['header_type' => 1, 'header' => 'H', 'subheader' => 'S', 'header_tag' => 'h2', 'subheader_tag' => 'h3']);
        $this->insert(2, ['header_type' => 6, 'header' => 'H', 'subheader' => 'S', 'header_tag' => 'h2', 'subheader_tag' => '']);
        $this->insert(3, ['header_type' => 3, 'header' => 'H', 'subheader' => 'S', 'header_tag' => 'h2', 'subheader_tag' => 'h5']);

        (new MigrateSemantilizerFields())->executeUpdate();

        self::assertSame('h3', $this->row(1)['subheader_tag']);
        self::assertSame('', $this->row(2)['subheader_tag']);
        self::assertSame('h5', $this->row(3)['subheader_tag']);
        // while the header of the same rows was taken over
        self::assertSame('h1', $this->row(1)['header_tag']);
        self::assertSame('h6', $this->row(2)['header_tag']);
    }

    #[Test]
    public function aRowWhoseOnlyChangeWouldBeTheSubheaderNeedsNoUpdate(): void
    {
        // the header is the type already (h2), the subheader is none of its business
        $this->addSemantilizerColumn();
        $this->insert(1, ['header_type' => 2, 'header' => 'H', 'subheader' => 'S', 'header_tag' => 'h2', 'subheader_tag' => 'h3']);

        self::assertFalse((new MigrateSemantilizerFields())->updateNecessary());
    }

    #[Test]
    public function canRunAgainAndChangesNothingTheSecondTime(): void
    {
        $this->addSemantilizerColumn();
        $this->insert(1, ['header_type' => 3, 'header' => 'H', 'header_tag' => 'h2']);
        $wizard = new MigrateSemantilizerFields();
        $wizard->executeUpdate();

        self::assertTrue($wizard->executeUpdate());
        self::assertSame('h3', $this->row(1)['header_tag']);
    }

    #[Test]
    public function hasATitleADescriptionAndNeedsTheDatabaseUpToDate(): void
    {
        $wizard = new MigrateSemantilizerFields();

        self::assertStringContainsString('Semantilizer', $wizard->getTitle());
        self::assertStringContainsString('header_type', $wizard->getDescription());
        self::assertStringNotContainsString('subheader', $wizard->getDescription());
        self::assertContains(\TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite::class, $wizard->getPrerequisites());
    }
}
