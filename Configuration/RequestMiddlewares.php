<?php

declare(strict_types=1);

use Twtgroup\Headliner\Middleware\InspectRequest;

return [
    'frontend' => [
        'twtgroup/headliner/inspect-request' => [
            'target' => InspectRequest::class,
            'after' => ['typo3/cms-frontend/site'],
            'before' => ['typo3/cms-frontend/page-argument-validator'],
        ],
    ],
];
