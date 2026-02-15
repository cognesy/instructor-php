<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Storage;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\Contracts\CanStoreMessages;
use Cognesy\Messages\MessageStore\Data\StoreMessagesResult;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Section;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;
use RuntimeException;

/**
 * JSONL file-based storage (Pi-Mono style).
 *
 * Append-only format where each line is a JSON entry.
 * Supports session trees via parentId chains in messages.
 *
 * File format:
 * - Line 1: Session header {"type":"session","id":"...","createdAt":"..."}
 * - Lines 2+: Entries {"type":"message"|"label","id":"...","parentId":"...","data":{...}}
 */
class JsonlStorage implements CanStoreMessages
{
    private const VERSION = 1;

    /** @var array<string, array{file: string, leafId: ?string, index: array<string, array>, labels: array<string, string>}> */
    private array $sessions = [];

    public function __construct(
        private string $basePath,
    ) {
        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }
    }

    // SESSION OPERATIONS ////////////////////////////////////

    #[\Override]
    public function createSession(?string $sessionId = null): string {
        $id = $sessionId ?? Uuid::uuid4();
        $file = $this->sessionFile($id);

        // Write session header
        $header = [
            'type' => 'session',
            'version' => self::VERSION,
            'id' => $id,
            'createdAt' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
        ];
        file_put_contents($file, json_encode($header) . "\n");

        $this->sessions[$id] = [
            'file' => $file,
            'leafId' => null,
            'index' => [],
            'labels' => [],
        ];

        return $id;
    }

    #[\Override]
    public function hasSession(string $sessionId): bool {
        return file_exists($this->sessionFile($sessionId));
    }

    #[\Override]
    public function load(string $sessionId): MessageStore {
        $this->ensureLoaded($sessionId);

        // Group messages by section
        $sectionMessages = [];
        foreach ($this->sessions[$sessionId]['index'] as $entry) {
            if ($entry['type'] !== 'message') {
                continue;
            }

            $sectionName = $entry['section'] ?? 'messages';
            if (!isset($sectionMessages[$sectionName])) {
                $sectionMessages[$sectionName] = [];
            }
            $sectionMessages[$sectionName][] = Message::fromArray($entry['data']);
        }

        // Build sections
        $sections = [];
        foreach ($sectionMessages as $sectionName => $messages) {
            $sections[] = new Section($sectionName, new Messages(...$messages));
        }

        return MessageStore::fromSections(...$sections);
    }

    #[\Override]
    public function save(string $sessionId, MessageStore $store): StoreMessagesResult {
        $startedAt = new DateTimeImmutable();

        try {
            // For JSONL, we rebuild the file from scratch
            // In production, you might want incremental appends
            $file = $this->sessionFile($sessionId);

            // Track existing messages for newMessages count
            $existingIds = isset($this->sessions[$sessionId])
                ? array_keys($this->sessions[$sessionId]['index'])
                : [];

            // Write header
            $header = [
                'type' => 'session',
                'version' => self::VERSION,
                'id' => $sessionId,
                'createdAt' => $startedAt->format(DateTimeImmutable::ATOM),
            ];
            file_put_contents($file, json_encode($header) . "\n");

            // Write all messages
            $leafId = null;
            $messagesStored = 0;
            $sectionsStored = 0;
            $newMessages = 0;

            foreach ($store->sections()->all() as $section) {
                $sectionsStored++;
                foreach ($section->messages()->all() as $message) {
                    $entry = [
                        'type' => 'message',
                        'id' => $message->id,
                        'parentId' => $message->parentId(),
                        'section' => $section->name(),
                        'timestamp' => $message->createdAt->format(DateTimeImmutable::ATOM),
                        'data' => $message->toArray(),
                    ];
                    file_put_contents($file, json_encode($entry) . "\n", FILE_APPEND);
                    $leafId = $message->id;
                    $messagesStored++;

                    if (!in_array($message->id, $existingIds, true)) {
                        $newMessages++;
                    }
                }
            }

            // Write labels
            if (isset($this->sessions[$sessionId])) {
                foreach ($this->sessions[$sessionId]['labels'] as $messageId => $label) {
                    $entry = [
                        'type' => 'label',
                        'id' => Uuid::uuid4(),
                        'parentId' => $leafId,
                        'targetId' => $messageId,
                        'label' => $label,
                        'timestamp' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
                    ];
                    file_put_contents($file, json_encode($entry) . "\n", FILE_APPEND);
                }
            }

            // Reload index
            $this->loadSession($sessionId);

            return StoreMessagesResult::success(
                sessionId: $sessionId,
                startedAt: $startedAt,
                finishedAt: new DateTimeImmutable(),
                sectionsStored: $sectionsStored,
                messagesStored: $messagesStored,
                newMessages: $newMessages,
            );
        } catch (\Throwable $e) {
            return StoreMessagesResult::failure(
                sessionId: $sessionId,
                startedAt: $startedAt,
                errorMessage: $e->getMessage(),
            );
        }
    }

    #[\Override]
    public function delete(string $sessionId): void {
        $file = $this->sessionFile($sessionId);
        if (file_exists($file)) {
            unlink($file);
        }
        unset($this->sessions[$sessionId]);
    }

    // MESSAGE OPERATIONS ////////////////////////////////////

    #[\Override]
    public function append(string $sessionId, string $section, Message $message): Message {
        $this->ensureLoaded($sessionId);

        // Set parentId to current leaf if not set
        $leafId = $this->sessions[$sessionId]['leafId'];
        if ($leafId !== null && $message->parentId() === null) {
            $message = $message->withParentId($leafId);
        }

        // Create entry
        $entry = [
            'type' => 'message',
            'id' => $message->id,
            'parentId' => $message->parentId(),
            'section' => $section,
            'timestamp' => $message->createdAt->format(DateTimeImmutable::ATOM),
            'data' => $message->toArray(),
        ];

        // Append to file
        $file = $this->sessions[$sessionId]['file'];
        file_put_contents($file, json_encode($entry) . "\n", FILE_APPEND);

        // Update index
        $this->sessions[$sessionId]['index'][$message->id] = $entry;
        $this->sessions[$sessionId]['leafId'] = $message->id;

        return $message;
    }

    #[\Override]
    public function get(string $sessionId, string $messageId): ?Message {
        $this->ensureLoaded($sessionId);

        $entry = $this->sessions[$sessionId]['index'][$messageId] ?? null;
        if ($entry === null || $entry['type'] !== 'message') {
            return null;
        }

        return Message::fromArray($entry['data']);
    }

    #[\Override]
    public function getSection(string $sessionId, string $section, ?int $limit = null): Messages {
        $this->ensureLoaded($sessionId);

        $messages = [];
        foreach ($this->sessions[$sessionId]['index'] as $entry) {
            if ($entry['type'] !== 'message') {
                continue;
            }
            if (($entry['section'] ?? 'messages') !== $section) {
                continue;
            }
            $messages[] = Message::fromArray($entry['data']);
        }

        if ($limit !== null) {
            $messages = array_slice($messages, -$limit);
        }

        return new Messages(...$messages);
    }

    // BRANCHING OPERATIONS //////////////////////////////////

    #[\Override]
    public function getLeafId(string $sessionId): ?string {
        $this->ensureLoaded($sessionId);
        return $this->sessions[$sessionId]['leafId'];
    }

    #[\Override]
    public function navigateTo(string $sessionId, string $messageId): void {
        $this->ensureLoaded($sessionId);

        if (!isset($this->sessions[$sessionId]['index'][$messageId])) {
            throw new RuntimeException("Message not found: {$messageId}");
        }

        $this->sessions[$sessionId]['leafId'] = $messageId;
    }

    #[\Override]
    public function getPath(string $sessionId, ?string $toMessageId = null): Messages {
        $this->ensureLoaded($sessionId);

        $targetId = $toMessageId ?? $this->sessions[$sessionId]['leafId'];
        if ($targetId === null) {
            return Messages::empty();
        }

        // Walk up the parentId chain
        $path = [];
        $currentId = $targetId;

        while ($currentId !== null) {
            $entry = $this->sessions[$sessionId]['index'][$currentId] ?? null;
            if ($entry === null || $entry['type'] !== 'message') {
                break;
            }
            array_unshift($path, Message::fromArray($entry['data']));
            $currentId = $entry['parentId'];
        }

        return new Messages(...$path);
    }

    #[\Override]
    public function fork(string $sessionId, string $fromMessageId): string {
        $this->ensureLoaded($sessionId);

        // Get path to fork point
        $path = $this->getPath($sessionId, $fromMessageId);

        // Create new session
        $newSessionId = $this->createSession();

        // Copy messages
        foreach ($path->all() as $message) {
            $this->append($newSessionId, 'messages', $message);
        }

        return $newSessionId;
    }

    // LABELS (CHECKPOINTS) //////////////////////////////////

    #[\Override]
    public function addLabel(string $sessionId, string $messageId, string $label): void {
        $this->ensureLoaded($sessionId);

        if (!isset($this->sessions[$sessionId]['index'][$messageId])) {
            throw new RuntimeException("Message not found: {$messageId}");
        }

        $this->sessions[$sessionId]['labels'][$messageId] = $label;

        // Append label entry to file
        $entry = [
            'type' => 'label',
            'id' => Uuid::uuid4(),
            'parentId' => $this->sessions[$sessionId]['leafId'],
            'targetId' => $messageId,
            'label' => $label,
            'timestamp' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
        ];
        file_put_contents($this->sessions[$sessionId]['file'], json_encode($entry) . "\n", FILE_APPEND);
    }

    #[\Override]
    public function getLabels(string $sessionId): array {
        $this->ensureLoaded($sessionId);
        return $this->sessions[$sessionId]['labels'];
    }

    // HELPERS ///////////////////////////////////////////////

    private function sessionFile(string $sessionId): string {
        // Sanitize session ID for filename
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sessionId);
        return "{$this->basePath}/{$safe}.jsonl";
    }

    private function ensureLoaded(string $sessionId): void {
        if (isset($this->sessions[$sessionId])) {
            return;
        }

        $this->loadSession($sessionId);
    }

    private function loadSession(string $sessionId): void {
        $file = $this->sessionFile($sessionId);

        if (!file_exists($file)) {
            throw new RuntimeException("Session not found: {$sessionId}");
        }

        $this->sessions[$sessionId] = [
            'file' => $file,
            'leafId' => null,
            'index' => [],
            'labels' => [],
        ];

        // Parse JSONL file
        $handle = fopen($file, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot open session file: {$file}");
        }

        $lineNum = 0;
        while (($line = fgets($handle)) !== false) {
            $lineNum++;
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $entry = json_decode($line, true);
            if ($entry === null) {
                continue;
            }

            // Skip session header
            if ($entry['type'] === 'session') {
                continue;
            }

            // Index messages
            if ($entry['type'] === 'message') {
                $this->sessions[$sessionId]['index'][$entry['id']] = $entry;
                $this->sessions[$sessionId]['leafId'] = $entry['id'];
            }

            // Track labels
            if ($entry['type'] === 'label') {
                $targetId = $entry['targetId'] ?? null;
                $label = $entry['label'] ?? null;
                if ($targetId !== null && $label !== null) {
                    $this->sessions[$sessionId]['labels'][$targetId] = $label;
                } elseif ($targetId !== null && $label === null) {
                    // Label removal
                    unset($this->sessions[$sessionId]['labels'][$targetId]);
                }
            }
        }

        fclose($handle);
    }
}
