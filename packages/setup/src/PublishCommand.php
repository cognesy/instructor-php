<?php declare(strict_types=1);

namespace Cognesy\Setup;

use Cognesy\Setup\Resources\PackageResource;
use Cognesy\Setup\Resources\PackageResourceLocator;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PublishCommand extends Command
{
    protected static ?string $defaultName = 'publish';

    private bool $noOp = true;
    private bool $force = false;
    private string $targetRoot = '';
    private string $packagesRoot = '';

    private ?Output $output = null;
    private ?Filesystem $filesystem = null;
    private ?PackageResourceLocator $locator = null;

    #[\Override]
    protected function configure(): void {
        $this->setName(self::$defaultName ?? 'publish')
            ->setDescription('Publishes package resources from packages/*/resources into target directory.')
            ->addArgument('target', InputArgument::OPTIONAL, 'Target directory where package resources will be published')
            ->addOption('target-dir', 't', InputOption::VALUE_REQUIRED, 'Target directory (alternative to <target> argument)')
            ->addOption('source-root', 's', InputOption::VALUE_OPTIONAL, 'Packages root to scan for resources', 'packages')
            ->addOption('package', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Publish only selected package(s)')
            ->addOption('exclude-package', 'x', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Exclude package(s) from publishing')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing destination package directories')
            ->addOption('log-file', 'l', InputOption::VALUE_OPTIONAL, 'Log file path')
            ->addOption('no-op', null, InputOption::VALUE_NONE, 'Dry run: show actions without modifying files');
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void {
        $this->noOp = (bool) $input->getOption('no-op');
        $this->force = (bool) $input->getOption('force');
        $this->output = new Output($input, $output);
        $this->filesystem = new Filesystem($this->noOp, $this->output);
        $this->locator = new PackageResourceLocator();

        $this->ensureProjectRoot();
        $this->targetRoot = Path::resolve($this->resolveTargetRoot($input));
        $this->packagesRoot = Path::resolve((string) $input->getOption('source-root'));
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        assert($this->output !== null);
        assert($this->filesystem !== null);
        assert($this->locator !== null);

        $onlyPackages = $this->normalizePackageList($input->getOption('package'));
        $excludedPackages = $this->normalizePackageList($input->getOption('exclude-package'));

        $resources = $this->locator->locate(
            packagesRoot: $this->packagesRoot,
            targetRoot: $this->targetRoot,
            onlyPackages: $onlyPackages,
            excludedPackages: $excludedPackages,
        );

        if ($resources === []) {
            $this->output->out("<red>No package resources found to publish.</red>", 'error');
            return Command::FAILURE;
        }

        $this->printHeader($resources);
        $summary = new PublishSummary();

        foreach ($resources as $resource) {
            $summary->add($this->publishResource($resource));
        }

        $this->printSummary($summary);
        return $summary->hasErrors()
            ? Command::FAILURE
            : Command::SUCCESS;
    }

    private function ensureProjectRoot(): void {
        $projectRoot = getcwd();
        if (!is_string($projectRoot) || !is_file($projectRoot . DIRECTORY_SEPARATOR . 'composer.json')) {
            throw new InvalidArgumentException('This command must be run from a project root containing composer.json.');
        }
    }

    private function resolveTargetRoot(InputInterface $input): string {
        $target = (string) ($input->getArgument('target') ?? '');
        if ($target !== '') {
            return $target;
        }

        $fromOption = (string) ($input->getOption('target-dir') ?? '');
        if ($fromOption !== '') {
            return $fromOption;
        }

        throw new InvalidArgumentException('Missing publish target. Pass <target> argument or --target-dir option.');
    }

    /**
     * @return list<string>
     */
    private function normalizePackageList(mixed $raw): array {
        if (!is_array($raw)) {
            return [];
        }

        $list = array_map(static fn(mixed $value): string => trim((string) $value), $raw);
        $list = array_values(array_filter($list, static fn(string $value): bool => $value !== ''));
        $list = array_values(array_unique($list));

        return $list;
    }

    /**
     * @param list<PackageResource> $resources
     */
    private function printHeader(array $resources): void {
        assert($this->output !== null);

        $this->output->out('');
        $this->output->out("<white>{$this->getApplication()?->getName()}</white> v{$this->getApplication()?->getVersion()}");
        $this->output->out("Source root: <blue>{$this->packagesRoot}</blue>");
        $this->output->out("Target root: <blue>{$this->targetRoot}</blue>");
        $this->output->out('Mode: ' . ($this->noOp ? '<yellow>no-op</yellow>' : '<green>apply</green>'));
        $this->output->out('Force overwrite: ' . ($this->force ? '<yellow>yes</yellow>' : '<green>no</green>'));
        $this->output->out('Packages to publish: <white>' . count($resources) . '</white>');
        $this->output->out('');
    }

    private function publishResource(PackageResource $resource): PublishStatus {
        assert($this->output !== null);
        assert($this->filesystem !== null);

        $this->output->out("Publishing <white>{$resource->package}</white>");

        if ($this->filesystem->exists($resource->destinationPath) && !$this->force) {
            $this->output->out(
                "<yellow>Skipped</yellow> {$resource->package} - destination exists: {$resource->destinationPath}",
                'warning',
            );
            return PublishStatus::Skipped;
        }

        if (!$this->prepareDestination($resource->destinationPath)) {
            return PublishStatus::Error;
        }

        $copied = $this->filesystem->copyDir($resource->sourcePath, $resource->destinationPath);
        if ($copied === Filesystem::RESULT_ERROR) {
            $this->output->out("<red>Failed</red> {$resource->package}", 'error');
            return PublishStatus::Error;
        }

        $this->output->out("<green>Published</green> {$resource->package}");
        return PublishStatus::Published;
    }

    private function prepareDestination(string $destinationPath): bool {
        assert($this->filesystem !== null);

        if ($this->force) {
            $removed = $this->filesystem->removePath($destinationPath);
            if ($removed === Filesystem::RESULT_ERROR) {
                return false;
            }
        }

        $created = $this->filesystem->createDirectory($destinationPath);
        return $created !== Filesystem::RESULT_ERROR;
    }

    private function printSummary(PublishSummary $summary): void {
        assert($this->output !== null);

        $this->output->out('');
        $this->output->out('Summary:');
        $this->output->out("  published: <green>{$summary->published()}</green>");
        $this->output->out("  skipped: <yellow>{$summary->skipped()}</yellow>");
        $this->output->out("  errors: <red>{$summary->errors()}</red>");
        $this->output->out('');
    }
}
