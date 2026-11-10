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

    #[Test]
    public function namesAnyRecordObject(): void
    {
        self::assertSame('cards:7', EditMark::recordSource(self::record('cards', 7)));
        self::assertSame('tt_content:12', EditMark::recordSource(self::record('tt_content.text', 12)));
        // a translation is named by its own uid
        self::assertSame('cards:40', EditMark::recordSource(self::record('cards', 7, 40)));
    }

    #[Test]
    public function aRecordIsOnlyNamedForARecordObject(): void
    {
        self::assertNull(EditMark::recordSource(null));
        // a row as an array is no record: it is named with rowSource() and its table
        self::assertNull(EditMark::recordSource(['uid' => 7, 'CType' => 'text']));
        self::assertNull(EditMark::recordSource('cards'));
    }

    #[Test]
    public function anArrayIsNamedWithTheTableThatIsGiven(): void
    {
        self::assertSame('cards:7', EditMark::rowSource(['uid' => 7, 'title' => 'A'], 'cards'));
        // the frontend gives the original of a translation, with the uid of the translation beside it
        self::assertSame('cards:70', EditMark::rowSource(['uid' => 7, '_LOCALIZED_UID' => 70], 'cards'));
    }

    #[Test]
    public function anArrayIsNotNamedWithoutATableOrAUid(): void
    {
        self::assertNull(EditMark::rowSource(['uid' => 7], ''));
        self::assertNull(EditMark::rowSource(['title' => 'A'], 'cards'));
        self::assertNull(EditMark::rowSource(['uid' => 0], 'cards'));
        // a table is a TCA name, nothing that could break out of the attribute
        self::assertNull(EditMark::rowSource(['uid' => 7], 'Cards"><x'));
    }

    #[Test]
    public function namesTheTagFieldOfARecordOfAnyTable(): void
    {
        $card = new Record(new RawRecord(7, 1, ['tag' => 'h3'], new ComputedProperties(localizedUid: 70), 'cards'), ['tag' => 'h3']);

        self::assertSame('cards:70:tag', EditMark::forRecord($card, 'tag'));
        self::assertSame('tt_content:12:header_tag', EditMark::forRecord(new Record(new RawRecord(12, 1, ['header_tag' => 'h2'], new ComputedProperties(), 'tt_content.text'), ['header_tag' => 'h2']), 'header_tag'));
    }

    #[Test]
    public function namesNothingForAFieldTheRecordDoesNotHave(): void
    {
        $card = new Record(new RawRecord(7, 1, ['title' => 'A'], new ComputedProperties(), 'cards'), ['title' => 'A']);

        self::assertNull(EditMark::forRecord($card, 'tag'));
        self::assertNull(EditMark::forRecord(['uid' => 7, 'tag' => 'h3'], 'tag'));
        self::assertNull(EditMark::forRecord(null, 'tag'));
    }
}
