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
            ->addOption('with-static', null, InputOption::VALUE_NONE, 'Also list headings with a fixed text');
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
        foreach ($this->templates($path) as $file) {
            $source = (string) file_get_contents($file);
            if (!$this->scanner->isFluid($source)) {
                continue;
            }
            foreach ($this->scanner->scan($source) as $heading) {
                $counts[$heading->kind->value] = ($counts[$heading->kind->value] ?? 0) + 1;
                if ($heading->kind === HeadingKind::Static && !$input->getOption('with-static')) {
                    continue;
                }
                // escaped: the markup of a template is no style tag of the console
                $output->writeln(OutputFormatter::escape(sprintf(
                    '%s:%d  <%s%s> %s',
                    substr($file, strlen($path) + 1),
                    $heading->line,
                    $heading->tag,
                    $heading->attributes === '' ? '' : ' ' . $heading->attributes,
                    mb_strimwidth($heading->text, 0, 100, '...')
                )));
                if ($heading->suggestion !== null) {
                    $output->writeln('    -> ' . OutputFormatter::escape($heading->suggestion));
                }
            }
        }

        $output->writeln(sprintf(
            "\n%d can be replaced, %d needs a decision, %d static",
            $counts[HeadingKind::Migratable->value] ?? 0,
            $counts[HeadingKind::NeedsDecision->value] ?? 0,
            $counts[HeadingKind::Static->value] ?? 0
        ));

        return Command::SUCCESS;
    }

    /**
     * @return \Generator<string>
     */
    private function templates(string $path): \Generator
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            static fn(\SplFileInfo $file): bool => !$file->isDir() || !in_array($file->getFilename(), self::SKIPPED, true)
        ));
        foreach ($files as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'html') {
                yield $file->getPathname();
            }
        }
    }
}
