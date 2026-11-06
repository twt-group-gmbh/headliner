<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Template;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The placeholder that the page module shows until the box has been loaded.
 */
final class PlaceholderTemplateTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['twtgroup/headliner'];

    /** @var array<string, mixed> */
    private array $originalLang = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLang = array_key_exists('LANG', $GLOBALS) ? ['LANG' => $GLOBALS['LANG']] : [];
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');
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

    private function render(bool $collapsed): string
    {
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templateRootPaths: ['EXT:headliner/Resources/Private/Templates'],
            request: (new ServerRequest('https://website.local/typo3/', 'GET'))->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE),
        ));
        $view->assignMultiple(['url' => '/typo3/ajax/headliner/structure?id=1', 'collapsed' => $collapsed]);

        return $view->render('PageModule/Placeholder');
    }

    #[Test]
    public function carriesTheUrlOfTheBoxAndAnErrorText(): void
    {
        $html = $this->render(false);

        self::assertStringContainsString('data-headliner-url="/typo3/ajax/headliner/structure?id=1"', $html);
        self::assertStringContainsString('data-headliner-error="The heading structure could not be loaded."', $html);
        self::assertStringContainsString('Checking the page...', $html);
    }

    #[Test]
    public function isOpenUnlessTheBoxIsCollapsed(): void
    {
        self::assertMatchesRegularExpression('/<details[^>]* open[ >]/', $this->render(false));
        self::assertDoesNotMatchRegularExpression('/<details[^>]* open[ >]/', $this->render(true));
    }
}
