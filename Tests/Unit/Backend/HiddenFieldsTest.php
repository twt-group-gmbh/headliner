<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Backend;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Backend\HiddenFields;

final class HiddenFieldsTest extends TestCase
{
    /**
     * The array the page TSconfig parser makes of "TCEFORM.tt_content.header_tag.disabled = 1" (keys with a dot for a
     * level).
     *
     * @return array<string, mixed>
     */
    private function tsConfig(): array
    {
        return [
            'TCEFORM.' => [
                'tt_content.' => [
                    'header_tag.' => ['disabled' => '1'],
                    'subheader_tag.' => ['types.' => ['text.' => ['disabled' => '1']]],
                    'bodytext.' => ['disabled' => '1', 'types.' => ['header.' => ['disabled' => '0']]],
                ],
            ],
        ];
    }

    #[Test]
    public function aFieldThatIsNotMentionedIsNotHidden(): void
    {
        $hidden = new HiddenFields($this->tsConfig());

        self::assertFalse($hidden->isHidden('tt_content', 'header_layout', 'text'));
        self::assertFalse($hidden->isHidden('tx_other', 'header_tag', 'text'));
        self::assertFalse((new HiddenFields([]))->isHidden('tt_content', 'header_tag', 'text'));
    }

    #[Test]
    public function aFieldDisabledWithoutATypeIsHiddenForEveryType(): void
    {
        $hidden = new HiddenFields($this->tsConfig());

        self::assertTrue($hidden->isHidden('tt_content', 'header_tag', 'text'));
        self::assertTrue($hidden->isHidden('tt_content', 'header_tag', 'camino_hero'));
        self::assertTrue($hidden->isHidden('tt_content', 'header_tag', ''));
    }

    #[Test]
    public function aFieldDisabledForOneTypeIsHiddenOnlyThere(): void
    {
        $hidden = new HiddenFields($this->tsConfig());

        self::assertTrue($hidden->isHidden('tt_content', 'subheader_tag', 'text'));
        self::assertFalse($hidden->isHidden('tt_content', 'subheader_tag', 'header'));
    }

    #[Test]
    public function aTypeCanShowAgainWhatIsHiddenForAllTypes(): void
    {
        $hidden = new HiddenFields($this->tsConfig());

        // like the core: TCEFORM.[table].[field].types.[type] is merged over TCEFORM.[table].[field]
        self::assertTrue($hidden->isHidden('tt_content', 'bodytext', 'text'));
        self::assertFalse($hidden->isHidden('tt_content', 'bodytext', 'header'));
    }

    #[Test]
    public function zeroAndAnEmptyValueDoNotHide(): void
    {
        $hidden = new HiddenFields(['TCEFORM.' => ['tt_content.' => ['header_tag.' => ['disabled' => '0'], 'subheader_tag.' => ['disabled' => '']]]]);

        self::assertFalse($hidden->isHidden('tt_content', 'header_tag', 'text'));
        self::assertFalse($hidden->isHidden('tt_content', 'subheader_tag', 'text'));
    }
}
