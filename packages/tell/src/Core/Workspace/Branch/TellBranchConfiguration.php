<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Workspace\Branch;

use Cognesy\Tell\Core\Contract\Workspace\CanConfigureTellBranch;
use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellWorkspace;
use Cognesy\Tell\Data\TellBranchConfig;
use Cognesy\Tell\Data\TellExecutionPolicy;
use Cognesy\Tell\Core\Paths\TellPaths;
use Cognesy\Tell\Core\Configuration\TellPolicyDefaults;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Core\Contract\Discovery\CanCatalogueTellProviders;
use Cognesy\Tell\Core\Workspace\TellWorkspaceContext;
use InvalidArgumentException;

/**
 * Versioned, secret-free runtime intent for one selected Tell workspace branch.
 *
 * Set and delete require the version returned by show() or effective(), so a
 * concurrent writer cannot silently overwrite a consumer's configuration.
 */
final readonly class TellBranchConfiguration implements CanConfigureTellBranch
{
    public function __construct(
        private CanOpenTellWorkspace $workspaces,
        private TellPaths $paths,
        private CanCatalogueTellProviders $providers,
        private string $directory,
        private ?string $branch = null,
    ) {}

    /** @return list<string> */
    #[\Override]
    public function allowedKeys(): array {
        return $this->workspace()->branchConfiguration->keys();
    }

    #[\Override]
    public function show(): TellBranchConfig {
        $workspace = $this->workspace();
        $branch = $this->branch($workspace);
        $config = $workspace->branchConfiguration->read($branch);

        return new TellBranchConfig($branch, $config['version'], $config['values']);
    }

    #[\Override]
    public function effective(?TellRequest $request = null): TellBranchConfig {
        $workspace = $this->workspace();
        $branch = $this->branch($workspace);
        $store = $workspace->branchConfiguration;
        $effective = $store->effective($branch);
        $policy = TellExecutionPolicy::resolve(
            branchValues: $store->read($branch)['values'],
            projectDefaults: $workspace->branchConfiguration->executionDefaults(),
            userDefaults: TellPolicyDefaults::fromFile($this->paths->configDirectory . '/execution-defaults.json'),
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

    #[\Override]
    public function set(string $key, mixed $value, int $expectedVersion): TellBranchConfig {
        $workspace = $this->workspace();
        $branch = $this->branch($workspace);
        $config = $workspace->branchConfiguration->set($branch, $key, $value, $expectedVersion);

        return new TellBranchConfig($branch, $config['version'], $config['values']);
    }

    #[\Override]
    public function delete(string $key, int $expectedVersion): TellBranchConfig {
        $workspace = $this->workspace();
        $branch = $this->branch($workspace);
        $config = $workspace->branchConfiguration->delete($branch, $key, $expectedVersion);

        return new TellBranchConfig($branch, $config['version'], $config['values']);
    }

    private function workspace(): TellWorkspaceContext {
        return $this->workspaces->open($this->directory);
    }

    private function branch(TellWorkspaceContext $workspace): string {
        return (new BranchResolver($workspace->arena, $workspace->branchSelection))->resolve($this->branch)->branch;
    }

    /** @param array{values: array<string, mixed>, provenance: array<string, string>} $effective */
    private function connection(TellWorkspaceContext $workspace, array $effective): array {
        $connection = is_string($effective['values']['connection'] ?? null)
            ? $effective['values']['connection']
            : 'openai';
        $model = is_string($effective['values']['model'] ?? null)
            ? $effective['values']['model']
            : '';
        try {
            $result = $this->providers->resolve($workspace->info->root, $connection, $model);
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
