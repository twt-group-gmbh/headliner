<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Configuration;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Site\Set\SetRegistry;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * One site set. fluid_styled_content is an optional dependency: it is loaded before the set if it is there, and nothing
 * is reported if it is not.
 */
final class SiteSetsTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['fluid_styled_content'];

    protected array $testExtensionsToLoad = ['twtgroup/headliner'];

    #[Test]
    public function theSetNeedsNothingAndLoadsFluidStyledContentFirstIfItIsThere(): void
    {
        $registry = $this->get(SetRegistry::class);
        $set = $registry->getSet('twtgroup/headliner');

        self::assertNotNull($set);
        self::assertSame([], $set->dependencies);
        self::assertSame(['typo3/fluid-styled-content'], $set->optionalDependencies);
        self::assertSame([], $registry->getInvalidSets());
        // the partial path is in its TypoScript, and fluid_styled_content comes before it
        self::assertFileExists(__DIR__ . '/../../../Configuration/Sets/Headliner/setup.typoscript');
        // the site lists Headliner first, and fluid_styled_content is still applied before it
        $order = array_values(array_map(static fn($definition): string => $definition->name, $registry->getSets('twtgroup/headliner', 'typo3/fluid-styled-content')));
        self::assertLessThan(array_search('twtgroup/headliner', $order, true), array_search('typo3/fluid-styled-content', $order, true));
    }

    #[Test]
    public function thereIsNoSecondSet(): void
    {
        self::assertNull($this->get(SetRegistry::class)->getSet('twtgroup/headliner-fluid-styled-content'));
    }
}
