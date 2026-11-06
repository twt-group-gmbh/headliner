<?php

declare(strict_types=1);

use Twtgroup\Headliner\Middleware\InspectRequest;

return [
    'frontend' => [
        'twtgroup/headliner/inspect-request' => [
            'target' => InspectRequest::class,
            'after' => ['typo3/cms-frontend/site'],
            // a hidden page is turned down when the page is resolved, so this has to come first
            'before' => ['typo3/cms-frontend/page-resolver'],
        ],
    ],
];
