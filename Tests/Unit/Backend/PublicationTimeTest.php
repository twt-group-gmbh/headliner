<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Backend;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Backend\PublicationState;
use Twtgroup\Headliner\Backend\PublicationTime;

final class PublicationTimeTest extends TestCase
{
    private const NOW = 1000;

    /**
     * @return array<string, int|string>
     */
    private function page(int $uid, int $start = 0, int $end = 0, bool $extend = false): array
    {
        return ['uid' => $uid, 'starttime' => (string) $start, 'endtime' => (string) $end, 'extendToSubpages' => $extend ? '1' : '0'];
    }

    #[Test]
    public function aPageWithoutTimesIsPublishedAndNeedsNoSimulation(): void
    {
        $time = PublicationTime::forRootLine([$this->page(5), $this->page(1)], 5, self::NOW);

        self::assertSame(PublicationState::Published, $time->state);
        self::assertNull($time->simulatedTime);
    }

    #[Test]
    public function aStartTimeInTheFutureShowsThePageAsItWillBe(): void
    {
        $time = PublicationTime::forRootLine([$this->page(5, start: 2000)], 5, self::NOW);

        self::assertSame(PublicationState::NotYet, $time->state);
        self::assertSame(2000, $time->simulatedTime);
        self::assertSame(2000, $time->start);
    }

    #[Test]
    public function aWindowThatIsOpenNowIsPublished(): void
    {
        $time = PublicationTime::forRootLine([$this->page(5, start: 500, end: 2000)], 5, self::NOW);

        self::assertSame(PublicationState::Published, $time->state);
        self::assertNull($time->simulatedTime);
    }

    #[Test]
    public function anEndTimeThatIsOverShowsThePageJustBeforeItEnded(): void
    {
        $time = PublicationTime::forRootLine([$this->page(5, end: 500)], 5, self::NOW);

        self::assertSame(PublicationState::Expired, $time->state);
        self::assertSame(499, $time->simulatedTime);
        self::assertSame(500, $time->end);
    }

    #[Test]
    public function aWindowInThePastIsShownJustBeforeItEnded(): void
    {
        $time = PublicationTime::forRootLine([$this->page(5, start: 500, end: 800)], 5, self::NOW);

        self::assertSame(PublicationState::Expired, $time->state);
        self::assertSame(799, $time->simulatedTime);
    }

    #[Test]
    public function aWindowInTheFutureIsShownAtItsStart(): void
    {
        $time = PublicationTime::forRootLine([$this->page(5, start: 2000, end: 3000)], 5, self::NOW);

        self::assertSame(PublicationState::NotYet, $time->state);
        self::assertSame(2000, $time->simulatedTime);
    }

    #[Test]
    public function anEndBeforeOrAtTheStartCanNeverBeShown(): void
    {
        self::assertSame(PublicationState::Never, PublicationTime::forRootLine([$this->page(5, start: 3000, end: 2000)], 5, self::NOW)->state);
        self::assertSame(PublicationState::Never, PublicationTime::forRootLine([$this->page(5, start: 2000, end: 2000)], 5, self::NOW)->state);
        self::assertNull(PublicationTime::forRootLine([$this->page(5, start: 3000, end: 2000)], 5, self::NOW)->simulatedTime);
    }

    #[Test]
    public function aParentCountsOnlyIfItExtendsToSubpages(): void
    {
        $extends = PublicationTime::forRootLine([$this->page(5), $this->page(2, start: 2000, extend: true)], 5, self::NOW);
        $doesNot = PublicationTime::forRootLine([$this->page(5), $this->page(2, start: 2000, extend: false)], 5, self::NOW);

        self::assertSame(PublicationState::NotYet, $extends->state);
        self::assertSame(2000, $extends->simulatedTime);
        self::assertSame(PublicationState::Published, $doesNot->state);
    }

    #[Test]
    public function anExpiredParentThatExtendsToSubpagesExpiresThePage(): void
    {
        $time = PublicationTime::forRootLine([$this->page(5), $this->page(2, end: 500, extend: true)], 5, self::NOW);

        self::assertSame(PublicationState::Expired, $time->state);
        self::assertSame(499, $time->simulatedTime);
    }

    #[Test]
    public function theLatestStartAndTheEarliestEndOfThePageAndItsParentsApply(): void
    {
        $rootLine = [
            $this->page(5, start: 1500, end: 5000),
            $this->page(2, start: 2500, end: 3000, extend: true),
            $this->page(1, start: 2200, end: 9000, extend: true),
        ];

        $time = PublicationTime::forRootLine($rootLine, 5, self::NOW);

        self::assertSame(PublicationState::NotYet, $time->state);
        self::assertSame(2500, $time->simulatedTime);
        self::assertSame(3000, $time->end);
    }

    #[Test]
    public function aParentThatEndsBeforeThePageStartsMakesItNeverVisible(): void
    {
        $time = PublicationTime::forRootLine([$this->page(5, start: 4000), $this->page(2, end: 3000, extend: true)], 5, self::NOW);

        self::assertSame(PublicationState::Never, $time->state);
    }

    #[Test]
    public function theTranslationOfThePageHasItsOwnTimes(): void
    {
        $time = PublicationTime::forRootLine([$this->page(5)], 5, self::NOW, $this->page(9, start: 2000));

        self::assertSame(PublicationState::NotYet, $time->state);
        self::assertSame(2000, $time->simulatedTime);
    }
}
