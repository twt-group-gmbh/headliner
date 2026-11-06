<?php

declare(strict_types=1);

use Twtgroup\Headliner\Middleware\InspectRequest;

return [
    'frontend' => [
        'twtgroup/headliner/inspect-request' => [
            'target' => InspectRequest::class,
            // after the frontend user is authenticated, whose aspect it may replace
            'after' => ['typo3/cms-frontend/site', 'typo3/cms-frontend/authentication'],
            // a hidden page and a page for a group are turned down when the page is resolved, so this has to come first
            'before' => ['typo3/cms-frontend/page-resolver'],
        ],
    ],
];
