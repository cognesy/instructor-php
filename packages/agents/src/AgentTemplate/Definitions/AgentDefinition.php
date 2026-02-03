<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentTemplate\Definitions;

use Cognesy\Agents\Core\Collections\NameList;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\LLMProvider;
use InvalidArgumentException;

final readonly class AgentDefinition
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $name,
        public string $description,
        public string $systemPrompt,
        public LLMConfig|string|null $model = null,
        public ?NameList $tools = null,
        public ?NameList $toolsDeny = null,
        public ?NameList $skills = null,
        public ?string $blueprint = null,
        public ?string $blueprintClass = null,
        public ?int $maxSteps = null,
        public ?int $maxTokens = null,
        public ?int $timeoutSec = null,
        public NameList $capabilities = new NameList(),
        public array $metadata = [],
        public ?int $version = null,
        public ?string $id = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $name = self::requireString($data, 'name');
        $description = self::requireString($data, 'description');

        $systemPrompt = self::extractString($data, 'system_prompt')
            ?? self::extractString($data, 'systemPrompt')
            ?? '';
        $systemPrompt = trim($systemPrompt);
        if ($systemPrompt === '') {
            throw new InvalidArgumentException("Missing or empty 'system_prompt' field in agent definition");
        }

        $blueprint = self::extractString($data, 'blueprint');
        $blueprintClass = self::extractString($data, 'blueprint_class')
            ?? self::extractString($data, 'blueprintClass');
        if ($blueprint !== null && $blueprintClass !== null) {
            throw new InvalidArgumentException("Provide only one of 'blueprint' or 'blueprint_class'");
        }

        $model = self::parseModel($data['model'] ?? $data['llm'] ?? null);
        $tools = self::parseNameList($data['tools'] ?? null, 'tools');
        $toolsDeny = self::parseNameList($data['tools_deny'] ?? $data['toolsDeny'] ?? null, 'tools_deny');
        $skills = self::parseNameList($data['skills'] ?? null, 'skills');
        $capabilities = self::parseNameList($data['capabilities'] ?? null, 'capabilities')
            ?? new NameList();

        // Support nested tools.allow / tools.deny format
        if ($tools === null && $toolsDeny === null && isset($data['tools']) && is_array($data['tools'])) {
            $toolsData = $data['tools'];
            if (isset($toolsData['allow']) || isset($toolsData['deny'])) {
                $tools = self::parseNameList($toolsData['allow'] ?? null, 'tools.allow');
                $toolsDeny = self::parseNameList($toolsData['deny'] ?? null, 'tools.deny');
            }
        }

        $maxSteps = self::extractInt($data, 'max_steps')
            ?? self::extractInt($data, 'maxSteps')
            ?? self::extractNestedInt($data, 'execution', 'max_steps');
        $maxTokens = self::extractInt($data, 'max_tokens')
            ?? self::extractInt($data, 'maxTokens')
            ?? self::extractNestedInt($data, 'execution', 'max_tokens');
        $timeoutSec = self::extractInt($data, 'timeout_sec')
            ?? self::extractInt($data, 'timeoutSec')
            ?? self::extractNestedInt($data, 'execution', 'timeout_sec');

        $metadata = $data['metadata'] ?? [];
        if (!is_array($metadata)) {
            throw new InvalidArgumentException("Invalid 'metadata' field: must be a map");
        }

        $version = isset($data['version']) && is_int($data['version']) ? $data['version'] : null;
        $id = self::extractString($data, 'id');

        return new self(
            name: $name,
            description: $description,
            systemPrompt: $systemPrompt,
            model: $model,
            tools: $tools,
            toolsDeny: $toolsDeny,
            skills: $skills,
            blueprint: $blueprint,
            blueprintClass: $blueprintClass,
            maxSteps: $maxSteps,
            maxTokens: $maxTokens,
            timeoutSec: $timeoutSec,
            capabilities: $capabilities,
            metadata: $metadata,
            version: $version,
            id: $id,
        );
    }

    // ACCESSORS ////////////////////////////////////////////////////

    public function id(): string
    {
        return $this->id ?? $this->name;
    }

    public function inheritsAllTools(): bool
    {
        return $this->tools === null;
    }

    public function hasSkills(): bool
    {
        return $this->skills !== null && !$this->skills->isEmpty();
    }

    public function shouldInheritModel(): bool
    {
        return $this->model === 'inherit';
    }

    public function filterTools(Tools $parentTools): Tools
    {
        if ($this->inheritsAllTools()) {
            return $parentTools;
        }

        $filtered = [];
        foreach ($parentTools->all() as $tool) {
            if ($this->tools?->has($tool->name()) ?? false) {
                $filtered[] = $tool;
            }
        }

        return new Tools(...$filtered);
    }

    public function resolveLlmProvider(?LLMProvider $parentProvider = null): LLMProvider
    {
        return match (true) {
            $this->model instanceof LLMConfig => LLMProvider::new()->withConfig($this->model),
            $this->model === 'inherit' => $parentProvider ?? LLMProvider::new(),
            is_string($this->model) => LLMProvider::using($this->model),
            default => LLMProvider::new(),
        };
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'system_prompt' => $this->systemPrompt,
            'model' => $this->serializeModel(),
            'tools' => $this->tools?->toArray(),
            'tools_deny' => $this->toolsDeny?->toArray(),
            'skills' => $this->skills?->toArray(),
            'blueprint' => $this->blueprint,
            'blueprint_class' => $this->blueprintClass,
            'max_steps' => $this->maxSteps,
            'max_tokens' => $this->maxTokens,
            'timeout_sec' => $this->timeoutSec,
            'capabilities' => $this->capabilities->toArray(),
            'metadata' => $this->metadata,
        ];
    }

    // PRIVATE HELPERS //////////////////////////////////////////////

    private static function requireString(array $data, string $key): string
    {
        if (!isset($data[$key]) || !is_string($data[$key])) {
            throw new InvalidArgumentException("Missing or invalid '{$key}' field in agent definition");
        }
        $value = trim($data[$key]);
        if ($value === '') {
            throw new InvalidArgumentException("'{$key}' field cannot be empty in agent definition");
        }
        return $value;
    }

    private static function extractString(array $data, string $key): ?string
    {
        if (!isset($data[$key]) || !is_string($data[$key])) {
            return null;
        }
        $value = trim($data[$key]);
        return $value !== '' ? $value : null;
    }

    private static function extractInt(array $data, string $key): ?int
    {
        if (!isset($data[$key]) || !is_int($data[$key])) {
            return null;
        }
        return $data[$key];
    }

    private static function extractNestedInt(array $data, string $section, string $key): ?int
    {
        if (!isset($data[$section]) || !is_array($data[$section])) {
            return null;
        }
        return self::extractInt($data[$section], $key);
    }

    private static function parseModel(mixed $model): LLMConfig|string|null
    {
        if ($model === null) {
            return null;
        }
        if (is_string($model)) {
            return $model;
        }
        if (is_array($model)) {
            // Support nested llm.preset format — treat preset string as model string
            if (isset($model['preset']) && is_string($model['preset'])) {
                return $model['preset'];
            }
            return LLMConfig::fromArray($model);
        }
        throw new InvalidArgumentException(
            "Invalid 'model' field: must be string (preset name) or object (LLMConfig)"
        );
    }

    private static function parseNameList(mixed $value, string $field): ?NameList
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $names = array_filter(
                array_map('trim', explode(',', $value)),
                fn(string $s) => $s !== ''
            );
            return NameList::fromArray($names);
        }
        if (is_array($value)) {
            // Check if it's a map with allow/deny keys (nested tools format)
            if (isset($value['allow']) || isset($value['deny'])) {
                return null; // handled by caller
            }
            return NameList::fromArray(array_map(fn($v) => is_string($v) ? trim($v) : $v, $value));
        }
        throw new InvalidArgumentException(
            "Invalid '{$field}' field: must be comma-separated string or array"
        );
    }

    private function serializeModel(): array|string|null
    {
        if ($this->model instanceof LLMConfig) {
            return $this->model->toArray();
        }
        return $this->model;
    }
}
