<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Skills;

use Cognesy\Utils\Markdown\FrontMatter;

final class SkillLibrary
{
    private const SKILL_FILENAME = 'SKILL.md';

    /** @var array<string, array{name: string, description: string, path: string, dir: string, disable-model-invocation: bool, user-invocable: bool, argument-hint: ?string}> */
    private array $metadata;

    /** @var array<string, Skill> */
    private array $loadedSkills = [];

    public function __construct(
        private readonly string $skillsPath,
    ) {
        $this->metadata = $this->scanDirectory();
    }

    public static function inDirectory(string $path): self {
        return new self($path);
    }

    /**
     * @return list<array{name: string, description: string}>
     */
    public function listSkills(
        ?bool $modelInvocable = null,
        ?bool $userInvocable = null,
    ): array {
        $list = [];
        foreach ($this->metadata as $meta) {
            if ($modelInvocable === true && ($meta['disable-model-invocation'] ?? false)) {
                continue;
            }
            if ($userInvocable === true && !($meta['user-invocable'] ?? true)) {
                continue;
            }
            $list[] = [
                'name' => $meta['name'],
                'description' => $meta['description'],
                'argument-hint' => $meta['argument-hint'] ?? null,
            ];
        }

        return $list;
    }

    public function hasSkill(string $name): bool {
        return isset($this->metadata[$name]);
    }

    public function getSkill(string $name): ?Skill {
        if (!isset($this->metadata[$name])) {
            return null;
        }

        if (isset($this->loadedSkills[$name])) {
            return $this->loadedSkills[$name];
        }

        $skill = $this->loadSkillFromFile($this->metadata[$name]['path']);
        if ($skill !== null) {
            $this->loadedSkills[$name] = $skill;
        }

        return $skill;
    }

    public function renderSkillList(
        ?bool $modelInvocable = null,
        ?bool $userInvocable = null,
    ): string {
        $skills = $this->listSkills(modelInvocable: $modelInvocable, userInvocable: $userInvocable);
        if ($skills === []) {
            return "(no skills available)";
        }

        $lines = ["Available skills:"];
        foreach ($skills as $skill) {
            $hint = !empty($skill['argument-hint']) ? " {$skill['argument-hint']}" : '';
            $lines[] = "- [{$skill['name']}{$hint}]: {$skill['description']}";
        }

        return implode("\n", $lines);
    }

    /** @return array<string, array{name: string, description: string, path: string, dir: string, disable-model-invocation: bool, user-invocable: bool, argument-hint: ?string}> */
    private function scanDirectory(): array {
        if (!is_dir($this->skillsPath)) {
            return [];
        }

        $metadata = [];
        foreach ($this->findSkillFiles() as $file) {
            $meta = $this->extractMetadata($file);
            if ($meta === null) {
                continue;
            }

            $metadata[$meta['name']] ??= $meta;
        }

        return $metadata;
    }

    /** @return list<string> */
    private function findSkillFiles(): array {
        $skillFiles = glob($this->skillsPath . '/*/' . self::SKILL_FILENAME);

        return ($skillFiles !== false) ? $skillFiles : [];
    }

    /** @return array{name: string, description: string, path: string, dir: string, disable-model-invocation: bool, user-invocable: bool, argument-hint: ?string}|null */
    private function extractMetadata(string $path): ?array {
        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $frontmatter = FrontMatter::parse($content);
        $data = ($frontmatter->hasFrontMatter() && $frontmatter->error() === null)
            ? $frontmatter->data()
            : [];
        $defaultName = basename(dirname($path));

        return [
            'name' => (string) ($data['name'] ?? $defaultName),
            'description' => (string) ($data['description'] ?? ''),
            'path' => $path,
            'dir' => dirname($path),
            'disable-model-invocation' => (bool) ($data['disable-model-invocation'] ?? false),
            'user-invocable' => (bool) ($data['user-invocable'] ?? true),
            'argument-hint' => isset($data['argument-hint']) ? (string) $data['argument-hint'] : null,
        ];
    }

    private function loadSkillFromFile(string $path): ?Skill {
        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $frontmatter = FrontMatter::parse($content);
        $data = ($frontmatter->hasFrontMatter() && $frontmatter->error() === null)
            ? $frontmatter->data()
            : [];
        $defaultName = basename(dirname($path));
        $dir = dirname($path);
        $body = ($frontmatter->hasFrontMatter() && $frontmatter->error() === null)
            ? trim($frontmatter->document())
            : trim($content);

        return new Skill(
            name: (string) ($data['name'] ?? $defaultName),
            description: (string) ($data['description'] ?? ''),
            body: $body,
            path: $path,
            license: isset($data['license']) ? (string) $data['license'] : null,
            compatibility: isset($data['compatibility']) ? (string) $data['compatibility'] : null,
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            allowedTools: self::parseAllowedTools($data['allowed-tools'] ?? null),
            disableModelInvocation: (bool) ($data['disable-model-invocation'] ?? false),
            userInvocable: (bool) ($data['user-invocable'] ?? true),
            argumentHint: isset($data['argument-hint']) ? (string) $data['argument-hint'] : null,
            model: isset($data['model']) ? (string) $data['model'] : null,
            context: isset($data['context']) ? (string) $data['context'] : null,
            agent: isset($data['agent']) ? (string) $data['agent'] : null,
            resources: $this->findResources($dir),
        );
    }

    /** @return list<string> */
    private static function parseAllowedTools(mixed $value): array {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }
        if (is_array($value)) {
            return array_values(array_map('trim', array_filter($value, 'is_string')));
        }
        if (is_string($value)) {
            // Try comma-delimited first, then space-delimited
            if (str_contains($value, ',')) {
                return array_values(array_filter(array_map('trim', explode(',', $value))));
            }
            return array_values(array_filter(preg_split('/\s+/', $value)));
        }
        return [];
    }

    /** @return list<string> */
    private function findResources(string $skillDir): array {
        $resources = [];
        $resourceFolders = ['scripts', 'references', 'assets', 'examples'];

        foreach ($resourceFolders as $folder) {
            $resources = array_merge(
                $resources,
                $this->listResourceFiles($skillDir . '/' . $folder, $folder . '/')
            );
        }

        return $resources;
    }

    /** @return list<string> */
    private function listResourceFiles(string $dir, string $prefix): array {
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/*');
        if ($files === false) {
            return [];
        }

        $resources = [];
        foreach ($files as $file) {
            if (is_file($file)) {
                $resources[] = $prefix . basename($file);
            }
        }

        return $resources;
    }
}
