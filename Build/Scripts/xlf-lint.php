<?php

/**
 * Lints the XLIFF files of the extension: valid XML, valid against the XLIFF schema, and the target language matches
 * the file name ("de.locallang.xlf" is German). Uses the lint command of symfony/translation, which TYPO3 installs.
 *
 *   php Build/Scripts/xlf-lint.php
 *
 * Exit code 0 if all files are fine, 1 if not.
 */

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Translation\Command\XliffLintCommand;

$root = dirname(__DIR__, 2);
require $root . '/.Build/vendor/autoload.php';

// Not strict: TYPO3 puts the language first ("de.locallang.xlf"), Symfony expects "locallang.de.xlf" by default.
$application = new Application();
$application->add(new XliffLintCommand(null, null, null, false));
$application->setAutoExit(false);

// Directories are searched recursively for *.xlf and *.xliff
exit($application->run(
    new ArrayInput(['command' => 'lint:xliff', 'filename' => [$root . '/Resources', $root . '/Configuration']]),
    new ConsoleOutput()
));
