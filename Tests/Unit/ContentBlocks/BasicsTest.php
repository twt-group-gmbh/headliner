<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\ContentBlocks;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The Basics for Content Blocks that the extension ships. Content Blocks is not a requirement, so nothing here can load
 * it: the files are checked for what a block needs from them.
 */
final class BasicsTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function basic(string $file): array
    {
        $basic = Yaml::parseFile(__DIR__ . '/../../../ContentBlocks/Basics/' . $file);
        self::assertIsArray($basic);

        return $basic;
    }

    /**
     * The fields of the palette of a Basic.
     *
     * @param array<string, mixed> $basic
     * @return array<string, array<string, mixed>> By identifier ('' for a field without one)
     */
    private static function paletteFields(array $basic): array
    {
        $fields = [];
        foreach ($basic['fields'][0]['fields'] as $index => $field) {
            $fields[$field['identifier'] ?? '#' . $index . ':' . $field['type']] = $field;
        }

        return $fields;
    }

    #[Test]
    public function theHeadlineHasTheHeaderItsTagAndItsLayoutInOnePalette(): void
    {
        $basic = self::basic('Headline.yaml');

        self::assertSame('Twtgroup/Headline', $basic['identifier']);
        self::assertSame('Palette', $basic['fields'][0]['type']);
        $fields = self::paletteFields($basic);
        self::assertSame(['header', '#1:Linebreak', 'header_tag', 'header_layout'], array_keys($fields));
        foreach (['header', 'header_tag', 'header_layout'] as $identifier) {
            self::assertTrue($fields[$identifier]['useExistingField'], $identifier);
        }
    }

    #[Test]
    public function theSubheadlineHasTheSubheaderAndItsTagWithoutALinebreak(): void
    {
        $basic = self::basic('Subheadline.yaml');

        self::assertSame('Twtgroup/Subheadline', $basic['identifier']);
        self::assertSame(['subheader', 'subheader_tag'], array_keys(self::paletteFields($basic)));
    }

    #[Test]
    public function theTagFieldsNameTheirType(): void
    {
        // Content Blocks knows the fields of the base TCA when it compiles a block, not the ones an extension adds in its
        // TCA overrides, so a tag field without its type makes the block fail to compile
        self::assertSame('Select', self::paletteFields(self::basic('Headline.yaml'))['header_tag']['type']);
        self::assertSame('Select', self::paletteFields(self::basic('Subheadline.yaml'))['subheader_tag']['type']);
    }

    #[Test]
    public function theFieldsOfTheCoreNeedNoTypeBecauseTheTcaOfTheCoreHasThem(): void
    {
        $fields = self::paletteFields(self::basic('Headline.yaml'));

        self::assertArrayNotHasKey('type', $fields['header']);
        self::assertArrayNotHasKey('type', $fields['header_layout']);
    }
}
