<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@auto' => true
    ])
    ->setFinder(
        (new Finder())
            ->in(__DIR__)
            // Skip everything git ignores (.Build, node_modules, var, config, …) so the fixer only
            // touches our own sources instead of vendored/generated code (and no longer times out).
            ->ignoreVCSIgnored(true)
            // ext_emconf.php must stay strict_types-free; the @auto set would add the declaration.
            ->notPath('ext_emconf.php')
    )
;
