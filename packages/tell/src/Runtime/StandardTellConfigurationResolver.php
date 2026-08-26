<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Tell\Contracts\CanReadTellBranchConfiguration;
use Cognesy\Tell\Contracts\CanResolveTellConfiguration;
use Cognesy\Tell\Contracts\CanResolveTellPaths;
use Cognesy\Tell\Contracts\Data\TellEffectiveConfiguration;
use Cognesy\Tell\TellRequest;

/** One immutable precedence path: request > branch > host/project > user > bundled. */
final readonly class StandardTellConfigurationResolver implements CanResolveTellConfiguration
{
    /** @param array<string, int|list<string>|string> $hostSettings */
    public function __construct(
        private CanResolveTellPaths $paths,
        private ?CanReadTellBranchConfiguration $branches = null,
        private array $hostSettings = [],
    ) {}

    public function resolve(TellRequest $request): TellEffectiveConfiguration
    {
        $paths = $this->paths->resolve($request->directory);
        $branch = $request->session === null ? $this->branches?->read($request->directory, $request->branch) : null;
        $branchValues = $branch->values ?? [];
        $host = [
            ...TellPolicyDefaults::fromFile($request->directory.'/.tell/config/defaults.json'),
            ...$this->hostSettings,
        ];
        $user = TellPolicyDefaults::fromFile($paths->configDirectory.'/execution-defaults.json');
        $settings = [...$host, ...$branchValues];
        $effective = $request->withBranchConfig($settings)->withPolicy(TellExecutionPolicy::resolve(
            branchValues: $branchValues,
            cliOverrides: $request->policyOverrides,
            projectDefaults: $this->integers($host),
            userDefaults: $user,
        ));
        $provenance = array_map(
            static fn (string $source): string => match ($source) {
                'cli' => 'request',
                'project' => 'host',
                default => $source,
            },
            $effective->policy?->provenance() ?? [],
        );
        foreach (['connection', 'model', 'reasoningEffort', 'tools'] as $key) {
            $explicit = match ($key) {
                'connection' => $request->connectionExplicit,
                'model' => $request->modelExplicit,
                'reasoningEffort' => $request->reasoningEffortExplicit,
                'tools' => $request->toolsExplicit,
            };
            $provenance[$key] = match (true) {
                $explicit => 'request',
                array_key_exists($key, $branchValues) => 'branch',
                array_key_exists($key, $host) => 'host',
                default => 'bundled',
            };
        }

        return new TellEffectiveConfiguration($effective, $provenance, $branch);
    }

    /** @param array<string, mixed> $values @return array<string, int> */
    private function integers(array $values): array
    {
        return array_filter($values, static fn (mixed $value): bool => is_int($value));
    }
}
