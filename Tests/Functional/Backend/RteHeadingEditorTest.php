<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Functional\Backend;

use PHPUnit\Framework\Attributes\Test;
use Twtgroup\Headliner\Backend\RteHeadingEditor;
use Twtgroup\Headliner\Tests\Functional\AbstractFrontendTestCase;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * Changing the tag of a heading in the rich text of a content element.
 */
final class RteHeadingEditorTest extends AbstractFrontendTestCase
{
    /** @var array<string, mixed> */
    private array $originalLang = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLang = array_key_exists('LANG', $GLOBALS) ? ['LANG' => $GLOBALS['LANG']] : [];
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/BackendUsers.csv');
        $this->writeSite();
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

    private function editor(): RteHeadingEditor
    {
        return $this->rteEditor();
    }

    private function enableTheSetting(): void
    {
        $this->writeSite(['headliner' => ['editRteHeadings' => true]]);
    }

    private function bodytext(): string
    {
        $text = (string) $this->getConnectionPool()->getConnectionForTable('tt_content')
            ->select(['bodytext'], 'tt_content', ['uid' => 21])->fetchOne();

        // the DataHandler puts line breaks between the blocks of a rich text, as when it is saved in the editor
        return (string) preg_replace('/>\s+</', '><', $text);
    }

    #[Test]
    public function changesTheTagOfTheHeadingAtThatPlaceInTheText(): void
    {
        $this->enableTheSetting();
        $this->setUpBackendUser(1);

        // the default preset of the RTE offers h2 and h3
        $result = $this->editor()->change('tt_content', 21, 'bodytext', 1, 'h2', 'Opening hours');

        self::assertSame('changed', $result->value);
        self::assertSame('<h2>Visit us</h2><p>Street</p><h2>Opening <strong>hours</strong></h2><p>Mon-Fri</p>', $this->bodytext());
    }

    #[Test]
    public function refusesAHeadingWhoseTextIsNotTheOneInTheField(): void
    {
        $this->enableTheSetting();
        $this->setUpBackendUser(1);

        // the page was changed after the box was drawn
        $result = $this->editor()->change('tt_content', 21, 'bodytext', 1, 'h2', 'Something else');

        self::assertSame('mismatch', $result->value);
        self::assertStringContainsString('<h3>Opening', $this->bodytext());
    }

    #[Test]
    public function refusesAnUnknownPlaceAndATagThatIsNoHeading(): void
    {
        $this->enableTheSetting();
        $this->setUpBackendUser(1);

        self::assertSame('mismatch', $this->editor()->change('tt_content', 21, 'bodytext', 5, 'h2', 'Visit us')->value);
        self::assertSame('notFound', $this->editor()->change('tt_content', 21, 'bodytext', 0, 'div', 'Visit us')->value);
        self::assertSame('notFound', $this->editor()->change('tt_content', 9999, 'bodytext', 0, 'h2', 'Visit us')->value);
    }

    #[Test]
    public function refusesAUserWhoMayNotEditTheElement(): void
    {
        $this->enableTheSetting();
        // this editor has no rights on any table
        $this->setUpBackendUser(2);

        $result = $this->editor()->change('tt_content', 21, 'bodytext', 1, 'h2', 'Opening hours');

        self::assertSame('notAllowed', $result->value);
        self::assertStringContainsString('<h3>Opening', $this->bodytext());
    }

    #[Test]
    public function offersAndAllowsOnlyTheHeadingsTheEditorOffers(): void
    {
        $this->enableTheSetting();
        $this->setUpBackendUser(1);
        $record = ['uid' => 21, 'pid' => 8, 'CType' => 'text'];

        self::assertSame(['h2', 'h3'], $this->editor()->allowedTags('tt_content', 'bodytext', $record));
        // h5 is no choice in the editor, so it is none here
        self::assertSame('notAllowed', $this->editor()->change('tt_content', 21, 'bodytext', 1, 'h5', 'Opening hours')->value);
        self::assertStringContainsString('<h3>Opening', $this->bodytext());
    }

    #[Test]
    public function changesNothingWhileTheSiteSettingIsOff(): void
    {
        // off by default: the text of an element is not changed from the box
        $this->setUpBackendUser(1);

        $result = $this->editor()->change('tt_content', 21, 'bodytext', 1, 'h2', 'Opening hours');

        self::assertSame('notAllowed', $result->value);
        self::assertStringContainsString('<h3>Opening', $this->bodytext());
    }

    #[Test]
    public function changesNothingInAFieldThatIsNoRichText(): void
    {
        $this->enableTheSetting();
        $this->setUpBackendUser(1);

        // a mark in the page names the field: only a rich text is ever changed, never a plain text field
        self::assertSame('notFound', $this->editor()->change('tt_content', 21, 'header', 0, 'h2', 'Intro')->value);
        self::assertSame('notFound', $this->editor()->change('be_users', 1, 'password', 0, 'h2', 'x')->value);
        self::assertSame('notFound', $this->editor()->change('no_such_table', 1, 'text', 0, 'h2', 'x')->value);
    }

    #[Test]
    public function knowsWhichFieldsAreRichText(): void
    {
        // for a content element the rich text is on per type of element
        self::assertTrue($this->editor()->isRichTextField('tt_content', 'bodytext', ['CType' => 'text']));
        self::assertFalse($this->editor()->isRichTextField('tt_content', 'bodytext', ['CType' => 'header']));
        self::assertFalse($this->editor()->isRichTextField('tt_content', 'header', ['CType' => 'text']));
        self::assertFalse($this->editor()->isRichTextField('tt_content', 'CType', ['CType' => 'text']));
        self::assertFalse($this->editor()->isRichTextField('be_users', 'password', []));
        self::assertFalse($this->editor()->isRichTextField('no_such_table', 'text', []));
    }
}
