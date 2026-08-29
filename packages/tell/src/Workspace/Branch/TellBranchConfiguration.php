<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Branch;

use Cognesy\Tell\Configuration\TellExecutionPolicy;
use Cognesy\Tell\Configuration\TellPolicyDefaults;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Discovery\TellProviderCatalogue;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Branch\Storage\BranchConfigStore;
use Cognesy\Tell\Workspace\WorkspaceState as StoredWorkspace;
use InvalidArgumentException;

/**
 * Versioned, secret-free runtime intent for one selected Tell workspace branch.
 *
 * Set and delete require the version returned by show() or effective(), so a
 * concurrent writer cannot silently overwrite a consumer's configuration.
 */
final readonly class TellBranchConfiguration
{
    public function __construct(
        private TellAgentFactory $agents,
        private string $directory,
        private ?string $branch = null,
    ) {}

    /** @return list<string> */
    public function allowedKeys(): array {
        return (new BranchConfigStore($this->workspace()))->keys();
    }

    public function show(): TellBranchConfig {
        $workspace = $this->workspace();
        $branch = $this->branch($workspace);
        $config = (new BranchConfigStore($workspace))->read($branch);

        return new TellBranchConfig($branch, $config['version'], $config['values']);
    }

    public function effective(?TellRequest $request = null): TellBranchConfig {
        $workspace = $this->workspace();
        $branch = $this->branch($workspace);
        $store = new BranchConfigStore($workspace);
        $effective = $store->effective($branch);
        $policy = TellExecutionPolicy::resolve(
            branchValues: $store->read($branch)['values'],
            projectDefaults: TellPolicyDefaults::fromFile($workspace->paths->config . '/defaults.json'),
            userDefaults: TellPolicyDefaults::fromFile($this->agents->paths()->configDirectory . '/execution-defaults.json'),
        );
        foreach ($policy->values() as $key => $value) {
            $effective['values'][$key] = $value;
            $effective['provenance'][$key] = $policy->provenance()[$key];
        }
        if ($request?->reasoningEffort !== null && $request->reasoningEffortExplicit) {
            $effective['values']['reasoningEffort'] = $request->reasoningEffort->value;
            $effective['provenance']['reasoningEffort'] = 'invocation';
        }

        return new TellBranchConfig(
            branch: $branch,
            version: $effective['version'],
            values: $effective['values'],
            provenance: $effective['provenance'],
            precedence: ['invocation', 'branch', 'project', 'user', 'bundled'],
            connection: $this->connection($workspace, $effective),
        );
    }

    public function set(string $key, mixed $value, int $expectedVersion): TellBranchConfig {
        $workspace = $this->workspace();
        $branch = $this->branch($workspace);
        $config = (new BranchConfigStore($workspace))->set($branch, $key, $value, $expectedVersion);

        return new TellBranchConfig($branch, $config['version'], $config['values']);
    }

    public function delete(string $key, int $expectedVersion): TellBranchConfig {
        $workspace = $this->workspace();
        $branch = $this->branch($workspace);
        $config = (new BranchConfigStore($workspace))->delete($branch, $key, $expectedVersion);

        return new TellBranchConfig($branch, $config['version'], $config['values']);
    }

    private function workspace(): StoredWorkspace {
        $workspace = $this->agents->workspace()->discover($this->directory);
        if ($workspace === null) {
            throw new InvalidArgumentException('Tell branch configuration requires an initialized workspace; call workspace()->initialize() first.');
        }

        return $workspace;
    }

    private function branch(StoredWorkspace $workspace): string {
        return (new BranchResolver(new FilesystemArena($workspace), $workspace))->resolve($this->branch)->branch;
    }

    /** @param array{values: array<string, mixed>, provenance: array<string, string>} $effective */
    private function connection(StoredWorkspace $workspace, array $effective): array {
        $connection = is_string($effective['values']['connection'] ?? null)
            ? $effective['values']['connection']
            : 'openai';
        $model = is_string($effective['values']['model'] ?? null)
            ? $effective['values']['model']
            : '';
        try {
            $result = (new TellProviderCatalogue($this->agents->paths()))->resolve($workspace->paths->root, $connection, $model);
            $result['connectionSource'] = $effective['provenance']['connection'] ?? 'bundled';
            $result['modelSource'] = $model === ''
                ? 'preset'
                : ($effective['provenance']['model'] ?? 'branch');

            return $result;
        } catch (InvalidArgumentException $error) {
            return [
                'connection' => $connection,
                'model' => $model === '' ? null : $model,
                'error' => $error->getMessage(),
            ];
        }
    }
}
