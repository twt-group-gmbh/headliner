<?php

use Twtgroup\Headliner\Controller\StructureController;

return [
    // The box with the heading structure of a page, loaded by the page module after it has been shown
    'headliner_structure' => [
        'path' => '/headliner/structure',
        'methods' => ['GET'],
        'target' => StructureController::class,
    ],
];
