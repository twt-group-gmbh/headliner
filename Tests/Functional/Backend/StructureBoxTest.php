<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Backend;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Tests\Functional\AbstractFrontendTestCase;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * The box with the heading structure, as the page module loads it.
 */
final class StructureBoxTest extends AbstractFrontendTestCase
{
    /** @var array<string, mixed> */
    private array $originalLang = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLang = array_key_exists('LANG', $GLOBALS) ? ['LANG' => $GLOBALS['LANG']] : [];
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/BackendUsers.csv');
        $this->writeSite();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');
        $GLOBALS['EXEC_TIME'] = time();
    }

    protected function tearDown(): void
    {
        if ($this->originalLang === []) {
            unset($GLOBALS['LANG']);
        } else {
            $GLOBALS['LANG'] = $this->originalLang['LANG'];
        }
        parent::tearDown();
    }

    #[Test]
    public function aStandardPageOfASiteHasABox(): void
    {
        self::assertTrue($this->structureBox()->supports(1));
    }

    #[Test]
    public function aPageThatDoesNotExistHasNoBox(): void
    {
        self::assertFalse($this->structureBox()->supports(9999));
    }

    #[Test]
    public function drawsTheStructureOfThePageWithItsProblemsAndTheReturnUrl(): void
    {
        $html = $this->structureBox()->render(
            $this->backendRequest(),
            1,
            [0],
            '/typo3/module/web/layout?id=1'
        );

        self::assertStringContainsString('class="headliner card', $html);
        self::assertStringContainsString('data-headliner-edit="tt_content:1:header_tag"', $html);
        // the fixture has no h1
        self::assertStringContainsString('The page has no H1.', $html);
        // the edit forms return to the page module, not to the AJAX route that has drawn the box
        self::assertStringContainsString('returnUrl=/typo3/module/web/layout?id%3D1', $html);
        self::assertStringNotContainsString('ajax/headliner', $html);
    }

    #[Test]
    public function aPageWithoutABoxGivesNothing(): void
    {
        $html = $this->structureBox()->render(
            $this->backendRequest(),
            9999,
            [0],
            ''
        );

        self::assertSame('', $html);
    }
}
