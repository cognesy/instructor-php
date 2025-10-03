<?php declare(strict_types=1);

namespace Cognesy\Setup\Assets;

use Cognesy\Setup\Contracts\Publishable;
use Cognesy\Setup\Filesystem;
use Cognesy\Setup\Output;
use Cognesy\Setup\Path;

class PromptsDirAsset implements Publishable
{
    public readonly string $name;
    public readonly string $description;
    private string $sourcePath;
    private string $destinationPath;
    private Filesystem $filesystem;
    private Output $output;

    public function __construct(
        string     $source,
        string     $dest,
        Filesystem $filesystem,
        Output     $output,
    ) {
        $this->name = 'prompts';
        $this->description = 'Prompt templates used by the Instructor library';
        $this->sourcePath = Path::resolve($source);
        $this->destinationPath = Path::resolve($dest);

        $this->filesystem = $filesystem;
        $this->output = $output;
    }

    #[\Override]
    public function publish(): bool {
        if ($this->filesystem->exists($this->destinationPath)) {
            $this->output->out(
                "<yellow>Skipped publishing prompts:</yellow> Directory already exists at {$this->destinationPath}. Please merge the prompt files manually.",
                'warning'
            );
            return false;
        }

        // Attempt to create the directory
        $created = $this->filesystem->createDirectory($this->destinationPath);
        if ($created === Filesystem::RESULT_ERROR) {
            $this->output->out(
                "<red>Failed to create prompts directory at {$this->destinationPath}.</red>",
                'error'
            );
            return false;
        }

        // Proceed to copy the directory
        $result = $this->filesystem->copyDir($this->sourcePath, $this->destinationPath);
        if ($result === Filesystem::RESULT_NOOP) {
            $this->output->out("<yellow>Would publish prompts:</yellow>\n from {$this->sourcePath}\n to {$this->destinationPath}");
            return true;
        }

        if ($result === Filesystem::RESULT_OK) {
            $this->output->out("Published prompts from {$this->sourcePath} to {$this->destinationPath}");
            return true;
        }

        $this->output->out(
            "<red>Failed to publish prompts from {$this->sourcePath} to {$this->destinationPath}.</red>",
            'error'
        );
        return false;
    }
}
