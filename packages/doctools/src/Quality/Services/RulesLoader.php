<?php declare(strict_types=1);

namespace Cognesy\Doctools\Quality\Services;

use Cognesy\Doctools\Quality\Data\QualityRule;
use Cognesy\Doctools\Quality\Data\QualityRuleEngine;
use Cognesy\Doctools\Quality\Data\QualityRuleScope;
use Cognesy\Doctools\Quality\Data\QualityRuleSet;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

final readonly class RulesLoader
{
    public function load(string $path): QualityRuleSet
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("Rules file not found: {$path}");
        }

        /** @var mixed $raw */
        $raw = Yaml::parseFile($path);
        if (!is_array($raw)) {
            throw new InvalidArgumentException("Rules file must contain a YAML object: {$path}");
        }

        $version = $raw['version'] ?? null;
        if ($version !== 1) {
            throw new InvalidArgumentException("Rules file {$path} must declare `version: 1`.");
        }

        $rawRules = $raw['rules'] ?? null;
        if (!is_array($rawRules)) {
            throw new InvalidArgumentException("Rules file {$path} must define a `rules` list.");
        }

        $rules = [];
        foreach ($rawRules as $index => $rawRule) {
            if (!is_array($rawRule)) {
                throw new InvalidArgumentException("Rule at index {$index} in {$path} must be a YAML object.");
            }
            $rules[] = $this->toRule($rawRule, $path, $index);
        }

        return new QualityRuleSet(sourcePath: $path, rules: $rules);
    }

    /**
     * @param array<string, mixed> $rawRule
     */
    private function toRule(array $rawRule, string $path, int $index): QualityRule
    {
        $id = $this->requiredString($rawRule, 'id', $path, $index);
        $engineRaw = $this->requiredString($rawRule, 'engine', $path, $index);
        $scopeRaw = $this->requiredString($rawRule, 'scope', $path, $index);
        $pattern = $this->requiredString($rawRule, 'pattern', $path, $index);
        $message = $this->requiredString($rawRule, 'message', $path, $index);
        $severity = $this->optionalString($rawRule, 'severity');
        $language = $this->optionalString($rawRule, 'language');

        $engine = QualityRuleEngine::fromString($engineRaw);
        $scope = QualityRuleScope::fromString($scopeRaw);
        if ($engine === QualityRuleEngine::AstGrep && $language === null) {
            throw new InvalidArgumentException("Rule `{$id}` in {$path} requires `language` for ast-grep engine.");
        }

        return new QualityRule(
            id: $id,
            engine: $engine,
            scope: $scope,
            pattern: $pattern,
            message: $message,
            severity: $severity ?? 'error',
            language: $language,
        );
    }

    /**
     * @param array<string, mixed> $rawRule
     */
    private function requiredString(array $rawRule, string $key, string $path, int $index): string
    {
        $value = $this->optionalString($rawRule, $key);
        if ($value === null) {
            throw new InvalidArgumentException("Rule index {$index} in {$path} is missing required string `{$key}`.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $rawRule
     */
    private function optionalString(array $rawRule, string $key): ?string
    {
        $value = $rawRule[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}

