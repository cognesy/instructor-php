<?php declare(strict_types=1);

namespace Cognesy\Setup;

use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class Filesystem
{
    private SymfonyFilesystem $fs;
    public const RESULT_OK = 0;
    public const RESULT_ERROR = 1;
    public const RESULT_NOOP = 2;

    public function __construct(
        private bool   $noOp,
        private Output $output,
    ) {
        $this->fs = new SymfonyFilesystem();
    }

    public function createDirectory(string $path): int {
        if ($this->noOp) {
            $this->output->out("<yellow>Would create directory:</yellow>\n $path");
            return self::RESULT_NOOP;
        }

        try {
            $this->fs->mkdir($path, 0755);
        } catch (IOExceptionInterface $e) {
            $this->output->out("<red>Failed to create directory:</red> {$e->getPath()}", 'error');
            return self::RESULT_ERROR;
        }
        return self::RESULT_OK;
    }

    public function copyDir(string $source, string $dest): int {
        if ($this->noOp) {
            $this->output->out("<yellow>Would copy directory:</yellow>\n from $source\n to $dest");
            return self::RESULT_NOOP;
        }
        try {
            $this->fs->mirror($source, $dest);
        } catch (IOExceptionInterface $e) {
            $this->output->out("<red>Failed to copy directory:</red>\n from $source\n to $dest\n - {$e->getMessage()}", 'error');
            return self::RESULT_ERROR;
        }
        $this->output->out("Copied directory:\n from $source\n to $dest");
        return self::RESULT_OK;
    }

    public function copyFile(string $source, string $dest): int {
        if (!$this->exists($source)) {
            $this->output->out("<red>Source file does not exist:</red>\n $source", 'error');
            return self::RESULT_ERROR;
        }

        if ($this->fs->exists($dest)) {
            $this->output->out("<yellow>Skipping - destination file already exists:</yellow>\n $dest", 'warning');
            return self::RESULT_NOOP;
        }

        if ($this->noOp) {
            $this->output->out("<yellow>Would copy file:</yellow>\n from $source\n to $dest");
            return self::RESULT_NOOP;
        }

        try {
            $this->fs->copy($source, $dest);
        } catch (IOExceptionInterface $e) {
            $this->output->out("<red>Failed to copy file:</red>\n from $source\n to $dest\n - {$e->getMessage()}", 'error');
            return self::RESULT_ERROR;
        }
        $this->output->out("Copied file:\n from $source\n to $dest");
        return self::RESULT_OK;
    }

    public function readFile(string $path): string {
        if (!$this->fs->exists($path)) {
            throw new \RuntimeException("File does not exist: $path");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Failed to read file: $path");
        }

        return $content;
    }

    public function writeFile(string $path, string $content): int {
        if ($this->noOp) {
            $this->output->out("<yellow>Would write to file:</yellow>\n $path");
            return self::RESULT_NOOP;
        }

        try {
            $this->fs->dumpFile($path, $content);
        } catch (IOExceptionInterface $e) {
            $this->output->out("<red>Failed to write to file:</red>\n $path\n Error: {$e->getMessage()}", 'error');
            return self::RESULT_ERROR;
        }
        $this->output->out("Wrote to file: $path");
        return self::RESULT_OK;
    }

    public function exists(string $path): bool {
        return $this->fs->exists($path);
    }
}
