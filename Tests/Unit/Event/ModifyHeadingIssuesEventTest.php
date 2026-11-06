<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Event;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Event\ModifyHeadingIssuesEvent;
use Twtgroup\Headliner\Structure\Heading;
use Twtgroup\Headliner\Structure\Issue;
use Twtgroup\Headliner\Structure\IssueType;

final class ModifyHeadingIssuesEventTest extends TestCase
{
    private function event(): ModifyHeadingIssuesEvent
    {
        return new ModifyHeadingIssuesEvent(
            [new Heading(1, 'Title'), new Heading(3, 'Skipped')],
            [new Issue(IssueType::SkippedLevel, 1), new Issue(IssueType::MultipleH1, 1)],
            12,
            0,
        );
    }

    #[Test]
    public function itCarriesWhatTheListenerNeeds(): void
    {
        $event = $this->event();

        self::assertCount(2, $event->getHeadings());
        self::assertSame('Skipped', $event->getHeadings()[1]->text);
        self::assertSame(12, $event->getPageId());
        self::assertSame(0, $event->getLanguageId());
        self::assertCount(2, $event->getIssues());
    }

    #[Test]
    public function aListenerCanAddAnIssueToAHeadingOrToThePage(): void
    {
        $event = $this->event();

        $event->addIssue('myext.tooLong', 0, 'Too long');
        $event->addIssue('myext.noLead', null, 'No lead');

        self::assertEquals(new Issue('myext.tooLong', 0, 'Too long'), $event->getIssues()[2]);
        self::assertEquals(new Issue('myext.noLead', null, 'No lead'), $event->getIssues()[3]);
    }

    #[Test]
    public function aListenerCanRemoveARuleByItsName(): void
    {
        $event = $this->event();

        $event->removeIssues(IssueType::MultipleH1->value);

        self::assertEquals([new Issue(IssueType::SkippedLevel, 1)], $event->getIssues());
    }

    #[Test]
    public function aListenerCanReplaceAllIssues(): void
    {
        $event = $this->event();

        $event->setIssues([new Issue('myext.only', null, 'Only')]);

        self::assertEquals([new Issue('myext.only', null, 'Only')], $event->getIssues());
    }
}
