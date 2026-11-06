<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Structure;

/**
 * What the page module simulates when it renders a page, so that a page a visitor cannot see (yet) can be shown:
 * the time (start and end time of a page), the frontend user groups (a page for groups) and the workspace. See
 * InspectRequest for how the frontend does it.
 */
final readonly class Simulation
{
    /**
     * @param int|null $time Render the page at this time
     * @param list<int>|null $groups Render the page as a user of these frontend user groups (0 and -2 included)
     * @param int|null $workspace Render the page in this workspace
     */
    public function __construct(
        public ?int $time = null,
        public ?array $groups = null,
        public ?int $workspace = null,
    ) {}
}
