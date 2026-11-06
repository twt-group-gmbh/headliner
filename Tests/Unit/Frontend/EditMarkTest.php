<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Frontend;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Frontend\EditMark;

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
}
