<?php
declare(strict_types=1);

namespace Cognesy\Auxiliary\AstGrep;

use Cognesy\Auxiliary\AstGrep\Data\SearchResult;
use Cognesy\Auxiliary\AstGrep\Data\SearchResults;
use Cognesy\Auxiliary\AstGrep\Enums\Language;
use RuntimeException;

class AstGrep
{
    private string $astGrepPath;
    private Language $language;
    private ?string $workingDirectory;

    public function __construct(
        Language $language = Language::PHP,
        ?string $workingDirectory = null,
        ?string $astGrepPath = null,
    ) {
        $this->language = $language;
        $this->workingDirectory = $workingDirectory;
        $this->astGrepPath = $astGrepPath ?? $this->findAstGrep();

        if (!$this->isAvailable()) {
            throw new RuntimeException('ast-grep is not available. Please install it first.');
        }
    }

    public function search(string $pattern, string $path = '.'): SearchResults {
        $command = $this->buildCommand($pattern, $path);
        $output = $this->execute($command);
        return $this->parseOutput($output);
    }

    public function searchWithRule(string $ruleFile, string $path = '.'): SearchResults {
        if (!file_exists($ruleFile)) {
            throw new RuntimeException("Rule file not found: $ruleFile");
        }

        $command = sprintf(
            '%s scan --rule %s %s 2>&1',
            $this->astGrepPath,
            escapeshellarg($ruleFile),
            escapeshellarg($path)
        );

        $output = $this->execute($command);
        return $this->parseOutput($output);
    }

    public function replace(string $pattern, string $replacement, string $path = '.'): SearchResults {
        $tempRuleFile = $this->createTempRuleFile($pattern, $replacement);

        try {
            $command = sprintf(
                '%s scan --rule %s %s --update-all 2>&1',
                $this->astGrepPath,
                escapeshellarg($tempRuleFile),
                escapeshellarg($path)
            );

            $output = $this->execute($command);
            return $this->parseOutput($output);
        } finally {
            unlink($tempRuleFile);
        }
    }

    public function isAvailable(): bool {
        $command = sprintf('%s --version 2>&1', $this->astGrepPath);
        $result = shell_exec($command);
        return is_string($result) && str_contains($result, 'ast-grep');
    }

    private function buildCommand(string $pattern, string $path): string {
        return sprintf(
            '%s --pattern %s --lang %s %s 2>&1',
            $this->astGrepPath,
            escapeshellarg($pattern),
            $this->language->value,
            escapeshellarg($path)
        );
    }

    private function execute(string $command): string {
        if ($this->workingDirectory !== null) {
            $originalDir = getcwd();
            if ($originalDir !== false) {
                chdir($this->workingDirectory);
            }
        }

        // Debug: uncomment to see actual command
        // error_log("Executing: $command from " . getcwd());

        $output = shell_exec($command);

        if (isset($originalDir) && is_string($originalDir)) {
            chdir($originalDir);
        }

        return is_string($output) ? $output : '';
    }

    private function parseOutput(string $output): SearchResults {
        $results = [];
        $lines = explode("\n", $output);

        $currentFile = null;
        $currentLine = null;
        $currentMatch = '';

        foreach ($lines as $line) {
            if (preg_match('/^(.+?):(\d+):(.+)$/', $line, $matches)) {
                if ($currentFile !== null && $currentLine !== null) {
                    $results[] = new SearchResult(
                        file: $currentFile,
                        line: $currentLine,
                        match: trim($currentMatch),
                        context: []
                    );
                }

                $currentFile = $matches[1];
                $currentLine = (int)$matches[2];
                $currentMatch = $matches[3];
            } elseif ($currentFile !== null && trim($line) !== '') {
                $currentMatch .= "\n" . $line;
            }
        }

        if ($currentFile !== null && $currentLine !== null) {
            $results[] = new SearchResult(
                file: $currentFile,
                line: $currentLine,
                match: trim($currentMatch),
                context: []
            );
        }

        return new SearchResults($results);
    }

    private function findAstGrep(): string {
        $paths = [
            '/home/linuxbrew/.linuxbrew/bin/ast-grep',
            '/usr/local/bin/ast-grep',
            '/usr/bin/ast-grep',
            'ast-grep',
        ];

        foreach ($paths as $path) {
            $command = sprintf('%s --version 2>&1', $path);
            $result = shell_exec($command);
            if (is_string($result) && str_contains($result, 'ast-grep')) {
                return $path;
            }
        }

        return 'ast-grep';
    }

    private function createTempRuleFile(string $pattern, string $replacement): string {
        $rule = [
            'id' => 'temp-rule',
            'language' => $this->language->value,
            'rule' => ['pattern' => $pattern],
            'fix' => $replacement,
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'ast-grep-rule-');
        $yamlContent = yaml_emit($rule);
        file_put_contents($tempFile, $yamlContent);

        return $tempFile;
    }
}