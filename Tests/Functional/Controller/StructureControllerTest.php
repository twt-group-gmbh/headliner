<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Controller\StructureController;
use Twtgroup\Headliner\Tests\Functional\AbstractFrontendTestCase;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * The AJAX route that the page module loads the box from.
 */
final class StructureControllerTest extends AbstractFrontendTestCase
{
    /** @var array<string, mixed> */
    private array $originalLang = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLang = array_key_exists('LANG', $GLOBALS) ? ['LANG' => $GLOBALS['LANG']] : [];
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/BackendUsers.csv');
        $this->writeSite();
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

    private function load(int $userUid, string $query): \Psr\Http\Message\ResponseInterface
    {
        $this->setUpBackendUser($userUid);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');
        parse_str($query, $arguments);

        return (new StructureController($this->structureBox()))(
            $this->backendRequest()->withQueryParams($arguments)
        );
    }

    #[Test]
    public function givesTheBoxToAUserWhoMaySeeThePage(): void
    {
        $response = $this->load(1, 'id=1&languages[]=0&returnUrl=/typo3/module/web/layout?id=1');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('class="headliner card', (string) $response->getBody());
    }

    #[Test]
    public function theEditFormsReturnToTheGivenLocalUrlOnly(): void
    {
        $local = (string) $this->load(1, 'id=1&returnUrl=/typo3/module/web/layout?id=1')->getBody();
        $foreign = (string) $this->load(1, 'id=1&returnUrl=https://evil.example/')->getBody();

        self::assertStringContainsString('returnUrl=/typo3/module/web/layout?id%3D1', $local);
        self::assertStringNotContainsString('evil.example', $foreign);
    }

    #[Test]
    public function refusesAUserWhoMayNotSeeThePage(): void
    {
        // this editor has no access to any page
        $response = $this->load(2, 'id=1&languages[]=0');

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    #[Test]
    public function refusesARequestWithoutAPage(): void
    {
        self::assertSame(403, $this->load(1, 'languages[]=0')->getStatusCode());
    }

    #[Test]
    public function refusesAPageThatDoesNotExist(): void
    {
        $response = $this->load(1, 'id=9999&languages[]=0');

        // a page that does not exist is not a page the user may see
        self::assertSame(403, $response->getStatusCode());
    }
}
