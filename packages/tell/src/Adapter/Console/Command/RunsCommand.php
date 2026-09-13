<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Command;

use Cognesy\Tell\Adapter\Console\Render\StructuredOutput;
use Cognesy\Tell\Core\Contract\Observation\CanInspectTellRuns;
use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class RunsCommand extends Command
{
    public function __construct(private readonly CanInspectTellRuns $runs) {
        parent::__construct('runs');
    }

    #[Override]
    protected function configure(): void {
        $this->setDescription('List or explain local Tell executions')
            ->setHelp(<<<'HELP'
List recent Tell runs from the semantic journal or explain one execution with
its optional safe local trace.

Examples:
  tell runs list
  tell runs list --status stopped --limit 20 --json
  tell runs show <execution-id>
  tell runs show <execution-id> --full --json
HELP)
            ->addArgument('action', InputArgument::OPTIONAL, 'list or show', 'list')
            ->addArgument('execution-id', InputArgument::OPTIONAL, 'Execution ID for show')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'completed, stopped, or failed')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'Filter by stable stop reason')
            ->addOption('agent', 'a', InputOption::VALUE_REQUIRED, 'Filter by agent')
            ->addOption('session', 's', InputOption::VALUE_REQUIRED, 'Filter by session')
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'Filter by branch')
            ->addOption('workspace', null, InputOption::VALUE_REQUIRED, 'Filter by workspace path')
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Filter by ISO-8601 timestamp')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum rows', '50')
            ->addOption('full', null, InputOption::VALUE_NONE, 'Include optional sanitized trace payloads')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $payload = match ((string) $input->getArgument('action')) {
                'list' => $this->list($input),
                'show' => $this->show($input),
                default => throw new InvalidArgumentException('Runs action must be list or show.'),
            };
            (new StructuredOutput($output))->write($payload, json: (bool) $input->getOption('json'));

            return Command::SUCCESS;
        } catch (InvalidArgumentException $error) {
            (new StructuredOutput($output))->write(['error' => $error->getMessage()], json: (bool) $input->getOption('json'));

            return Command::INVALID;
        }
    }


    /** @return array<string, mixed> */
    private function list(InputInterface $input): array {
        $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT);
        if (!is_int($limit) || $limit < 1 || $limit > 200) {
            throw new InvalidArgumentException('--limit must be an integer from 1 to 200.');
        }
        $filters = $this->filters($input);
        $runs = $this->runs->list($filters, $limit);

        return [
            'count' => count($runs),
            'runs' => $runs,
            'message' => $runs === [] ? 'No journaled Tell runs found.' : null,
        ];
    }

    /** @return array<string, mixed> */
    private function show(InputInterface $input): array {
        $id = $input->getArgument('execution-id');
        if (!is_string($id) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,127}\z/', $id) !== 1) {
            throw new InvalidArgumentException('A valid execution ID is required for runs show.');
        }
        $run = $this->runs->show($id, (bool) $input->getOption('full'));
        if ($run === null) {
            throw new InvalidArgumentException("Tell run '{$id}' is not journaled.");
        }

        return $run;
    }

    /** @return array<string, string> */
    private function filters(InputInterface $input): array {
        $filters = [];
        foreach (['status', 'reason', 'agent', 'session', 'branch', 'workspace', 'since'] as $name) {
            $value = $input->getOption($name);
            if (is_string($value) && $value !== '') {
                $filters[$name] = $value;
            }
        }
        if (isset($filters['status']) && !in_array($filters['status'], ['completed', 'stopped', 'failed'], true)) {
            throw new InvalidArgumentException('--status must be completed, stopped, or failed.');
        }
        if (isset($filters['since'])) {
            try {
                new DateTimeImmutable($filters['since']);
            } catch (\Throwable) {
                throw new InvalidArgumentException('--since must be an ISO-8601 timestamp.');
            }
        }

        return $filters;
    }
}
