<?php

declare(strict_types=1);

namespace Cognesy\Doctor\Freeze;

use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;
use Cognesy\Utils\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Utils\Sandbox\Drivers\HostSandbox;

class FreezeCommand
{
    private ?string $filePath = null;
    private ?string $executeCommand = null;
    private ?string $output = null;
    private ?string $language = null;
    private ?string $theme = null;
    private ?string $config = null;
    private bool $window = false;
    private bool $showLineNumbers = false;
    private ?string $background = null;
    private ?int $height = null;
    private ?string $fontFamily = null;
    private ?int $fontSize = null;
    private ?float $lineHeight = null;
    private ?int $borderRadius = null;
    private ?int $borderWidth = null;
    private ?string $borderColor = null;
    private ?string $padding = null;
    private ?string $margin = null;
    private ?string $lines = null;
    
    private CanExecuteCommand $executor;

    public function __construct(?string $filePath = null, ?CanExecuteCommand $executor = null) {
        $this->filePath = $filePath;
        // Executor is no longer used; kept for backward compatibility
        if ($executor !== null) {
            $this->executor = $executor;
        } else {
            $dir = sys_get_temp_dir();
            $policy = ExecutionPolicy::in($dir)->inheritEnvironment(true);
            $this->executor = new HostSandbox($policy);
        }
    }

    public function execute(string $command): self {
        $this->executeCommand = $command;
        return $this;
    }

    public function output(string $path): self {
        $this->output = $path;
        return $this;
    }

    public function language(string $language): self {
        $this->language = $language;
        return $this;
    }

    public function theme(string $theme): self {
        $this->theme = $theme;
        return $this;
    }

    public function config(string $config): self {
        $this->config = $config;
        return $this;
    }

    public function window(bool $enabled = true): self {
        $this->window = $enabled;
        return $this;
    }

    public function showLineNumbers(bool $enabled = true): self {
        $this->showLineNumbers = $enabled;
        return $this;
    }

    public function background(string $color): self {
        $this->background = $color;
        return $this;
    }

    public function height(int $height): self {
        $this->height = $height;
        return $this;
    }

    public function fontFamily(string $family): self {
        $this->fontFamily = $family;
        return $this;
    }

    public function fontSize(int $size): self {
        $this->fontSize = $size;
        return $this;
    }

    public function lineHeight(float $height): self {
        $this->lineHeight = $height;
        return $this;
    }

    public function borderRadius(int $radius): self {
        $this->borderRadius = $radius;
        return $this;
    }

    public function borderWidth(int $width): self {
        $this->borderWidth = $width;
        return $this;
    }

    public function borderColor(string $color): self {
        $this->borderColor = $color;
        return $this;
    }

    public function padding(string $padding): self {
        $this->padding = $padding;
        return $this;
    }

    public function margin(string $margin): self {
        $this->margin = $margin;
        return $this;
    }

    public function lines(string $lines): self {
        $this->lines = $lines;
        return $this;
    }

    public function setExecutor(CanExecuteCommand $executor): self {
        $this->executor = $executor;
        return $this;
    }

    public function run(): FreezeResult {
        // Ensure file/output paths are absolute to avoid cwd differences in Sandbox Host driver
        $cwd = getcwd() ?: null;
        if (!empty($this->filePath) && $cwd !== null && !str_starts_with((string)$this->filePath, '/')) {
            $abs = $cwd . '/' . $this->filePath;
            $real = realpath($abs);
            $this->filePath = $real !== false ? $real : $abs;
        }
        if (!empty($this->output) && $cwd !== null && !str_starts_with((string)$this->output, '/')) {
            $this->output = $cwd . '/' . $this->output;
        }

        $commandArray = $this->buildCommandArray();

        try {
            $exec = $this->executor->execute($commandArray);
            $success = $exec->success();
            $output = $success ? $exec->stdout() : '';
            $error = $success ? '' : $exec->combinedOutput();
        } catch (\Throwable $e) {
            $success = false;
            $output = '';
            $error = $e->getMessage();
        }

        return new FreezeResult(
            success: $success,
            output: $output,
            errorOutput: $error,
            command: $this->buildCommandString(),
            outputPath: $this->output,
        );
    }

