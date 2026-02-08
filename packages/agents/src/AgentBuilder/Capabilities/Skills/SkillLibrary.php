<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Skills;

use Cognesy\Agents\AgentTemplate\Definitions\MarkdownFrontmatter;

final class SkillLibrary
{
    private const SKILL_FILENAME = 'SKILL.md';

    /** @var array<string, array{name: string, description: string, path: string, dir: string}> */
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

    /** @return list<array{name: string, description: string}> */
    public function listSkills(): array {
        $list = [];
        foreach ($this->metadata as $meta) {
            $list[] = [
                'name' => $meta['name'],
                'description' => $meta['description'],
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

    public function renderSkillList(): string {
        $skills = $this->listSkills();
        if ($skills === []) {
            return "(no skills available)";
        }

        $lines = ["Available skills:"];
        foreach ($skills as $skill) {
            $lines[] = "- [{$skill['name']}]: {$skill['description']}";
        }

        return implode("\n", $lines);
    }

    /** @return array<string, array{name: string, description: string, path: string, dir: string}> */
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

    /** @return array{name: string, description: string, path: string, dir: string}|null */
    private function extractMetadata(string $path): ?array {
        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $frontmatter = MarkdownFrontmatter::parse($content);
        $defaultName = basename(dirname($path));

        return [
            'name' => (string) ($frontmatter?->data['name'] ?? $defaultName),
            'description' => (string) ($frontmatter?->data['description'] ?? ''),
            'path' => $path,
            'dir' => dirname($path),
        ];
    }

    private function loadSkillFromFile(string $path): ?Skill {
        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $frontmatter = MarkdownFrontmatter::parse($content);
        $defaultName = basename(dirname($path));
        $dir = dirname($path);

        return new Skill(
            name: (string) ($frontmatter?->data['name'] ?? $defaultName),
            description: (string) ($frontmatter?->data['description'] ?? ''),
            body: $frontmatter?->body ?? trim($content),
            path: $path,
            resources: $this->findResources($dir),
        );
    }

    /** @return list<string> */
    private function findResources(string $skillDir): array {
        $resources = [];
        $resourceFolders = ['scripts', 'references', 'assets'];

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
