<?php declare(strict_types=1);

namespace Cognesy\Setup;

use Cognesy\Setup\Assets\ConfigurationsDirAsset;
use Cognesy\Setup\Assets\EnvFileAsset;
use Cognesy\Setup\Assets\PromptsDirAsset;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PublishCommand extends Command
{
    protected static $defaultName = 'publish';

    private bool $noOp = true;
    private bool $stopOnError = true;
    private string $targetConfigDir = '';
    private string $targetPromptsDir = '';
    private string $targetEnvFile = '';

    private Output $output;
    private Filesystem $filesystem;
    private EnvFile $envFile;

    protected function configure(): void {
        $this->setName(self::$defaultName)
            ->setDescription('Publishes or updates assets for the Instructor library.')
            ->addOption('target-config-dir', 'c', InputOption::VALUE_REQUIRED, 'Target directory for configuration files')
            ->addOption('target-prompts-dir', 'p', InputOption::VALUE_REQUIRED, 'Target directory for prompt files')
            ->addOption('target-env-file', 'e', InputOption::VALUE_REQUIRED, 'Target .env file')
            ->addOption('log-file', 'l', InputOption::VALUE_OPTIONAL, 'Log file path')
            ->addOption('no-op', 'no', InputOption::VALUE_NONE, 'Do not perform any actions, only log what would be done');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void {
        $this->noOp = (bool)$input->getOption('no-op');
        $this->stopOnError = !$this->noOp;
        $this->output = new Output($input, $output);
        $this->filesystem = new Filesystem($this->noOp, $this->output);
        $this->envFile = new EnvFile($this->noOp, $this->output, $this->filesystem);

        // Ensure the command is run from the project root
        $projectRoot = getcwd();
        if (!file_exists($projectRoot . '/composer.json')) {
            throw new InvalidArgumentException("This command must be run from your project root directory.");
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $this->output->out("");
        $this->output->out("<white>{$this->getApplication()?->getName()}</white> v{$this->getApplication()?->getVersion()}");

        $this->targetConfigDir = $input->getOption('target-config-dir') ?? throw new InvalidArgumentException('Missing target-config-dir option');
        $this->targetPromptsDir = $input->getOption('target-prompts-dir') ?? throw new InvalidArgumentException('Missing target-prompts-dir option');
        $this->targetEnvFile = $input->getOption('target-env-file') ?? throw new InvalidArgumentException('Missing target-env-file option');

        $assets = $this->getAssets($input);
        if (empty($assets)) {
            $this->output->out(" <gray>...</gray> <yellow>(!)</yellow> <red>No assets to publish</red>", 'error');
            return Command::FAILURE;
        }

        $this->output->out("");
        $this->output->out("Publishing Instructor assets...");

        $totalAssets = count($assets);
        $step = 0;
        try {
            foreach ($assets as $asset) {
                $step += 1;
                $this->output->out("");
                $this->output->out("<blue>(step $step of $totalAssets)</blue> Processing asset: <white>{$asset->name}</white> ({$asset->description})");
                $success = $asset->publish();
                if (!$success && $this->stopOnError) {
                    return Command::FAILURE;
                }
            }

            $this->output->out("");
            $this->output->out(" <gray>...</gray> <green>DONE</green>");
            $this->output->out("");
            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->handleError('command execution', $e);
            return Command::FAILURE;
        }
    }

    private function handleError(string $context, Exception $e): void {
        $this->output->out(" <gray>...</gray> <yellow>(!)</yellow> <red>Error in</red> $context: " . $e->getMessage(), 'error');

        if ($this->stopOnError) {
            $this->output->out("");
            $this->output->out(" <red>STOPPED</red> - Error details:");
            $this->output->out($e->getMessage());
            $this->output->out($e->getTraceAsString());
            $this->output->out("");
        }
    }

    private function getAssets(InputInterface $input) : array {
        $assets = [];
        if ($input->getOption('target-config-dir')) {
            $assets[] = new ConfigurationsDirAsset(
                __DIR__ . '/../config',
                $this->targetConfigDir,
                $this->filesystem,
                $this->output,
            );
        }

        if ($input->getOption('target-prompts-dir')) {
            $assets[] = new PromptsDirAsset(
                __DIR__ . '/../prompts',
                $this->targetPromptsDir,
                $this->filesystem,
                $this->output,
            );
        }

        if ($input->getOption('target-env-file')) {
            $assets[] = new EnvFileAsset(
                __DIR__ . '/../.env-dist',
                $this->targetEnvFile, $this->targetConfigDir,
                $this->filesystem,
                $this->output,
                $this->envFile,
            );
        }

        return $assets;
    }
}
