<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Frontend;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Frontend\EditMark;
use TYPO3\CMS\Core\Domain\RawRecord;
use TYPO3\CMS\Core\Domain\Record;
use TYPO3\CMS\Core\Domain\Record\ComputedProperties;

final class EditMarkTest extends TestCase
{
    #[Test]
    public function namesTheRecordAndTheFieldOfAContentElement(): void
    {
        self::assertSame('tt_content:12:header_tag', EditMark::forContent(['uid' => 12, 'CType' => 'text'], 'header_tag'));
        self::assertSame('tt_content:12:subheader_tag', EditMark::forContent(['uid' => '12', 'CType' => 'text'], 'subheader_tag'));
    }

    #[Test]
    public function aTranslationIsNamedByItsOwnUid(): void
    {
        // the frontend gives the original with the uid of the translation in _LOCALIZED_UID: that is what is edited
        self::assertSame(
            'tt_content:40:header_tag',
            EditMark::forContent(['uid' => 12, '_LOCALIZED_UID' => 40, 'CType' => 'text'], 'header_tag')
        );
    }

    #[Test]
    public function nothingIsNamedForDataThatIsNoContentElement(): void
    {
        self::assertNull(EditMark::forContent(null, 'header_tag'));
        self::assertNull(EditMark::forContent('text', 'header_tag'));
        self::assertNull(EditMark::forContent(['uid' => 0, 'CType' => 'text'], 'header_tag'));
        self::assertNull(EditMark::forContent(['title' => 'a page'], 'header_tag'));
        self::assertNull(EditMark::forContent(['uid' => 3], 'header_tag'));
    }

    /**
     * A content element as the record object a Content Block (or any template that gets records) is given.
     */
    private static function record(string $type, int $uid, ?int $localizedUid = null): Record
    {
        $raw = new RawRecord($uid, 1, ['CType' => 'text'], new ComputedProperties(localizedUid: $localizedUid), $type);

        return new Record($raw, ['CType' => 'text']);
    }

    #[Test]
    public function namesTheRecordOfARecordObject(): void
    {
        self::assertSame('tt_content:12:header_tag', EditMark::forContent(self::record('tt_content.text', 12), 'header_tag'));
        self::assertSame('tt_content:12:subheader_tag', EditMark::forContent(self::record('tt_content', 12), 'subheader_tag'));
    }

    #[Test]
    public function aTranslatedRecordObjectIsNamedByTheUidOfItsTranslation(): void
    {
        self::assertSame('tt_content:40:header_tag', EditMark::forContent(self::record('tt_content.text', 12, 40), 'header_tag'));
    }

    #[Test]
    public function aRecordOfAnotherTableIsNotNamed(): void
    {
        // the box edits content elements; the record of a collection (a card) lives in a table of its own
        self::assertNull(EditMark::forContent(self::record('cards', 7), 'header_tag'));
        self::assertNull(EditMark::forContent(self::record('pages', 1), 'header_tag'));
    }

    #[Test]
    public function namesTheContentElementItself(): void
    {
        self::assertSame(12, EditMark::elementUid(['uid' => 12, 'CType' => 'text']));
        self::assertSame(40, EditMark::elementUid(['uid' => 12, '_LOCALIZED_UID' => 40, 'CType' => 'text']));
        self::assertSame(12, EditMark::elementUid(self::record('tt_content.text', 12)));
        self::assertNull(EditMark::elementUid(self::record('cards', 7)));
        self::assertNull(EditMark::elementUid(null));
    }
}
