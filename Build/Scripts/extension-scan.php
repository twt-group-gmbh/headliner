<?php

/**
 * Runs TYPO3's Extension Scanner (Install Tool -> Upgrade -> "Scan Extension Files") on the command
 * line, using the scanner classes and matcher configuration of the TYPO3 version installed in the
 * given vendor directory. Only the extension's own code is scanned (Classes/, Configuration/ and
 * ext_*.php), not tests, build tools or whatever else lives in the repository.
 *
 *   php Build/Scripts/extension-scan.php                              # uses .Build/vendor
 *   php Build/Scripts/extension-scan.php --vendor=/path/to/vendor     # e.g. another TYPO3 install
 *   php Build/Scripts/extension-scan.php --strict                     # also fail on "weak" matches
 *
 * Exit code 1 for "strong" matches (removed/changed core API) and files that cannot be parsed. "Weak"
 * matches are only reported: they match on a method name alone and are often false positives.
 * Silence one with a "// @extensionScannerIgnoreLine" comment above the line.
 */

use PhpParser\Error as PhpParserError;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use Symfony\Component\Finder\Finder;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Install\ExtensionScanner\Php\CodeStatistics;
use TYPO3\CMS\Install\ExtensionScanner\Php\GeneratorClassesResolver;
use TYPO3\CMS\Install\ExtensionScanner\Php\MatcherFactory;

$options = getopt('', ['vendor::', 'path::', 'strict']);
$extensionRoot = rtrim((string) ($options['path'] ?? dirname(__DIR__, 2)), '/');
// The TYPO3 to scan with defaults to this repository's dev dependencies, wherever --path points.
$vendor = rtrim((string) ($options['vendor'] ?? dirname(__DIR__, 2) . '/.Build/vendor'), '/');
$strict = array_key_exists('strict', $options);

if (!is_file($vendor . '/autoload.php') || !is_dir($vendor . '/typo3/cms-install')) {
    fwrite(STDERR, "No TYPO3 with EXT:install found in {$vendor} - install the dev dependencies first.\n");
    exit(2);
}

require $vendor . '/autoload.php';

$project = dirname($vendor);
Environment::initialize(
    new ApplicationContext('Development'),
    true,
    true,
    $project,
    $project . '/public',
    $project . '/var',
    $project . '/config',
    $project . '/public/index.php',
    'UNIX'
);

$resolveConfigurationFiles = static function (array $configurations) use ($vendor): array {
    foreach ($configurations as &$configuration) {
        if (isset($configuration['configurationFile'])) {
            $configuration['configurationFile'] = str_replace(
                'EXT:install/',
                $vendor . '/typo3/cms-install/',
                $configuration['configurationFile']
            );
        }
    }

    return $configurations;
};

$scanner = '14.x';
$matcherConfigurations = $resolveConfigurationFiles(
    (new \TYPO3\CMS\Install\ExtensionScanner\Php\MatcherRegistry())->getMatcherConfigurations()
);

$files = [];
foreach (['Classes', 'Configuration'] as $directory) {
    if (is_dir($extensionRoot . '/' . $directory)) {
        foreach ((new Finder())->files()->in($extensionRoot . '/' . $directory)->name('*.php')->sortByName() as $file) {
            $files[] = $file->getPathname();
        }
    }
}
foreach (glob($extensionRoot . '/ext_*.php') ?: [] as $file) {
    $files[] = $file;
}

$strong = 0;
$weak = 0;
$parseErrors = 0;
$effectiveLines = 0;
$report = [];

foreach ($files as $path) {
    $relative = ltrim(substr($path, strlen($extensionRoot)), '/');
    $parser = (new ParserFactory())->createForVersion(PhpVersion::fromComponents(8, 2));
    try {
        $statements = $parser->parse((string) file_get_contents($path));
    } catch (PhpParserError $error) {
        $parseErrors++;
        $report[] = "PARSE ERROR {$relative}: " . $error->getMessage();
        continue;
    }

    // Same two-pass traversal as the Install Tool: resolve names first, then match.
    $traverser = new NodeTraverser();
    $traverser->addVisitor(new NameResolver());
    $statements = $traverser->traverse((array) $statements);

    $traverser = new NodeTraverser();
    $traverser->addVisitor(new GeneratorClassesResolver());
    $statistics = new CodeStatistics();
    $traverser->addVisitor($statistics);
    $matchers = (new MatcherFactory())->createAll($matcherConfigurations);
    foreach ($matchers as $matcher) {
        $traverser->addVisitor($matcher);
    }
    $traverser->traverse($statements);

    $effectiveLines += $statistics->getNumberOfEffectiveCodeLines();
    $lines = file($path) ?: [];
    foreach ($matchers as $matcher) {
        foreach ($matcher->getMatches() as $match) {
            $match['indicator'] === 'strong' ? $strong++ : $weak++;
            $report[] = sprintf(
                "%s %s:%d %s\n    %s\n    changelog: %s",
                strtoupper((string) $match['indicator']),
                $relative,
                $match['line'],
                $match['message'],
                trim($lines[$match['line'] - 1] ?? ''),
                implode(', ', $match['restFiles'] ?? [])
            );
        }
    }
}

printf(
    "TYPO3 extension scanner %s: %d files, %d effective code lines - %d strong, %d weak, %d parse errors\n",
    $scanner,
    count($files),
    $effectiveLines,
    $strong,
    $weak,
    $parseErrors
);
echo $report === [] ? "No findings.\n" : implode("\n", $report) . "\n";

exit($strong > 0 || $parseErrors > 0 || ($strict && $weak > 0) ? 1 : 0);