    private function buildCommandArray(): array {
        // Auto-detect language if not specified and we have a file
        if (empty($this->language) && !empty($this->filePath)) {
            $this->language = $this->detectLanguageFromFile($this->filePath);
        } else {
            $this->language = strtolower($this->language ?? 'text');
        }

        $parts = ['freeze'];

        // Add file path right after freeze command if we have one
        if (!empty($this->filePath) && empty($this->executeCommand)) {
            $parts[] = $this->filePath;
        }

        if (!empty($this->executeCommand)) {
            $parts[] = '-x';
            $parts[] = $this->executeCommand;
        }

        if ($this->config) {
            $parts[] = '--config';
            $parts[] = $this->config;
        }

        if ($this->language) {
            $parts[] = '--language';
            $parts[] = $this->language;
        }

        if ($this->theme) {
            $parts[] = '--theme';
            $parts[] = $this->theme;
        }

        if ($this->output) {
            $parts[] = '--output';
            $parts[] = $this->output;
        }

        if ($this->window) {
            $parts[] = '--window';
        }

        if ($this->showLineNumbers) {
            $parts[] = '--show-line-numbers';
        }

        if ($this->background) {
            $parts[] = '--background';
            $parts[] = $this->background;
        }

        if ($this->height) {
            $parts[] = '--height';
            $parts[] = (string)$this->height;
        }

        if ($this->fontFamily) {
            $parts[] = '--font.family';
            $parts[] = $this->fontFamily;
        }

        if ($this->fontSize) {
            $parts[] = '--font.size';
            $parts[] = (string)$this->fontSize;
        }

        if ($this->lineHeight) {
            $parts[] = '--line-height';
            $parts[] = (string)$this->lineHeight;
        }

        if ($this->borderRadius) {
            $parts[] = '--border.radius';
            $parts[] = (string)$this->borderRadius;
        }

        if ($this->borderWidth) {
            $parts[] = '--border.width';
            $parts[] = (string)$this->borderWidth;
        }

        if ($this->borderColor) {
            $parts[] = '--border.color';
            $parts[] = $this->borderColor;
        }

        if ($this->padding) {
            $parts[] = '--padding';
            $parts[] = $this->padding;
        }

        if ($this->margin) {
            $parts[] = '--margin';
            $parts[] = $this->margin;
        }

        if ($this->lines) {
            $parts[] = '--lines';
            $parts[] = $this->lines;
        }

        return $parts;
    }

    public function buildCommandString(): string {
        $commandArray = $this->buildCommandArray();
        
        // Escape arguments for shell execution (except 'freeze' and file path)
        $escapedParts = [];
        foreach ($commandArray as $index => $part) {
            if ($index === 0) {
                $escapedParts[] = $part; // Don't escape 'freeze'
            } else if ($index === 1 && !empty($this->filePath) && empty($this->executeCommand)) {
                $escapedParts[] = $part; // Don't escape file path when it's first argument
            } else if (str_starts_with($part, '--') || str_starts_with($part, '-')) {
                $escapedParts[] = $part; // Don't escape flag names
            } else {
                $escapedParts[] = escapeshellarg($part); // Escape values
            }
        }
        
        return implode(' ', $escapedParts);
    }

    private function detectLanguageFromFile(string $filePath): ?string {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'php' => 'php',
            'py' => 'python',
            'js' => 'javascript',
            'ts' => 'typescript',
            'java' => 'java',
            'cpp', 'cc', 'cxx' => 'cpp',
            'c' => 'c',
            'cs' => 'csharp',
            'go' => 'go',
            'rs' => 'rust',
            'rb' => 'ruby',
            'swift' => 'swift',
            'kt' => 'kotlin',
            'scala' => 'scala',
            'sh', 'bash' => 'bash',
            'ps1' => 'powershell',
            'sql' => 'sql',
            'html' => 'html',
            'css' => 'css',
            'scss' => 'scss',
            'less' => 'less',
            'json' => 'json',
            'xml' => 'xml',
            'yaml', 'yml' => 'yaml',
            'toml' => 'toml',
            'md' => 'markdown',
            'r' => 'r',
            'matlab', 'm' => 'matlab',
            'pl' => 'perl',
            'lua' => 'lua',
            'vim' => 'vim',
            'dockerfile' => 'dockerfile',
            default => null,
        };
    }
}
