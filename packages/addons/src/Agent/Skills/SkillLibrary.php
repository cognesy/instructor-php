<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Skills;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class SkillLibrary
{
    private const SKILL_EXTENSION = '.md';

    private string $skillsPath;

    /** @var array<string, array{name: string, description: string, path: string}> */
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

        $files = glob($this->skillsPath . '/*' . self::SKILL_EXTENSION);
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $meta = $this->extractMetadata($file);
            if ($meta !== null) {
                $this->metadata[$meta['name']] = $meta;
            }
        }
    }

    /** @return array{name: string, description: string, path: string}|null */
    private function extractMetadata(string $path): ?array {
        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $frontmatter = $this->parseFrontmatter($content) ?? [];
        $name = $frontmatter['name'] ?? pathinfo($path, PATHINFO_FILENAME);
        $description = $frontmatter['description'] ?? '';

        return [
            'name' => $name,
            'description' => $description,
            'path' => $path,
        ];
    }

    private function loadSkillFromFile(string $path): ?Skill {
        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $frontmatter = $this->parseFrontmatter($content);
        $body = $this->extractBody($content);
        $resources = $this->findResources($path);

        $name = $frontmatter['name'] ?? pathinfo($path, PATHINFO_FILENAME);
        $description = $frontmatter['description'] ?? '';

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
    private function findResources(string $skillPath): array {
        $skillDir = dirname($skillPath);
        $skillName = pathinfo($skillPath, PATHINFO_FILENAME);

        $resourceDirs = [
            $skillDir . '/' . $skillName,
            $skillDir . '/resources/' . $skillName,
        ];

        $resources = [];
        foreach ($resourceDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $files = glob($dir . '/*');
            if ($files === false) {
                continue;
            }

            foreach ($files as $file) {
                if (is_file($file)) {
                    $resources[] = basename($file);
                }
            }
        }

        return $resources;
    }
}
