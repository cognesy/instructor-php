<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Tools;

use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\ToolInterface;
use Cognesy\Agents\Exceptions\InvalidToolException;

final class ToolRegistry implements ToolRegistryInterface
{
    /** @var array<string, callable(): ToolInterface> */
    private array $factories = [];

    /** @var array<string, ToolInterface> */
    private array $instances = [];

    /** @var array<string, array<string, mixed>> */
    private array $metadata = [];

    /** @var array<string, array<string, mixed>> */
    private array $fullSpecs = [];

    #[\Override]
    public function register(ToolInterface $tool): void {
        $this->instances[$tool->name()] = $tool;
        $this->metadata[$tool->name()] ??= $tool->metadata();
        $this->fullSpecs[$tool->name()] ??= $tool->instructions();
    }

    #[\Override]
    public function registerFactory(
        string $name,
        callable $factory,
        ?array $metadata = null,
        ?array $fullSpec = null,
    ): void {
        $this->factories[$name] = $factory;
        if ($metadata !== null) {
            $this->metadata[$name] = $metadata;
        }
        if ($fullSpec !== null) {
            $this->fullSpecs[$name] = $fullSpec;
        }
    }

    #[\Override]
    public function has(string $name): bool
    {
        return isset($this->instances[$name]) || isset($this->factories[$name]);
    }

    #[\Override]
    public function resolve(string $name): ToolInterface
    {
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        if (isset($this->factories[$name])) {
            $tool = ($this->factories[$name])();
            $this->instances[$name] = $tool;
            $this->metadata[$name] ??= $tool->metadata();
            $this->fullSpecs[$name] ??= $tool->instructions();
            return $tool;
        }

        throw new InvalidToolException("Tool '{$name}' not found in registry.");
    }

    #[\Override]
    public function listMetadata(?string $locale = null): array
    {
        $list = [];
        foreach ($this->names() as $name) {
            $list[] = $this->resolveMetadata($name);
        }

        return $list;
    }

    #[\Override]
    public function listFullSpecs(?string $locale = null): array
    {
        $list = [];
        foreach ($this->names() as $name) {
            $list[] = $this->resolveFullSpec($name);
        }

        return $list;
    }

    #[\Override]
    public function search(string $query, ?string $locale = null): array
    {
        $needle = mb_strtolower(trim($query));
        if ($needle === '') {
            return [];
        }

        $results = [];
        foreach ($this->names() as $name) {
            $metadata = $this->resolveMetadata($name);
            $fullSpec = $this->fullSpecs[$name] ?? null;
            $description = $metadata['description'] ?? $fullSpec['description'] ?? '';
            $summary = $metadata['summary'] ?? '';
            $namespace = $metadata['namespace'] ?? '';
            $tags = $metadata['tags'] ?? [];

            $haystack = implode(' ', array_filter([
                $metadata['name'] ?? $name,
                $summary,
                $description,
                $namespace,
                is_array($tags) ? implode(' ', $tags) : (string) $tags,
            ]));

            if (mb_strtolower($haystack) === '') {
                continue;
            }

            if (mb_strpos(mb_strtolower($haystack), $needle) !== false) {
                $metadata['description'] = $description;
                $results[] = $metadata;
            }
        }

        return $results;
    }

    #[\Override]
    public function buildTools(?array $names = null, ?ToolPolicy $policy = null): Tools
    {
        $available = $this->names();
        $candidateNames = ($names === null || $names === [])
            ? $available
            : $names;

        if ($policy !== null) {
            $candidateNames = $policy->filterNames($candidateNames, $available);
        }

        $resolved = [];
        foreach ($candidateNames as $name) {
            if (! $this->has($name)) {
                continue;
            }

            $resolved[] = $this->resolve($name);
        }

        return new Tools(...$resolved);
    }

    #[\Override]
    public function names(): array
    {
        $names = array_merge(
            array_keys($this->factories),
            array_keys($this->instances),
            array_keys($this->metadata),
            array_keys($this->fullSpecs),
        );

        $unique = [];
        foreach ($names as $name) {
            if (!isset($unique[$name])) {
                $unique[$name] = true;
            }
        }

        return array_keys($unique);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMetadata(string $name): array
    {
        if (! isset($this->metadata[$name])) {
            $tool = $this->resolve($name);
            $this->metadata[$name] = $tool->metadata();
        }

        return $this->metadata[$name];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFullSpec(string $name): array
    {
        if (! isset($this->fullSpecs[$name])) {
            $tool = $this->resolve($name);
            $this->fullSpecs[$name] = $tool->instructions();
        }

        return $this->fullSpecs[$name];
    }
}
