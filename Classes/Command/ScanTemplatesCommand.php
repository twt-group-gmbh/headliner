<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Twtgroup\Headliner\Migration\HeadingKind;
use Twtgroup\Headliner\Migration\TemplateScanner;
use TYPO3\CMS\Core\Core\Environment;

/**
 * Lists the headings in the Fluid templates of a project and which of them `headliner:headline` can replace. It only
 * reads: no template is changed.
 */
#[AsCommand(name: 'headliner:scan-templates', description: 'List the headings in Fluid templates and which ViewHelper replaces them')]
final class ScanTemplatesCommand extends Command
{
    /** Directories of code that is not the project's: dependencies, the core, caches, build output. */
    private const SKIPPED = ['vendor', 'node_modules', '.git', 'var', '.Build', 'typo3temp', '.ddev', 'sysext', '_assets'];

    public function __construct(private readonly TemplateScanner $scanner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, 'Directory to scan (default: the project path)')
            ->addOption('with-static', null, InputOption::VALUE_NONE, 'Also list headings with a fixed text')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Path (relative to the scanned directory) to skip, e.g. app/public/typo3conf/ext; can be given more than once')
            ->addOption('with-backend', null, InputOption::VALUE_NONE, 'Also list the headings of backend templates (the preview of a Content Block)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) ($input->getArgument('path') ?? Environment::getProjectPath());
        if (!is_dir($path)) {
            $output->writeln('<error>Not a directory: ' . $path . '</error>');

            return Command::FAILURE;
        }
        $path = rtrim($path, '/');

        $counts = [];
        $backend = 0;
        $excluded = array_values(array_filter(array_map(
            static fn(mixed $exclude): string => trim((string) $exclude, '/'),
            (array) $input->getOption('exclude')
        ), static fn(string $exclude): bool => $exclude !== ''));
        foreach ($this->templates($path, $excluded) as $file) {
            $source = (string) file_get_contents($file);
            if (!$this->scanner->isFluid($source)) {
                continue;
            }
            $isBackend = $this->scanner->isBackendTemplate($file);
            foreach ($this->scanner->scan($source) as $heading) {
                if ($isBackend) {
                    ++$backend;
                    if (!$input->getOption('with-backend')) {
                        continue;
                    }
                } else {
                    $counts[$heading->kind->value] = ($counts[$heading->kind->value] ?? 0) + 1;
                }
                if ($heading->kind === HeadingKind::Static && !$input->getOption('with-static')) {
                    continue;
                }
                $location = substr($file, strlen($path) + 1) . ':' . $heading->line;
                $lines = [];
                if ($heading->suggestion === null) {
                    $lines[] = sprintf(
                        '%s  <%s%s> %s',
                        $location,
                        $heading->tag,
                        $heading->attributes === '' ? '' : ' ' . $heading->attributes,
                        mb_strimwidth($heading->text, 0, 100, '...')
                    );
                } else {
                    $lines[] = $location . ($heading->count > 1 ? sprintf('  (%d headings: %s)', $heading->count, $heading->tag) : '');
                    $lines[] = '  - ' . mb_strimwidth($heading->original, 0, 200, '...');
                    $lines[] = '  + ' . $heading->suggestion;
                    if ($heading->attributes !== '' && $heading->kind === HeadingKind::Migratable) {
                        $lines[] = '  ! ' . $heading->attributes . ' is not passed on by the ViewHelper: keep it in the CSS or an element around it';
                    }
                }
                // escaped: the markup of a template is no style tag of the console
                $output->writeln(OutputFormatter::escape(implode("\n", $lines)));
            }
        }

        $output->writeln(sprintf(
            "\n%d can be replaced, %d %s, %d needs a decision, %d static%s",
            $counts[HeadingKind::Migratable->value] ?? 0,
            $counts[HeadingKind::Component->value] ?? 0,
            ($counts[HeadingKind::Component->value] ?? 0) === 1 ? 'component' : 'components',
            $counts[HeadingKind::NeedsDecision->value] ?? 0,
            $counts[HeadingKind::Static->value] ?? 0,
            $backend > 0 && !$input->getOption('with-backend') ? sprintf(', %d in backend templates (not listed)', $backend) : ''
        ));

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $excluded paths relative to $path; a path is a file or a directory, not the start of a name
     * @return \Generator<string>
     */
    private function templates(string $path, array $excluded): \Generator
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            static function (\SplFileInfo $file) use ($path, $excluded): bool {
                $relative = substr($file->getPathname(), strlen($path) + 1);
                foreach ($excluded as $exclude) {
                    if ($relative === $exclude || str_starts_with($relative, $exclude . '/')) {
                        return false;
                    }
                }

                return !$file->isDir() || !in_array($file->getFilename(), self::SKIPPED, true);
            }
        ));
        foreach ($files as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'html') {
                yield $file->getPathname();
            }
        }
    }
}
