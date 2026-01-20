<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Config;

use Cognesy\Config\BasePath;
use Symfony\Component\Yaml\Yaml;

final readonly class ExampleGroupingConfig
{
    public function __construct(
        private string $path = 'config/examples-groups.yaml',
    ) {}

    public function load(): ExampleGrouping
    {
        $config = $this->loadConfig();
        if ($config === null) {
            return ExampleGrouping::empty();
        }

        return $this->groupingFromConfig($config);
    }

    private function loadConfig(): ?array
    {
        $fullPath = BasePath::get($this->path);
        if (!file_exists($fullPath)) {
            return null;
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            return null;
        }

        $config = Yaml::parse($content);
        if (!is_array($config)) {
            return null;
        }

        return $config;
    }

    private function groupingFromConfig(array $config): ExampleGrouping
    {
        $rawGroups = $config['groups'] ?? [];
        if (!is_array($rawGroups)) {
            return ExampleGrouping::empty();
        }

        $groups = [];
        foreach ($rawGroups as $index => $rawGroup) {
            $group = $this->groupFromConfig($rawGroup, (int) $index);
            if ($group === null) {
                continue;
            }
            $groups[] = $group;
        }

        return ExampleGrouping::fromArray($groups);
    }

    private function groupFromConfig(mixed $rawGroup, int $index): ?ExampleGroupDefinition
    {
        if (!is_array($rawGroup)) {
            return null;
        }

        $id = $rawGroup['id'] ?? null;
        $title = $rawGroup['title'] ?? null;
        if (!is_string($id) || $id === '') {
            return null;
        }
        if (!is_string($title) || $title === '') {
            return null;
        }

        $subgroups = $this->subgroupsFromConfig($rawGroup['subgroups'] ?? []);
        return ExampleGroupDefinition::fromConfig($id, $title, $index, $subgroups);
    }

    private function subgroupsFromConfig(mixed $rawSubgroups): ExampleSubgroupDefinitions
    {
        if (!is_array($rawSubgroups)) {
            return ExampleSubgroupDefinitions::empty();
        }

        $subgroups = [];
        foreach ($rawSubgroups as $index => $rawSubgroup) {
            $subgroup = $this->subgroupFromConfig($rawSubgroup, (int) $index);
            if ($subgroup === null) {
                continue;
            }
            $subgroups[] = $subgroup;
        }

        return ExampleSubgroupDefinitions::fromArray($subgroups);
    }

    private function subgroupFromConfig(mixed $rawSubgroup, int $index): ?ExampleSubgroupDefinition
    {
        if (!is_array($rawSubgroup)) {
            return null;
        }

        $id = $rawSubgroup['id'] ?? null;
        $title = $rawSubgroup['title'] ?? null;
        if (!is_string($id) || $id === '') {
            return null;
        }
        if (!is_string($title) || $title === '') {
            return null;
        }

        $includes = $this->rulesFromConfig($rawSubgroup['include'] ?? []);
        $excludes = $this->rulesFromConfig($rawSubgroup['exclude'] ?? []);

        return ExampleSubgroupDefinition::fromConfig($id, $title, $index, $includes, $excludes);
    }

    private function rulesFromConfig(mixed $rawRules): ExampleMatchRules
    {
        if (!is_array($rawRules)) {
            return ExampleMatchRules::empty();
        }

        $rules = [];
        foreach ($rawRules as $rawRule) {
            $rule = $this->ruleFromConfig($rawRule);
            if ($rule === null) {
                continue;
            }
            $rules[] = $rule;
        }

        return ExampleMatchRules::fromArray($rules);
    }

    private function ruleFromConfig(mixed $rawRule): ?ExampleMatchRule
    {
        if (!is_array($rawRule)) {
            return null;
        }

        $path = $rawRule['path'] ?? null;
        if (!is_string($path) || $path === '') {
            return null;
        }

        $source = $rawRule['source'] ?? null;
        $sourceId = is_string($source) && $source !== '' ? $source : null;

        return ExampleMatchRule::fromConfig($path, $sourceId);
    }
}
