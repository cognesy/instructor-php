<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Command;

use Cognesy\Tell\Adapter\Console\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Adapter\Console\Operational\OperationalPlane;
use Cognesy\Tell\Adapter\Console\Operational\PlaneOperation;
use Cognesy\Tell\Adapter\Console\Render\StructuredOutput;
use Cognesy\Tell\Core\Contract\Workspace\CanAccessTellConversations;
use Cognesy\Tell\Core\Contract\Workspace\CanConfigureTellBranch;
use Cognesy\Tell\Core\Workspace\WorkspaceException;
use Cognesy\Tell\Data\TellBranchConfig;
use InvalidArgumentException;
use JsonException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ConfigCommand extends Command implements CanDescribeOperationalPlane
{
    public function __construct(
        private readonly CanAccessTellConversations $conversations,
    ) {
        parent::__construct('config');
    }

    #[Override]
    protected function configure(): void {
        $this->setDescription('Show or atomically change secret-free branch runtime intent')
            ->setHelp(<<<'HELP'
Configuration contains branch-local runtime intent only: connection labels,
model and tool overrides, and bounded execution policy. It never stores keys,
tokens, headers, raw environment values, or credential-bearing DSNs.

Precedence for execution policy is CLI flags, branch intent, project defaults,
user defaults, then bundled defaults. `effective` shows the resolved policy
provenance; credentials remain outside this command.

`set` and `delete` require the version returned by `show` or `effective`, so a
concurrent edit fails without overwriting the other writer.
HELP)
            ->addArgument('action', InputArgument::OPTIONAL, 'show, get, set, delete, or effective', 'show')
            ->addArgument('key', InputArgument::OPTIONAL)
            ->addArgument('value', InputArgument::OPTIONAL, 'JSON value for set')
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'Select branch without checkout')
            ->addOption('if-version', null, InputOption::VALUE_REQUIRED, 'Expected config version for set/delete')
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Workspace directory', '')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $requested = $this->branch($input);
            $configuration = $this->conversations->configuration($this->directory($input), $requested);
            $shown = $configuration->show();
            $branch = [
                'name' => $shown->branch,
                'source' => $requested === null ? 'current' : 'invocation',
            ];
            $payload = match ((string) $input->getArgument('action')) {
                'show' => $this->show($branch, $configuration, $shown),
                'effective' => $this->effective($branch, $configuration),
                'get' => $this->get($branch, $configuration, $input),
                'set' => $this->change($configuration, $branch, $input, false),
                'delete' => $this->change($configuration, $branch, $input, true),
                default => throw new InvalidArgumentException('Unknown config action.'),
            };
            (new StructuredOutput($output))->write($payload, json: (bool) $input->getOption('json'));

            return Command::SUCCESS;
        } catch (InvalidArgumentException $error) {
            (new StructuredOutput($output))->write(['error' => $error->getMessage()], json: (bool) $input->getOption('json'));

            return Command::INVALID;
        } catch (WorkspaceException $error) {
            (new StructuredOutput($output))->write(['error' => $error->getMessage()], json: (bool) $input->getOption('json'));

            return Command::FAILURE;
        }
    }

    #[Override]
    public function planeOperation(): PlaneOperation {
        return new PlaneOperation(
            plane: OperationalPlane::Management,
            command: 'config',
            responsibility: 'Read or atomically update typed secret-free branch intent.',
            ownedState: 'One branch-local config record.',
            input: 'Explicit action, selected branch, allowed key, JSON value, and expected version.',
            output: 'Versioned intent with field-level provenance.',
            authority: 'Read and atomically replace only selected config.',
            degradedBehavior: 'Rejects secrets, unknown keys, invalid values, and stale versions without partial writes.',
        );
    }

    /** @param array{name: string, source: string} $branch @return array<string, mixed> */
    private function show(array $branch, CanConfigureTellBranch $configuration, TellBranchConfig $config): array {
        return [
            'branch' => $branch,
            'version' => $config->version,
            'values' => $config->values,
            'allowedKeys' => $configuration->allowedKeys(),
        ];
    }

    /** @param array{name: string, source: string} $branch @return array<string, mixed> */
    private function effective(array $branch, CanConfigureTellBranch $configuration): array {
        $config = $configuration->effective();

        return [
            'branch' => $branch,
            'version' => $config->version,
            'values' => $config->values,
            'provenance' => $config->provenance,
            'precedence' => ['cli', 'branch', 'project', 'user', 'bundled'],
            'connectionResolution' => $config->connection,
        ];
    }

    /** @param array{name: string, source: string} $branch @return array<string, mixed> */
    private function get(array $branch, CanConfigureTellBranch $configuration, InputInterface $input): array {
        $key = $this->key($configuration, $input);
        $config = $configuration->effective();

        return [
            'branch' => $branch,
            'version' => $config->version,
            'key' => $key,
            'value' => $config->values[$key] ?? null,
            'source' => $config->provenance[$key] ?? 'bundled',
        ];
    }

    /** @param array{name: string, source: string} $branch @return array<string, mixed> */
    private function change(CanConfigureTellBranch $configuration, array $branch, InputInterface $input, bool $delete): array {
        $version = $this->version($input);
        $key = $this->key($configuration, $input);
        $config = $delete
            ? $configuration->delete($key, $version)
            : $configuration->set($key, $this->jsonValue($input), $version);

        return [
            'branch' => $branch,
            'version' => $config->version,
            'values' => $config->values,
            'changed' => true,
        ];
    }

    private function version(InputInterface $input): int {
        $version = $input->getOption('if-version');
        if (!is_string($version) || !ctype_digit($version)) {
            throw new InvalidArgumentException('--if-version is required for config set and delete.');
        }

        return (int) $version;
    }

    private function key(CanConfigureTellBranch $configuration, InputInterface $input): string {
        $key = $input->getArgument('key');
        if (!is_string($key) || !in_array($key, $configuration->allowedKeys(), true)) {
            throw new InvalidArgumentException('Config key must be one of: ' . implode(', ', $configuration->allowedKeys()) . '.');
        }

        return $key;
    }

    private function jsonValue(InputInterface $input): mixed {
        $raw = $input->getArgument('value');
        if (!is_string($raw)) {
            throw new InvalidArgumentException('Config set requires a JSON value.');
        }
        try {
            return json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Config set value must be valid JSON.', previous: $error);
        }
    }

    private function directory(InputInterface $input): string {
        $directory = (string) $input->getOption('dir');
        $cwd = getcwd();
        $directory = $directory !== '' ? $directory : (is_string($cwd) ? $cwd : '.');
        return $directory;
    }

    private function branch(InputInterface $input): ?string {
        $requested = $input->getOption('branch');
        if ($requested !== null && !is_string($requested)) {
            throw new InvalidArgumentException('Tell branch selector must be a string.');
        }

        return $requested === '' ? null : $requested;
    }
}
