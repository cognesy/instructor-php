<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentTemplate\Spec;

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class AgentSpecParser
{
    /**
     * Parse an agent spec from a markdown file.
     *
     * @param string $path Absolute path to .md file
     * @return AgentSpec
     * @throws RuntimeException if file cannot be read
     * @throws InvalidArgumentException if format is invalid
     */
    public function parseMarkdownFile(string $path): AgentSpec {
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Failed to read agent spec file: {$path}");
        }

        return $this->parseMarkdown($content);
    }

    /**
     * Parse an agent spec from markdown content.
     *
     * @param string $content Markdown with YAML frontmatter
     * @return AgentSpec
     * @throws InvalidArgumentException if format is invalid
     */
    public function parseMarkdown(string $content): AgentSpec {
        $content = str_replace("\r\n", "\n", $content);
        // Extract YAML frontmatter
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            throw new InvalidArgumentException(
                "Invalid markdown format: missing YAML frontmatter (expected ---\\n...\\n---)"
            );
        }

        $frontmatter = $this->parseYaml($matches[1]);
        $systemPrompt = trim($matches[2]);

        if ($systemPrompt === '') {
            throw new InvalidArgumentException(
                "Invalid markdown format: system prompt (content after frontmatter) cannot be empty"
            );
        }

        return $this->createSpec($frontmatter, $systemPrompt);
    }

    /**
     * Parse an agent spec from JSON data.
     *
     * @param array<string, mixed> $data JSON-decoded data
     * @return AgentSpec
     * @throws InvalidArgumentException if required fields missing
     */
    public function parseJson(array $data): AgentSpec {
        // Extract system prompt (support multiple field names)
        $systemPrompt = $data['systemPrompt'] ?? $data['prompt'] ?? $data['system'] ?? '';
        unset($data['systemPrompt'], $data['prompt'], $data['system']);

        if ($systemPrompt === '') {
            throw new InvalidArgumentException(
                "Missing 'systemPrompt' field in JSON data"
            );
        }

        return $this->createSpec($data, $systemPrompt);
    }

    // INTERNAL /////////////////////////////////////////////////////

    /**
     * Parse YAML string into array.
     *
     * @param string $yaml YAML content
     * @return array<string, mixed>
     * @throws InvalidArgumentException if YAML is invalid
     */
    private function parseYaml(string $yaml): array {
        try {
            $parsed = Yaml::parse(trim($yaml));
            if (!is_array($parsed)) {
                throw new InvalidArgumentException("YAML frontmatter must be a dictionary/object");
            }
            return $parsed;
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(
                "Invalid YAML frontmatter: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Create AgentSpec from parsed data and system prompt.
     *
     * @param array<string, mixed> $data Parsed frontmatter
     * @param string $systemPrompt System prompt content
     * @return AgentSpec
     * @throws InvalidArgumentException if required fields missing or invalid
     */
    private function createSpec(array $data, string $systemPrompt): AgentSpec {
        // Required fields
        $name = $data['name'] ?? throw new InvalidArgumentException("Missing 'name' field");
        $description = $data['description'] ?? throw new InvalidArgumentException("Missing 'description' field");

        // Parse tools (comma-separated string or array)
        $tools = $this->parseTools($data['tools'] ?? null);

        // Parse model (string preset, LLMConfig array, or null)
        $model = $this->parseModel($data['model'] ?? null);

        // Parse skills (comma-separated string or array)
        $skills = $this->parseSkills($data['skills'] ?? null);

        // Metadata (everything else)
        $metadata = $data['metadata'] ?? [];

        return new AgentSpec(
            name: $name,
            description: $description,
            systemPrompt: $systemPrompt,
            tools: $tools,
            model: $model,
            skills: $skills,
            metadata: $metadata,
        );
    }

    /**
     * Parse tools field (string or array).
     *
     * @param mixed $tools Tools data
     * @return array<string>|null
     */
    private function parseTools(mixed $tools): ?array {
        if ($tools === null) {
            return null;
        }

        if (is_string($tools)) {
            // Comma-separated list
            return array_filter(
                array_map('trim', explode(',', $tools)),
                fn($t) => $t !== ''
            );
        }

        if (is_array($tools)) {
            return array_map('trim', $tools);
        }

        throw new InvalidArgumentException(
            "Invalid 'tools' field: must be comma-separated string or array"
        );
    }

    /**
     * Parse model field (string preset, LLMConfig array, or null).
     *
     * @param mixed $model Model data
     * @return LLMConfig|string|null
     */
    private function parseModel(mixed $model): LLMConfig|string|null {
        if ($model === null) {
            return null;
        }

        if (is_string($model)) {
            return $model; // Preset name or 'inherit'
        }

        if (is_array($model)) {
            // LLMConfig object
            return LLMConfig::fromArray($model);
        }

        throw new InvalidArgumentException(
            "Invalid 'model' field: must be string (preset name) or object (LLMConfig)"
        );
    }

    /**
     * Parse skills field (string or array).
     *
     * @param mixed $skills Skills data
     * @return array<string>|null
     */
    private function parseSkills(mixed $skills): ?array {
        if ($skills === null) {
            return null;
        }

        if (is_string($skills)) {
            // Comma-separated list
            return array_filter(
                array_map('trim', explode(',', $skills)),
                fn($s) => $s !== ''
            );
        }

        if (is_array($skills)) {
            return array_map('trim', $skills);
        }

        throw new InvalidArgumentException(
            "Invalid 'skills' field: must be comma-separated string or array"
        );
    }
}
