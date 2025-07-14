<?php

namespace Cognesy\Setup\Assets;

use Cognesy\Setup\Contracts\Publishable;
use Cognesy\Setup\EnvFile;
use Cognesy\Setup\Filesystem;
use Cognesy\Setup\Output;
use Cognesy\Setup\Path;

class EnvFileAsset implements Publishable
{
    public readonly string $name;
    public readonly string $description;
    private string $sourcePath;
    private string $destinationPath;
    private string $configDir;
    private EnvFile $envFile;
    private Filesystem $filesystem;
    private Output $output;

    public function __construct(
        string $source,
        string $dest,
        string $configDir,
        Filesystem $filesystem,
        Output $output,
        EnvFile $envFile,
    ) {
        $this->name = 'env';
        $this->description = 'Environment configuration file (.env)';
        $this->sourcePath = Path::resolve($source);
        $this->destinationPath = Path::resolve($dest);
        $this->configDir = Path::resolve($configDir);

        $this->envFile = $envFile;
        $this->filesystem = $filesystem;
        $this->output = $output;
    }

    public function publish(): bool {
        // copy from .env-dist to .env if .env does not exist
        if (!$this->filesystem->exists($this->destinationPath)) {
            $result = $this->filesystem->copyFile($this->sourcePath, $this->destinationPath);
            if ($result === Filesystem::RESULT_NOOP) {
                $this->output->out("<yellow>Would copy & update env file:</yellow>\n from {$this->sourcePath}\n to {$this->destinationPath}");
            } elseif ($this->filesystem->exists($this->destinationPath)) {
                $this->output->out("Published env file from {$this->sourcePath} to {$this->destinationPath}");
            }
        }

        // merge variable values into existing env file - needed to add INSTRUCTOR_CONFIG_PATHS
        $this->envFile->mergeEnvFiles($this->sourcePath, $this->destinationPath, $this->configDir);
        if ($this->filesystem->exists($this->destinationPath)) {
            //$this->output->out("Merged env files into {$this->destinationPath}");
            return true;
        }
        return false;
    }
}
