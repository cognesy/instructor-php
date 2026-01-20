<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentBuilder\Capabilities\Skills;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class SkillLibrary
{
    private const SKILL_EXTENSION = '.md';
    private const SKILL_FILENAME = 'SKILL.md';

    private string $skillsPath;

    /** @var array<string, array{name: string, description: string, path: string, dir: string, isSkillFile: bool}> */
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
                continue;
            }

            if ($this->shouldOverrideMetadata($this->metadata[$name], $meta)) {
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

        $legacyFiles = glob($this->skillsPath . '/*' . self::SKILL_EXTENSION);
        if ($legacyFiles !== false) {
            $files = array_merge($files, $legacyFiles);
        }

        return $files;
    }

    /** @param array{name: string, description: string, path: string, dir: string, isSkillFile: bool} $existing
     *  @param array{name: string, description: string, path: string, dir: string, isSkillFile: bool} $incoming
     */
    private function shouldOverrideMetadata(array $existing, array $incoming): bool {
        if ($incoming['isSkillFile'] && !$existing['isSkillFile']) {
            return true;
        }
        return false;
    }

    /** @return array{name: string, description: string, path: string, dir: string, isSkillFile: bool}|null */
    private function extractMetadata(string $path): ?array {
        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $frontmatter = $this->parseFrontmatter($content) ?? [];
        $isSkillFile = basename($path) === self::SKILL_FILENAME;
        $defaultName = $isSkillFile
            ? basename(dirname($path))
            : pathinfo($path, PATHINFO_FILENAME);
        $name = $frontmatter['name'] ?? $defaultName;
        $description = $frontmatter['description'] ?? '';
        $dir = $isSkillFile ? dirname($path) : ($this->skillsPath . '/' . $name);

        return [
            'name' => $name,
            'description' => $description,
            'path' => $path,
            'dir' => $dir,
            'isSkillFile' => $isSkillFile,
        ];
    }

    private function loadSkillFromFile(string $path): ?Skill {
        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $frontmatter = $this->parseFrontmatter($content);
        $body = $this->extractBody($content);
        $isSkillFile = basename($path) === self::SKILL_FILENAME;
        $defaultName = $isSkillFile
            ? basename(dirname($path))
            : pathinfo($path, PATHINFO_FILENAME);
        $name = $frontmatter['name'] ?? $defaultName;
        $description = $frontmatter['description'] ?? '';
        $dir = $isSkillFile ? dirname($path) : ($this->skillsPath . '/' . $name);
        $resources = $this->findResources($dir, $name, !$isSkillFile);

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
    private function findResources(string $skillDir, string $skillName, bool $includeLegacy = false): array {
        $resources = [];
        $resourceFolders = ['scripts', 'references', 'assets'];

        foreach ($resourceFolders as $folder) {
            $resources = array_merge(
                $resources,
                $this->listResourceFiles($skillDir . '/' . $folder, $folder . '/')
            );
        }

        if ($includeLegacy) {
            $legacyDir = $this->skillsPath . '/resources/' . $skillName;
            $resources = array_merge(
                $resources,
                $this->listResourceFiles($legacyDir, 'resources/')
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
