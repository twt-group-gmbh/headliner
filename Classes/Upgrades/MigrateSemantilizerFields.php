<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Upgrades;

use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\RepeatableInterface;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Takes the field of the Semantilizer (z7_semantilizer) into the field of headliner: the type of the header
 * (`header_type`, 0 to 6) becomes the tag of the header (`header_tag`). The layout (`header_layout`) is the same field in
 * both and stays as it is. The Semantilizer has no field for the subheader, so the subheader is not touched.
 *
 * It only fills what is empty, which is what the field is until somebody chooses a tag: a tag in it, also an h2, was chosen
 * after headliner was installed and is kept. It works on the column `header_type` of the table, which stays in the database after the Semantilizer is
 * removed (until the unused columns are deleted), so it can run after it. It can run again.
 */
#[UpgradeWizard('headlinerMigrateSemantilizerFields')]
final class MigrateSemantilizerFields implements UpgradeWizardInterface, RepeatableInterface
{
    private const TABLE = 'tt_content';

    /** The column the Semantilizer adds */
    private const TYPE_COLUMN = 'header_type';

    public function getTitle(): string
    {
        return 'Headliner: take the heading types of the Semantilizer (z7_semantilizer) into the tags of headliner';
    }

    public function getDescription(): string
    {
        return 'Writes the field "header_type" of the Semantilizer (0 to 6) into the tag of the header ("header_tag", h1 to h6, '
            . 'div for none). Tags that were chosen already are kept. The layout ("header_layout") is not changed.';
    }

    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class];
    }

    public function updateNecessary(): bool
    {
        if (!$this->hasTypeColumn()) {
            return false;
        }
        foreach (SemantilizerMapping::TYPES as $type) {
            if ((int) $this->query($type)->count('uid')->executeQuery()->fetchOne() > 0) {
                return true;
            }
        }

        return false;
    }

    public function executeUpdate(): bool
    {
        if (!$this->hasTypeColumn()) {
            return true;
        }
        foreach (SemantilizerMapping::TYPES as $type) {
            $this->query($type)->update(self::TABLE)->set('header_tag', (string) SemantilizerMapping::headerTag($type))->executeStatement();
        }

        return true;
    }

    private function hasTypeColumn(): bool
    {
        return $this->connectionPool()->getConnectionForTable(self::TABLE)->createSchemaManager()
            ->introspectTable(self::TABLE)->hasColumn(self::TYPE_COLUMN);
    }

    /**
     * The rows of one type that still have to be changed: the tag is empty (nobody chose one) and not the tag of the type
     * already.
     */
    private function query(int $type): QueryBuilder
    {
        $tag = (string) SemantilizerMapping::headerTag($type);

        $queryBuilder = $this->connectionPool()->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $expr = $queryBuilder->expr();
        $queryBuilder->from(self::TABLE)->where(
            $expr->eq(self::TYPE_COLUMN, $queryBuilder->createNamedParameter($type, Connection::PARAM_INT)),
            $expr->or(
                $expr->eq('header_tag', $queryBuilder->createNamedParameter('')),
                $expr->isNull('header_tag')
            ),
            $expr->or($expr->isNull('header_tag'), $expr->neq('header_tag', $queryBuilder->createNamedParameter($tag)))
        );

        return $queryBuilder;
    }

    private function connectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }
}
