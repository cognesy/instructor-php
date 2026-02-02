<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Skills;

use Symfony\Component\Yaml\Yaml;

final class SkillLibrary
{
    private const SKILL_FILENAME = 'SKILL.md';

    private string $skillsPath;

    /** @var array<string, array{name: string, description: string, path: string, dir: string}> */
    private array $metadata = [];

    /** @var array<string, Skill> */
    private array $loadedSkills = [];

    private bool $scanned = false;

    public function __construct(?string $skillsPath = null) {
        $this->skillsPath = $skillsPath ?? (getcwd() ?: '/tmp') . '/skills';
    }

    public static function inDirectory(string $path): self {
        return new self($path);
    }

    /** @return list<array{name: string, description: string}> */
    public function listSkills(): array {
        $this->ensureScanned();

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
        $this->ensureScanned();
        return isset($this->metadata[$name]);
    }

    public function getSkill(string $name): ?Skill {
        $this->ensureScanned();

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
        if (empty($skills)) {
            return "(no skills available)";
        }

        $lines = ["Available skills:"];
        foreach ($skills as $skill) {
            $lines[] = "- [{$skill['name']}]: {$skill['description']}";
        }

        return implode("\n", $lines);
    }

    private function ensureScanned(): void {
        if ($this->scanned) {
            return;
        }

        $this->scanDirectory();
        $this->scanned = true;
    }

    private function scanDirectory(): void {
        if (!is_dir($this->skillsPath)) {
            return;
        }

        $files = $this->findSkillFiles();
        foreach ($files as $file) {
            $meta = $this->extractMetadata($file);
            if ($meta === null) {
                continue;
            }

            $name = $meta['name'];
            if (!isset($this->metadata[$name])) {
                $this->metadata[$name] = $meta;
            }
        }
    }

    /** @return list<string> */
    private function findSkillFiles(): array {
        $files = [];

        $skillFiles = glob($this->skillsPath . '/*/' . self::SKILL_FILENAME);
        if ($skillFiles !== false) {
            $files = array_merge($files, $skillFiles);
        }

        return $files;
    }

    /** @return array{name: string, description: string, path: string, dir: string}|null */
    private function extractMetadata(string $path): ?array {
        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $frontmatter = $this->parseFrontmatter($content) ?? [];
        $defaultName = basename(dirname($path));
        $name = (string) ($frontmatter['name'] ?? $defaultName);
        $description = (string) ($frontmatter['description'] ?? '');
        $dir = dirname($path);

        return [
            'name' => $name,
            'description' => $description,
            'path' => $path,
            'dir' => $dir,
        ];
    }

    private function loadSkillFromFile(string $path): ?Skill {
        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $frontmatter = $this->parseFrontmatter($content);
        $body = $this->extractBody($content);
        $defaultName = basename(dirname($path));
        $name = (string) ($frontmatter['name'] ?? $defaultName);
        $description = (string) ($frontmatter['description'] ?? '');
        $dir = dirname($path);
        $resources = $this->findResources($dir);

        return new Skill(
            name: $name,
            description: $description,
            body: $body,
            path: $path,
            resources: $resources,
        );
    }

    /** @return array<string, mixed>|null */
    private function parseFrontmatter(string $content): ?array {
        if (!str_starts_with($content, '---')) {
            return null;
        }

        $endPos = strpos($content, '---', 3);
        if ($endPos === false) {
            return null;
        }

        $yaml = substr($content, 3, $endPos - 3);

        try {
            $parsed = Yaml::parse(trim($yaml));
            return is_array($parsed) ? $parsed : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractBody(string $content): string {
        if (!str_starts_with($content, '---')) {
            return trim($content);
        }

        $endPos = strpos($content, '---', 3);
        if ($endPos === false) {
            return trim($content);
        }

        return trim(substr($content, $endPos + 3));
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
