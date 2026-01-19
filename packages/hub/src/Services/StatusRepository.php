<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Services;

use Cognesy\Config\BasePath;
use Cognesy\InstructorHub\Contracts\CanPersistStatus;
use Cognesy\InstructorHub\Exceptions\InvalidStatusFileException;
use Symfony\Component\Filesystem\Filesystem;

class StatusRepository implements CanPersistStatus
{
    private const STATUS_FILE_VERSION = '1.0';

    private string $statusFilePath;
    private Filesystem $filesystem;

    public function __construct(?string $statusFilePath = null)
    {
        $this->statusFilePath = $statusFilePath ?? BasePath::get('.instructor-hub/status.json');
        $this->filesystem = new Filesystem();
        $this->ensureDirectoryExists();
    }

    #[\Override]
    public function save(array $statusData): void
    {
        $statusData['metadata']['version'] = self::STATUS_FILE_VERSION;
        $statusData['metadata']['lastUpdated'] = (new \DateTimeImmutable())->format('c');

        $json = json_encode(
            $statusData,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($json === false) {
            throw new InvalidStatusFileException('Failed to encode status data to JSON: ' . json_last_error_msg());
        }

        $this->filesystem->dumpFile($this->statusFilePath, $json);
    }

    #[\Override]
    public function load(): array
    {
        if (!$this->exists()) {
            return $this->getEmptyStructure();
        }

        $content = file_get_contents($this->statusFilePath);
        if ($content === false) {
            throw new InvalidStatusFileException("Failed to read status file: {$this->statusFilePath}");
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidStatusFileException('Invalid JSON in status file: ' . json_last_error_msg());
        }

        return $this->validateAndMigrate($data);
    }

    #[\Override]
    public function exists(): bool
    {
        return file_exists($this->statusFilePath);
    }

    #[\Override]
    public function clear(): void
    {
        if ($this->exists()) {
            $this->filesystem->remove($this->statusFilePath);
        }
    }

    #[\Override]
    public function backup(): string
    {
        if (!$this->exists()) {
            throw new InvalidStatusFileException('Cannot backup non-existent status file');
        }

        $backupPath = $this->statusFilePath . '.backup.' . date('Y-m-d-H-i-s');
        $this->filesystem->copy($this->statusFilePath, $backupPath);

        return $backupPath;
    }

    #[\Override]
    public function getLastModified(): ?\DateTimeImmutable
    {
        if (!$this->exists()) {
            return null;
        }

        $timestamp = filemtime($this->statusFilePath);
        return $timestamp !== false ? new \DateTimeImmutable('@' . $timestamp) : null;
    }

    #[\Override]
    public function getFilePath(): string
    {
        return $this->statusFilePath;
    }

    private function ensureDirectoryExists(): void
    {
        $dir = dirname($this->statusFilePath);
        if (!$this->filesystem->exists($dir)) {
            $this->filesystem->mkdir($dir, 0755);
        }
    }

    private function getEmptyStructure(): array
    {
        return [
            'metadata' => [
                'version' => self::STATUS_FILE_VERSION,
                'lastUpdated' => (new \DateTimeImmutable())->format('c'),
                'totalExamples' => 0,
            ],
            'examples' => [],
            'statistics' => [
                'totalExecuted' => 0,
                'completed' => 0,
                'errors' => 0,
                'skipped' => 0,
                'interrupted' => 0,
                'averageExecutionTime' => 0.0,
                'totalExecutionTime' => 0.0,
                'lastFullRun' => null,
                'lastPartialRun' => null,
                'slowestExample' => null,
                'fastestExample' => null,
            ],
        ];
    }

    private function validateAndMigrate(array $data): array
    {
        // Ensure required structure exists
        $structure = $this->getEmptyStructure();

        $data = array_merge($structure, $data);
        $data['metadata'] = array_merge($structure['metadata'], $data['metadata'] ?? []);
        $data['statistics'] = array_merge($structure['statistics'], $data['statistics'] ?? []);

        // Validate version and migrate if needed
        $version = $data['metadata']['version'] ?? '0.0';
        if (version_compare($version, self::STATUS_FILE_VERSION, '<')) {
            $data = $this->migrateData($data, $version);
        }

        return $data;
    }

    private function migrateData(array $data, string $fromVersion): array
    {
        // Future migration logic will go here
        // For now, just update the version
        $data['metadata']['version'] = self::STATUS_FILE_VERSION;
        return $data;
    }
}
