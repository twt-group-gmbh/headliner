<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Backend;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Backend\TagField;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Which fields the box may change as the tag of a heading. The mark with the field is part of the rendered page, which
 * an editor can write to (in a rich text, say), so the box must not take its word for it.
 */
final class TagFieldTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['fluid_styled_content'];

    protected array $testExtensionsToLoad = ['twtgroup/headliner'];

    private function tagField(): TagField
    {
        return new TagField($this->get(TcaSchemaFactory::class));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function fieldsProvider(): iterable
    {
        yield 'the tag of the header' => ['tt_content', 'header_tag', true];
        yield 'the tag of the subheader' => ['tt_content', 'subheader_tag', true];
        yield 'the type of the element' => ['tt_content', 'CType', false];
        yield 'the text of the header' => ['tt_content', 'header', false];
        yield 'the layout of the header' => ['tt_content', 'header_layout', false];
        yield 'a password' => ['be_users', 'password', false];
        yield 'a field that does not exist' => ['tt_content', 'nothing', false];
        yield 'a table that does not exist' => ['no_such_table', 'tag', false];
    }

    #[Test]
    #[DataProvider('fieldsProvider')]
    public function onlyASelectOfTagsIsATagField(string $table, string $field, bool $expected): void
    {
        self::assertSame($expected, $this->tagField()->is($table, $field));
    }

    #[Test]
    public function offersTheTagsTheFieldKnows(): void
    {
        self::assertSame(['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div'], $this->tagField()->options('tt_content', 'header_tag'));
        self::assertSame([], $this->tagField()->options('be_users', 'password'));
    }
}
