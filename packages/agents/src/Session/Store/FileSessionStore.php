<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Store;

use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\AgentSessionInfo;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Session\SessionInfoList;
use Cognesy\Agents\Session\Contracts\CanStoreSessions;
use Cognesy\Agents\Session\Exceptions\InvalidSessionFileException;
use Cognesy\Agents\Session\Exceptions\SessionConflictException;
use Cognesy\Agents\Session\Exceptions\SessionNotFoundException;
use DateTimeImmutable;

final class FileSessionStore implements CanStoreSessions
{
    public function __construct(
        private readonly string $directory,
    ) {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
            throw new \RuntimeException("Failed to create session store directory: {$this->directory}");
        }
    }

    #[\Override]
    public function create(AgentSession $session): AgentSession
    {
        $id = $session->sessionId();
        $sessionId = SessionId::from($id);
        $filePath = $this->filePath($sessionId);
        $lockPath = $this->lockPath($sessionId);

        return $this->withLock($lockPath, function () use ($session, $id, $filePath): AgentSession {
            if (file_exists($filePath)) {
                throw new SessionConflictException("Session already exists: '{$id}'");
            }

            if ($session->version() !== 0) {
                throw new SessionConflictException("Cannot create session '{$id}' with non-zero version {$session->version()}");
            }

            $persisted = AgentSession::reconstitute($session, 1, new DateTimeImmutable());
            $this->atomicWrite($filePath, $persisted);
            return $persisted;
        });
    }

    #[\Override]
    public function save(AgentSession $session): AgentSession
    {
        $id = $session->sessionId();
        $sessionId = SessionId::from($id);
        $filePath = $this->filePath($sessionId);
        $lockPath = $this->lockPath($sessionId);

        return $this->withLock($lockPath, function () use ($session, $id, $filePath): AgentSession {
            if (!file_exists($filePath)) {
                throw new SessionNotFoundException("Session not found: '{$id}'");
            }

            $storedData = $this->readFile($filePath);
            $storedVersion = $storedData['header']['version'] ?? 0;
            if ($storedVersion !== $session->version()) {
                throw new SessionConflictException("Version conflict for session '{$id}'");
            }

            $persisted = AgentSession::reconstitute($session, $storedVersion + 1, new DateTimeImmutable());
            $this->atomicWrite($filePath, $persisted);
            return $persisted;
        });
    }

    #[\Override]
    public function load(SessionId $sessionId): ?AgentSession
    {
        $id = $sessionId->value;
        $filePath = $this->filePath($sessionId);
        if (!file_exists($filePath)) {
            return null;
        }
        $data = $this->readFile($filePath);
        return $this->deserializeSession($filePath, $data);
    }

    #[\Override]
    public function exists(SessionId $sessionId): bool
    {
        return file_exists($this->filePath($sessionId));
    }

    #[\Override]
    public function delete(SessionId $sessionId): void
    {
        $id = $sessionId->value;
        $filePath = $this->filePath($sessionId);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        $lockPath = $this->lockPath($sessionId);
        if (file_exists($lockPath)) {
            unlink($lockPath);
        }
    }

    #[\Override]
    public function listHeaders(): SessionInfoList
    {
        $headers = [];
        $pattern = $this->directory . '/*.json';
        $files = glob($pattern) ?: [];

        foreach ($files as $filePath) {
            $data = $this->readFile($filePath);
            $this->validateSessionData($filePath, $data);
            $headers[] = AgentSessionInfo::fromArray($data['header']);
        }

        return new SessionInfoList(...$headers);
    }

    // INTERNALS ///////////////////////////////////////////////////

    private function filePath(SessionId $sessionId): string {
        return $this->directory . '/' . $sessionId->value . '.json';
    }

    private function lockPath(SessionId $sessionId): string {
        return $this->directory . '/' . $sessionId->value . '.lock';
    }

    private function readFile(string $filePath): array {
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw new InvalidSessionFileException($filePath, 'Failed to read file');
        }

        $data = json_decode($contents, true);
        if (!is_array($data)) {
            throw new InvalidSessionFileException($filePath, 'Invalid JSON');
        }

        return $data;
    }

    private function validateSessionData(string $filePath, array $data): void {
        if (!isset($data['header']) || !is_array($data['header'])) {
            throw new InvalidSessionFileException($filePath, 'Missing or invalid header');
        }
        if (!isset($data['definition']) || !is_array($data['definition'])) {
            throw new InvalidSessionFileException($filePath, 'Missing or invalid definition');
        }
        if (!isset($data['state']) || !is_array($data['state'])) {
            throw new InvalidSessionFileException($filePath, 'Missing or invalid state');
        }
    }

    private function deserializeSession(string $filePath, array $data): AgentSession {
        $this->validateSessionData($filePath, $data);
        try {
            return AgentSession::fromArray($data);
        } catch (\Throwable $e) {
            throw new InvalidSessionFileException($filePath, 'Deserialization failed: ' . $e->getMessage(), $e);
        }
    }

    private function atomicWrite(string $filePath, AgentSession $session): void {
        $json = json_encode($session->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $tmpPath = $filePath . '.tmp.' . getmypid();
        if (file_put_contents($tmpPath, $json) === false) {
            throw new InvalidSessionFileException($filePath, 'Failed to write temp file');
        }
        if (!rename($tmpPath, $filePath)) {
            @unlink($tmpPath);
            throw new InvalidSessionFileException($filePath, 'Failed to rename temp file');
        }
    }

    /** @param callable(): AgentSession $callback */
    private function withLock(string $lockPath, callable $callback): AgentSession {
        $lockHandle = fopen($lockPath, 'c');
        if ($lockHandle === false) {
            throw new InvalidSessionFileException($lockPath, 'Failed to open lock file');
        }

        try {
            if (!flock($lockHandle, LOCK_EX)) {
                throw new InvalidSessionFileException($lockPath, 'Failed to acquire lock');
            }

            return $callback();
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }
}
