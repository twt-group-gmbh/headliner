<?php

use Twtgroup\Headliner\Controller\RteHeadingController;
use Twtgroup\Headliner\Controller\StructureController;

return [
    // The box with the heading structure of a page, loaded by the page module after it has been shown
    'headliner_structure' => [
        'path' => '/headliner/structure',
        'methods' => ['GET'],
        'target' => StructureController::class,
    ],
    // The tag of a heading in the rich text of a content element, changed from the box
    'headliner_rte_tag' => [
        'path' => '/headliner/rte-tag',
        'methods' => ['POST'],
        'target' => RteHeadingController::class,
    ],
];
