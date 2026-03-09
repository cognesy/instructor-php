<?php declare(strict_types=1);

namespace Cognesy\Doctools\Quality\Commands;

use Cognesy\Doctools\Quality\Actions\RunDocsQualityAction;
use Cognesy\Doctools\Quality\Data\DocsQualityConfig;
use Cognesy\Doctools\Quality\Services\DocsQualityService;
use Cognesy\Doctools\Quality\Services\RulesProvider;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'qa',
    description: 'Run docs QA checks (anti-patterns, local links, fenced PHP snippets)',
)]
final class RunDocsQualityCommand extends Command
{
    public function __construct(
        private readonly RunDocsQualityAction $runQuality = new RunDocsQualityAction(
            new DocsQualityService(new RulesProvider()),
        ),
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption(
                'source-dir',
                's',
                InputOption::VALUE_OPTIONAL,
                'Docs directory to scan',
                'docs',
            )
            ->addOption(
                'repo-root',
                'r',
                InputOption::VALUE_OPTIONAL,
                'Repository root path used for resolving absolute links',
            )
            ->addOption(
                'profile',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Rule profile to apply (for example: none, agents, http-client, instructor)',
                'instructor',
            )
            ->addOption(
                'extensions',
                'e',
                InputOption::VALUE_OPTIONAL,
                'Comma-separated markdown extensions to scan',
                'md',
            )
            ->addOption(
                'rules',
                null,
                InputOption::VALUE_OPTIONAL,
                'Comma-separated YAML rules file paths to apply last (highest precedence).',
                '',
            )
            ->addOption(
                'ast-grep-bin',
                null,
                InputOption::VALUE_OPTIONAL,
                'Path to ast-grep binary',
                'ast-grep',
            )
            ->addOption(
                'format',
                null,
                InputOption::VALUE_OPTIONAL,
                'Output format: text|json',
                'text',
            )
            ->addOption(
                'strict',
                null,
                InputOption::VALUE_NEGATABLE,
                'Fail when required quality tooling (e.g. ast-grep) is missing.',
                true,
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = 'text';
        try {
            $format = $this->parseFormat((string)$input->getOption('format'));
            $docsRoot = $this->resolvePath((string)$input->getOption('source-dir'));
            $repoRoot = $this->resolveRepoRoot(
                docsRoot: $docsRoot,
                optionValue: $input->getOption('repo-root'),
            );

            $config = new DocsQualityConfig(
                docsRoot: $docsRoot,
                repoRoot: $repoRoot,
                profile: strtolower(trim((string)$input->getOption('profile'))),
                extensions: $this->parseExtensions((string)$input->getOption('extensions')),
                ruleFiles: $this->parseRuleFiles((string)$input->getOption('rules')),
                astGrepBin: trim((string)$input->getOption('ast-grep-bin')),
                strict: (bool)$input->getOption('strict'),
                format: $format,
            );

            $result = ($this->runQuality)($config);
            if ($format === 'json') {
                $output->writeln($this->encodeJson($this->toJsonPayload($result)));
                return $result->hasErrors() ? Command::FAILURE : Command::SUCCESS;
            }

            if ($result->hasErrors()) {
                $output->writeln('docs-qa: failed');
                $output->writeln('');
                foreach ($result->issues as $issue) {
                    $output->writeln('- ' . $issue->format());
                }
                $output->writeln('');
                $output->writeln(sprintf(
                    'Summary: %d issue(s), %d snippet(s) checked, %d skipped.',
                    $result->issueCount(),
                    $result->checkedSnippets,
                    $result->skippedSnippets,
                ));
                return Command::FAILURE;
            }

            $output->writeln(sprintf(
                'docs-qa: passed (%d snippet(s) checked, %d skipped)',
                $result->checkedSnippets,
                $result->skippedSnippets,
            ));
            return Command::SUCCESS;
        } catch (InvalidArgumentException $e) {
            if ($format === 'json') {
                $output->writeln($this->encodeJson(['status' => 'invalid', 'message' => $e->getMessage()]));
                return Command::INVALID;
            }
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::INVALID;
        } catch (RuntimeException $e) {
            if ($format === 'json') {
                $output->writeln($this->encodeJson(['status' => 'failed', 'message' => $e->getMessage()]));
                return Command::FAILURE;
            }
            $output->writeln("<error>docs-qa: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            throw new InvalidArgumentException('Option --source-dir cannot be empty.');
        }

        if (Path::isAbsolute($path)) {
            return Path::canonicalize($path);
        }

        $cwd = getcwd();
        if ($cwd === false) {
            throw new RuntimeException('Unable to resolve current working directory.');
        }

        return Path::canonicalize(Path::join($cwd, $path));
    }

    private function resolveRepoRoot(string $docsRoot, mixed $optionValue): string
    {
        $repoRoot = trim((string)$optionValue);
        if ($repoRoot !== '') {
            return $this->resolvePath($repoRoot);
        }

        $normalized = str_replace('\\', '/', $docsRoot);
        if (preg_match('#^(.*?)/packages/[^/]+/docs(?:/.*)?$#', $normalized, $matches)) {
            return $matches[1];
        }

        $cwd = getcwd();
        if ($cwd === false) {
            throw new RuntimeException('Unable to resolve repository root.');
        }

        return Path::canonicalize($cwd);
    }

    /**
     * @return list<string>
     */
    private function parseExtensions(string $raw): array
    {
        $extensions = [];
        foreach (explode(',', $raw) as $extension) {
            $normalized = strtolower(trim($extension));
            if ($normalized === '') {
                continue;
            }
            $extensions[$normalized] = true;
        }

        if ($extensions === []) {
            return ['md'];
        }

        return array_keys($extensions);
    }

    /**
     * @return list<string>
     */
    private function parseRuleFiles(string $raw): array
    {
        $ruleFiles = [];
        foreach (explode(',', $raw) as $path) {
            $normalized = trim($path);
            if ($normalized === '') {
                continue;
            }
            $ruleFiles[] = $this->resolvePath($normalized);
        }

        return $ruleFiles;
    }

    private function parseFormat(string $format): string
    {
        $normalized = strtolower(trim($format));
        if ($normalized === 'text' || $normalized === 'json') {
            return $normalized;
        }

        throw new InvalidArgumentException("Unsupported --format value: {$format}. Expected text|json.");
    }

    private function toJsonPayload(\Cognesy\Doctools\Quality\Data\DocsQualityResult $result): array
    {
        $issues = [];
        foreach ($result->issues as $issue) {
            $issues[] = [
                'file' => $issue->filePath,
                'line' => $issue->line,
                'message' => $issue->message,
            ];
        }

        return [
            'status' => $result->hasErrors() ? 'failed' : 'passed',
            'summary' => [
                'issue_count' => $result->issueCount(),
                'checked_snippets' => $result->checkedSnippets,
                'skipped_snippets' => $result->skippedSnippets,
            ],
            'issues' => $issues,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodeJson(array $payload): string
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('Unable to encode JSON quality output.');
        }

        return $json;
    }
}
