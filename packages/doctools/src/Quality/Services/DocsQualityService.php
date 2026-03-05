<?php declare(strict_types=1);

namespace Cognesy\Doctools\Quality\Services;

use Cognesy\Doctools\Quality\Data\DocsQualityConfig;
use Cognesy\Doctools\Quality\Data\DocsQualityIssue;
use Cognesy\Doctools\Quality\Data\DocsQualityResult;
use Cognesy\Doctools\Quality\Data\QualityRule;
use Cognesy\Doctools\Quality\Data\QualityRuleEngine;
use Cognesy\Doctools\Quality\Data\QualityRuleScope;
use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

final readonly class DocsQualityService
{
    public function __construct(
        private RulesProvider $rules = new RulesProvider(),
    ) {}

    public function run(DocsQualityConfig $config): DocsQualityResult
    {
        if (!is_dir($config->docsRoot)) {
            throw new RuntimeException("docs directory not found: {$config->docsRoot}");
        }

        $rules = $this->rules->rulesFor($config);
        $markdownRegexRules = $this->filterRules($rules, QualityRuleEngine::Regex, QualityRuleScope::Markdown);
        $snippetRegexRules = $this->filterRules($rules, QualityRuleEngine::Regex, QualityRuleScope::PhpSnippet);
        $snippetAstGrepRules = $this->filterRules($rules, QualityRuleEngine::AstGrep, QualityRuleScope::PhpSnippet);

        $astGrepBinary = $config->astGrepBin ?? 'ast-grep';
        if ($snippetAstGrepRules !== [] && !$this->isAstGrepAvailable($astGrepBinary)) {
            if ($config->strict) {
                throw new RuntimeException("ast-grep binary not available: {$astGrepBinary}");
            }
            $snippetAstGrepRules = [];
        }

        $files = $this->markdownFiles($config->docsRoot, $config->extensions);
        $issues = [];
        $checkedSnippets = 0;
        $skippedSnippets = 0;

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                $issues[] = new DocsQualityIssue($file, null, 'unable to read file');
                continue;
            }

            $this->appendIssues($issues, $this->regexIssues($content, $file, 1, $markdownRegexRules));
            $this->appendIssues($issues, $this->brokenLinkIssues($content, $file, $config->docsRoot, $config->repoRoot));

            foreach ($this->phpFencedBlocks($content) as $block) {
                if ($this->shouldSkipSnippet($block['code'])) {
                    $skippedSnippets++;
                    continue;
                }

                $this->appendIssues($issues, $this->regexIssues($block['code'], $file, $block['line'], $snippetRegexRules));
                $this->appendIssues($issues, $this->astGrepIssues(
                    snippet: $block['code'],
                    file: $file,
                    baseLine: $block['line'],
                    rules: $snippetAstGrepRules,
                    binary: $astGrepBinary,
                ));

                $checkedSnippets++;
                $lint = $this->lintPhpSnippet($block['code']);

                if ($lint['ok']) {
                    continue;
                }

                if ($this->isIncompleteSnippet($block['code'], $lint['output'])) {
                    $checkedSnippets--;
                    $skippedSnippets++;
                    continue;
                }

                $issues[] = new DocsQualityIssue(
                    filePath: $file,
                    line: $block['line'],
                    message: 'php snippet lint failed: ' . trim($lint['output']),
                );
            }
        }

        return new DocsQualityResult(
            issues: $issues,
            checkedSnippets: $checkedSnippets,
            skippedSnippets: $skippedSnippets,
        );
    }

    /**
     * @param list<DocsQualityIssue> $issues
     * @param list<DocsQualityIssue> $newIssues
     */
    private function appendIssues(array &$issues, array $newIssues): void
    {
        foreach ($newIssues as $issue) {
            $issues[] = $issue;
        }
    }

    /**
     * @param list<string> $extensions
     * @return list<string>
     */
    private function markdownFiles(string $root, array $extensions): array
    {
        $allowed = [];
        foreach ($extensions as $extension) {
            $normalized = strtolower(trim($extension));
            if ($normalized === '') {
                continue;
            }
            $allowed[$normalized] = true;
        }
        if ($allowed === []) {
            $allowed['md'] = true;
        }

        $paths = [];
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iter as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo) {
                continue;
            }
            if (!$fileInfo->isFile()) {
                continue;
            }
            $extension = strtolower($fileInfo->getExtension());
            if (!isset($allowed[$extension])) {
                continue;
            }
            $paths[] = $fileInfo->getPathname();
        }

        sort($paths);
        return $paths;
    }

    /**
     * @param list<QualityRule> $rules
     * @return list<DocsQualityIssue>
     */
    private function regexIssues(string $content, string $file, int $baseLine, array $rules): array
    {
        $issues = [];
        foreach ($rules as $rule) {
            $matched = @preg_match_all($rule->pattern, $content, $matches, PREG_OFFSET_CAPTURE);
            if ($matched === false) {
                throw new InvalidArgumentException("Invalid regex for rule `{$rule->id}`: {$rule->pattern}");
            }
            if ($matched < 1) {
                continue;
            }

            foreach ($matches[0] as $match) {
                $issues[] = new DocsQualityIssue(
                    filePath: $file,
                    line: $this->lineForOffset($content, $match[1], $baseLine),
                    message: "quality rule `{$rule->id}` matched `{$match[0]}`. {$rule->message}",
                );
            }
        }

        return $issues;
    }

    /**
     * @return list<array{code:string,line:int}>
     */
    private function phpFencedBlocks(string $markdown): array
    {
        $blocks = [];
        if (!preg_match_all('/```php[^\n]*\n(.*?)\n```/s', $markdown, $matches, PREG_OFFSET_CAPTURE)) {
            return $blocks;
        }

        foreach ($matches[1] as $match) {
            $blocks[] = [
                'code' => $match[0],
                'line' => $this->lineForOffset($markdown, $match[1]),
            ];
        }

        return $blocks;
    }

    private function lineForOffset(string $content, int $offset, int $baseLine = 1): int
    {
        return $baseLine + substr_count(substr($content, 0, $offset), "\n");
    }

    private function shouldSkipSnippet(string $code): bool
    {
        if (trim($code) === '') {
            return true;
        }

        $skipPatterns = [
            '/\.\.\./',
            '/\{\{.*\}\}/s',
            '/\{%.*%\}/s',
            '/<\/?[A-Za-z][^>]*>/',
            '/qa:skip/',
            '/qa:expect-fail/',
        ];

        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{ok:bool,output:string}
     */
    private function lintPhpSnippet(string $snippet): array
    {
        $normalized = trim($snippet);
        if (!str_starts_with($normalized, '<?php')) {
            $normalized = "<?php\n{$normalized}";
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'docs-qa-');
        if ($tmpFile === false) {
            return ['ok' => false, 'output' => 'failed to allocate temporary file'];
        }

        $bytes = file_put_contents($tmpFile, $normalized);
        if ($bytes === false) {
            @unlink($tmpFile);
            return ['ok' => false, 'output' => 'failed to write temporary file'];
        }

        $outputLines = [];
        exec('php -l ' . escapeshellarg($tmpFile) . ' 2>&1', $outputLines, $exitCode);
        @unlink($tmpFile);

        return [
            'ok' => $exitCode === 0,
            'output' => implode("\n", $outputLines),
        ];
    }

    private function isIncompleteSnippet(string $snippet, string $lintOutput): bool
    {
        if (!str_contains($lintOutput, 'unexpected end of file')) {
            return false;
        }

        $openBraces = substr_count($snippet, '{');
        $closeBraces = substr_count($snippet, '}');
        return $openBraces > $closeBraces;
    }

    /**
     * @param list<QualityRule> $rules
     * @return list<DocsQualityIssue>
     */
    private function astGrepIssues(
        string $snippet,
        string $file,
        int $baseLine,
        array $rules,
        string $binary,
    ): array {
        $issues = [];
        foreach ($rules as $rule) {
            foreach ($this->astGrepMatches($snippet, $rule, $binary) as $match) {
                $issues[] = new DocsQualityIssue(
                    filePath: $file,
                    line: $baseLine + $match['line'],
                    message: "quality rule `{$rule->id}` matched `{$match['text']}`. {$rule->message}",
                );
            }
        }

        return $issues;
    }

    private function isAstGrepAvailable(string $binary): bool
    {
        $process = new Process([$binary, '--version']);
        $process->run();
        if (!$process->isSuccessful()) {
            return false;
        }

        return str_contains(strtolower($process->getOutput()), 'ast-grep');
    }

    /**
     * @return list<array{line:int,text:string}>
     */
    private function astGrepMatches(string $snippet, QualityRule $rule, string $binary): array
    {
        $tmpFileBase = tempnam(sys_get_temp_dir(), 'docs-quality-ast-');
        if ($tmpFileBase === false) {
            throw new RuntimeException('Unable to allocate temporary snippet file for ast-grep.');
        }
        $tmpFile = "{$tmpFileBase}.php";
        rename($tmpFileBase, $tmpFile);

        file_put_contents($tmpFile, $snippet);
        try {
            $language = $rule->language ?? 'php';
            $process = new Process([
                $binary,
                '--pattern',
                $rule->pattern,
                '--lang',
                $language,
                '--json=stream',
                $tmpFile,
            ]);
            $process->run();

            $exitCode = $process->getExitCode();
            if ($exitCode === 1) {
                return [];
            }
            if (!$process->isSuccessful()) {
                $errorOutput = trim($process->getErrorOutput() . "\n" . $process->getOutput());
                throw new RuntimeException("ast-grep failed for rule `{$rule->id}`: {$errorOutput}");
            }

            return $this->parseAstGrepOutput($process->getOutput(), $rule->id);
        } finally {
            @unlink($tmpFile);
        }
    }

    /**
     * @return list<array{line:int,text:string}>
     */
    private function parseAstGrepOutput(string $output, string $ruleId): array
    {
        $matches = [];
        foreach (explode("\n", trim($output)) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $match = json_decode($line, true);
            if (!is_array($match)) {
                continue;
            }

            $range = $match['range'] ?? null;
            $start = is_array($range) ? ($range['start'] ?? null) : null;
            $lineNumber = is_array($start) ? ($start['line'] ?? null) : null;
            if (!is_int($lineNumber)) {
                throw new RuntimeException("ast-grep returned invalid line data for rule `{$ruleId}`.");
            }

            $text = $match['text'] ?? '';
            if (!is_string($text) || $text === '') {
                $text = '(pattern match)';
            }

            $matches[] = ['line' => $lineNumber, 'text' => $text];
        }

        return $matches;
    }

    /**
     * @param list<QualityRule> $rules
     * @return list<QualityRule>
     */
    private function filterRules(array $rules, QualityRuleEngine $engine, QualityRuleScope $scope): array
    {
        $filtered = [];
        foreach ($rules as $rule) {
            if ($rule->engine !== $engine || $rule->scope !== $scope) {
                continue;
            }
            $filtered[] = $rule;
        }

        return $filtered;
    }

    /**
     * @return list<DocsQualityIssue>
     */
    private function brokenLinkIssues(string $markdown, string $file, string $docsRoot, string $repoRoot): array
    {
        $issues = [];
        $scan = $this->stripFencedCodePreservingLines($markdown);

        if (!preg_match_all('/\[[^\]]+\]\(([^)]+)\)/', $scan, $matches, PREG_OFFSET_CAPTURE)) {
            return $issues;
        }

        foreach ($matches[1] as $match) {
            $targetRaw = trim($match[0]);
            if ($targetRaw === '') {
                continue;
            }

            $target = trim($targetRaw, '<>');
            if (
                str_starts_with($target, 'http://')
                || str_starts_with($target, 'https://')
                || str_starts_with($target, 'mailto:')
                || str_starts_with($target, '#')
            ) {
                continue;
            }

            $path = explode('#', $target, 2)[0];
            if ($path === '') {
                continue;
            }

            if ($this->linkExists($path, $file, $docsRoot, $repoRoot)) {
                continue;
            }

            $issues[] = new DocsQualityIssue(
                filePath: $file,
                line: $this->lineForOffset($scan, $match[1]),
                message: "broken local link `{$targetRaw}`.",
            );
        }

        return $issues;
    }

    private function linkExists(string $path, string $file, string $docsRoot, string $repoRoot): bool
    {
        $candidates = str_starts_with($path, '/')
            ? $this->absoluteLinkCandidates($path, $docsRoot, $repoRoot)
            : $this->linkCandidates(Path::join(dirname($file), $path));

        foreach (array_unique($candidates) as $candidate) {
            if (is_file($candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function absoluteLinkCandidates(string $path, string $docsRoot, string $repoRoot): array
    {
        $relative = ltrim($path, '/');

        return [
            ...$this->linkCandidates(Path::join($docsRoot, $relative)),
            ...$this->linkCandidates(Path::join($repoRoot, 'docs', $relative)),
            ...$this->linkCandidates(Path::join($repoRoot, 'builds/docs-build', $relative)),
        ];
    }

    private function stripFencedCodePreservingLines(string $markdown): string
    {
        return (string)preg_replace_callback(
            '/```.*?```/s',
            static fn(array $match): string => str_repeat("\n", substr_count($match[0], "\n")),
            $markdown,
        );
    }

    /**
     * @return list<string>
     */
    private function linkCandidates(string $path): array
    {
        $candidates = [$path];
        if (pathinfo($path, PATHINFO_EXTENSION) !== '') {
            return $candidates;
        }

        $trimmed = rtrim($path, '/');
        $candidates[] = $path . '.md';
        $candidates[] = $path . '.mdx';
        $candidates[] = $trimmed . '/index.md';
        $candidates[] = $trimmed . '/index.mdx';

        return $candidates;
    }
}
