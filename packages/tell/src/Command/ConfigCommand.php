<?php

declare(strict_types=1);

namespace Cognesy\Tell\Command;

use Cognesy\Tell\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Operational\OperationalPlane;
use Cognesy\Tell\Operational\PlaneOperation;
use Cognesy\Tell\Render\StructuredOutput;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Runtime\TellExecutionPolicy;
use Cognesy\Tell\Runtime\TellPolicyDefaults;
use Cognesy\Tell\Runtime\TellProviderCatalogue;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\BranchConfigStore;
use Cognesy\Tell\Workspace\BranchResolver;
use Cognesy\Tell\Workspace\TellWorkspace;
use Cognesy\Tell\Workspace\WorkspaceException;
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
    public function __construct(private readonly TellAgentFactory $agents)
    {
        parent::__construct('config');
    }

    #[Override]
    protected function configure(): void
    {
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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $workspace = $this->workspace($input);
            $branch = $this->branch($workspace, $input);
            $store = new BranchConfigStore($workspace);
            $payload = match ((string) $input->getArgument('action')) {
                'show' => $this->show($branch->toArray(), $store, $branch->branch),
                'effective' => $this->effective($workspace, $branch->toArray(), $store, $branch->branch),
                'get' => $this->get($workspace, $branch->toArray(), $store, $branch->branch, $input),
                'set' => $this->change($store, $branch->branch, $branch->toArray(), $input, false),
                'delete' => $this->change($store, $branch->branch, $branch->toArray(), $input, true),
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
    public function planeOperation(): PlaneOperation
    {
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
    private function show(array $branch, BranchConfigStore $store, string $name): array
    {
        $config = $store->read($name);

        return [
            'branch' => $branch,
            'version' => $config['version'],
            'values' => $config['values'],
            'allowedKeys' => $store->keys(),
        ];
    }

    /** @param array{name: string, source: string} $branch @return array<string, mixed> */
    private function effective(TellWorkspace $workspace, array $branch, BranchConfigStore $store, string $name): array
    {
        $config = $store->effective($name);
        $branchValues = $store->read($name)['values'];
        $policy = TellExecutionPolicy::resolve(
            branchValues: $branchValues,
            projectDefaults: TellPolicyDefaults::fromFile($workspace->paths->config.'/defaults.json'),
            userDefaults: TellPolicyDefaults::fromFile($this->agents->paths()->configDirectory.'/execution-defaults.json'),
        );
        foreach ($policy->values() as $key => $value) {
            $config['values'][$key] = $value;
            $config['provenance'][$key] = $policy->provenance()[$key];
        }
        $connection = is_string($config['values']['connection'] ?? null) ? $config['values']['connection'] : 'openai';
        $model = is_string($config['values']['model'] ?? null) ? $config['values']['model'] : '';
        try {
            $connectionResolution = (new TellProviderCatalogue($this->agents->paths()))->resolve($workspace->paths->root, $connection, $model);
            $connectionResolution['connectionSource'] = $config['provenance']['connection'] ?? 'bundled';
            $connectionResolution['modelSource'] = $model === ''
                ? 'preset'
                : ($config['provenance']['model'] ?? 'branch');
        } catch (InvalidArgumentException $error) {
            $connectionResolution = [
                'connection' => $connection,
                'model' => $model === '' ? null : $model,
                'error' => $error->getMessage(),
            ];
        }

        return [
            'branch' => $branch,
            'version' => $config['version'],
            'values' => $config['values'],
            'provenance' => $config['provenance'],
            'precedence' => ['cli', 'branch', 'project', 'user', 'bundled'],
            'connectionResolution' => $connectionResolution,
        ];
    }

    /** @param array{name: string, source: string} $branch @return array<string, mixed> */
    private function get(TellWorkspace $workspace, array $branch, BranchConfigStore $store, string $name, InputInterface $input): array
    {
        $key = $this->key($store, $input);
        $config = $this->effective($workspace, $branch, $store, $name);

        return [
            'branch' => $branch,
            'version' => $config['version'],
            'key' => $key,
            'value' => $config['values'][$key] ?? null,
            'source' => $config['provenance'][$key] ?? 'bundled',
        ];
    }

    /** @param array{name: string, source: string} $branch @return array<string, mixed> */
    private function change(BranchConfigStore $store, string $name, array $branch, InputInterface $input, bool $delete): array
    {
        $version = $this->version($input);
        $key = $this->key($store, $input);
        $config = $delete
            ? $store->delete($name, $key, $version)
            : $store->set($name, $key, $this->jsonValue($input), $version);

        return [
            'branch' => $branch,
            'version' => $config['version'],
            'values' => $config['values'],
            'changed' => true,
        ];
    }

    private function version(InputInterface $input): int
    {
        $version = $input->getOption('if-version');
        if (! is_string($version) || ! ctype_digit($version)) {
            throw new InvalidArgumentException('--if-version is required for config set and delete.');
        }

        return (int) $version;
    }

    private function key(BranchConfigStore $store, InputInterface $input): string
    {
        $key = $input->getArgument('key');
        if (! is_string($key) || ! in_array($key, $store->keys(), true)) {
            throw new InvalidArgumentException('Config key must be one of: '.implode(', ', $store->keys()).'.');
        }

        return $key;
    }

    private function jsonValue(InputInterface $input): mixed
    {
        $raw = $input->getArgument('value');
        if (! is_string($raw)) {
            throw new InvalidArgumentException('Config set requires a JSON value.');
        }
        try {
            return json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Config set value must be valid JSON.', previous: $error);
        }
    }

    private function workspace(InputInterface $input): TellWorkspace
    {
        $directory = (string) $input->getOption('dir');
        $cwd = getcwd();
        $directory = $directory !== '' ? $directory : (is_string($cwd) ? $cwd : '.');
        $workspace = $this->agents->workspace()->discover($directory);
        if ($workspace === null) {
            throw new WorkspaceException('Tell config requires an initialized workspace; run `tell init` first.');
        }

        return $workspace;
    }

    private function branch(TellWorkspace $workspace, InputInterface $input): \Cognesy\Tell\Workspace\BranchSelection
    {
        $requested = $input->getOption('branch');
        if ($requested !== null && ! is_string($requested)) {
            throw new InvalidArgumentException('Tell branch selector must be a string.');
        }

        return (new BranchResolver(new ArenaStore($workspace)))->resolve($requested === '' ? null : $requested);
    }
}
