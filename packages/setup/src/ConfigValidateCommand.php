<?php declare(strict_types=1);

namespace Cognesy\Setup;

use Cognesy\Config\ConfigBootstrap;
use Cognesy\Config\ConfigValidator;
use Cognesy\Setup\Config\PackageConfigDiscovery;
use Cognesy\Setup\Config\PublishedConfigFileSetResolver;
use Cognesy\Setup\Config\PublishedConfigRules;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class ConfigValidateCommand extends Command
{
    protected static ?string $defaultName = 'config:validate';

    private string $targetRoot = '';
    private string $packagesRoot = '';

    private ?Output $output = null;
    private ?PackageConfigDiscovery $discovery = null;
    private ?PublishedConfigFileSetResolver $fileSetResolver = null;

    #[\Override]
    protected function configure(): void
    {
        $this->setName(self::$defaultName ?? 'config:validate')
            ->setDescription('Validates published package config files.')
            ->addArgument('target', InputArgument::OPTIONAL, 'Published config target directory to validate')
            ->addOption('target-dir', 't', InputOption::VALUE_REQUIRED, 'Target directory (alternative to <target> argument)')
            ->addOption('source-root', 's', InputOption::VALUE_OPTIONAL, 'Packages root used to discover owned config namespaces', 'packages')
            ->addOption('package', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Validate only selected package(s)')
            ->addOption('exclude-package', 'x', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Exclude package(s) from validation')
            ->addOption('log-file', 'l', InputOption::VALUE_OPTIONAL, 'Log file path');
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->output = new Output($input, $output);
        $this->discovery = new PackageConfigDiscovery();
        $this->fileSetResolver = new PublishedConfigFileSetResolver();

        $this->ensureProjectRoot();
        $this->targetRoot = Path::resolve($this->resolveTargetRoot($input));
        $this->packagesRoot = Path::resolve((string) $input->getOption('source-root'));
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        assert($this->output !== null);
        assert($this->discovery !== null);
        assert($this->fileSetResolver !== null);

        try {
            $plan = $this->discovery->discover(
                packagesRoot: $this->packagesRoot,
                targetRoot: $this->targetRoot,
                onlyPackages: $this->normalizePackageList($input->getOption('package')),
                excludedPackages: $this->normalizePackageList($input->getOption('exclude-package')),
            );

            if ($plan->isEmpty()) {
                $this->output->out('<red>No package config files discovered for validation.</red>', 'error');

                return Command::FAILURE;
            }

            $fileSet = $this->fileSetResolver->resolve($this->targetRoot, $plan);
            $graph = (new ConfigBootstrap())->bootstrap($fileSet);
            $validated = (new ConfigValidator(new PublishedConfigRules()))->validate($graph);

            $this->output->out("Validated <green>" . $fileSet->count() . "</green> files across <green>" . count($plan->packages()) . "</green> package namespaces.");
            $this->output->out('Config graph roots: <white>' . implode(', ', array_keys($validated)) . '</white>');

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->output->out('<red>Validation failed:</red> ' . $e->getMessage(), 'error');

            return Command::FAILURE;
        }
    }

    private function ensureProjectRoot(): void
    {
        $projectRoot = getcwd();
        if (!is_string($projectRoot) || !is_file($projectRoot . DIRECTORY_SEPARATOR . 'composer.json')) {
            throw new InvalidArgumentException('This command must be run from a project root containing composer.json.');
        }
    }

    private function resolveTargetRoot(InputInterface $input): string
    {
        $target = (string) ($input->getArgument('target') ?? '');
        if ($target !== '') {
            return $target;
        }

        $fromOption = (string) ($input->getOption('target-dir') ?? '');
        if ($fromOption !== '') {
            return $fromOption;
        }

        throw new InvalidArgumentException('Missing validation target. Pass <target> argument or --target-dir option.');
    }

    /**
     * @return list<string>
     */
    private function normalizePackageList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $list = array_map(static fn(mixed $value): string => trim((string) $value), $raw);
        $list = array_values(array_filter($list, static fn(string $value): bool => $value !== ''));

        return array_values(array_unique($list));
    }
}
