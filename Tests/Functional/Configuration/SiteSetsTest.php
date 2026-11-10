<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Configuration;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Site\Set\SetRegistry;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Two site sets: the one for every site, which needs nothing, and the one for fluid_styled_content, which adds the
 * header partial. fluid_styled_content is optional because only the second set depends on it.
 */
final class SiteSetsTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['fluid_styled_content'];

    protected array $testExtensionsToLoad = ['twtgroup/headliner'];

    #[Test]
    public function theBaseSetDependsOnNothing(): void
    {
        $set = $this->get(SetRegistry::class)->getSet('twtgroup/headliner');

        self::assertNotNull($set);
        self::assertSame([], $set->dependencies);
        // the settings and the page TSconfig are in it, the TypoScript with the partial of fluid_styled_content is not
        self::assertNotEmpty($set->settingsDefinitions);
        self::assertNotEmpty($set->pagets);
        self::assertFileDoesNotExist(__DIR__ . '/../../../Configuration/Sets/Headliner/setup.typoscript');
    }

    #[Test]
    public function theSetForFluidStyledContentAddsThePartialOnTopOfTheBaseSet(): void
    {
        $set = $this->get(SetRegistry::class)->getSet('twtgroup/headliner-fluid-styled-content');

        self::assertNotNull($set);
        self::assertSame(['typo3/fluid-styled-content', 'twtgroup/headliner'], $set->dependencies);
        self::assertStringContainsString('partialRootPaths', (string) file_get_contents(__DIR__ . '/../../../Configuration/Sets/FluidStyledContent/setup.typoscript'));
    }

    #[Test]
    public function neitherSetIsInvalid(): void
    {
        self::assertSame([], $this->get(SetRegistry::class)->getInvalidSets());
    }
}
