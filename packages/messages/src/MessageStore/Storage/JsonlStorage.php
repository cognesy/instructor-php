<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Storage;

use Cognesy\Messages\Message;
use Cognesy\Messages\MessageId;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageSessionId;
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

    /** @var array<string, array{file: string, leafId: ?MessageId, index: array<string, array>, labels: array<string, string>}> */
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
    public function createSession(?MessageSessionId $sessionId = null): MessageSessionId {
        $id = $sessionId ?? MessageSessionId::generate();
        $sessionKey = $id->toString();
        $file = $this->sessionFile($id);

        // Write session header
        $header = [
            'type' => 'session',
            'version' => self::VERSION,
            'id' => $sessionKey,
            'createdAt' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
        ];
        file_put_contents($file, json_encode($header) . "\n");

        $this->sessions[$sessionKey] = [
            'file' => $file,
            'leafId' => null,
            'index' => [],
            'labels' => [],
        ];

        return $id;
    }

    #[\Override]
    public function hasSession(MessageSessionId $sessionId): bool {
        return file_exists($this->sessionFile($sessionId));
    }

    #[\Override]
    public function load(MessageSessionId $sessionId): MessageStore {
        $this->ensureLoaded($sessionId);
        $sessionKey = $sessionId->toString();

        // Group messages by section
        $sectionMessages = [];
        foreach ($this->sessions[$sessionKey]['index'] as $entry) {
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
    public function save(MessageSessionId $sessionId, MessageStore $store): StoreMessagesResult {
        $startedAt = new DateTimeImmutable();
        $sessionKey = $sessionId->toString();

        try {
            // For JSONL, we rebuild the file from scratch
            // In production, you might want incremental appends
            $file = $this->sessionFile($sessionId);

            // Track existing messages for newMessages count
            $existingIds = isset($this->sessions[$sessionKey])
                ? array_keys($this->sessions[$sessionKey]['index'])
                : [];

            // Write header
            $header = [
                'type' => 'session',
                'version' => self::VERSION,
                'id' => $sessionKey,
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
                    $messageId = $message->id()->toString();
                    $entry = [
                        'type' => 'message',
                        'id' => $messageId,
                        'parentId' => $message->parentId()?->toString(),
                        'section' => $section->name(),
                        'timestamp' => $message->createdAt->format(DateTimeImmutable::ATOM),
                        'data' => $message->toArray(),
                    ];
                    file_put_contents($file, json_encode($entry) . "\n", FILE_APPEND);
                    $leafId = $messageId;
                    $messagesStored++;

                    if (!in_array($messageId, $existingIds, true)) {
                        $newMessages++;
                    }
                }
            }

            // Write labels
            if (isset($this->sessions[$sessionKey])) {
                foreach ($this->sessions[$sessionKey]['labels'] as $messageId => $label) {
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
    public function delete(MessageSessionId $sessionId): void {
        $file = $this->sessionFile($sessionId);
        if (file_exists($file)) {
            unlink($file);
        }
        unset($this->sessions[$sessionId->toString()]);
    }

    // MESSAGE OPERATIONS ////////////////////////////////////

    #[\Override]
    public function append(MessageSessionId $sessionId, string $section, Message $message): Message {
        $this->ensureLoaded($sessionId);
        $sessionKey = $sessionId->toString();

        // Set parentId to current leaf if not set
        $leafId = $this->sessions[$sessionKey]['leafId'];
        if ($leafId !== null && $message->parentId() === null) {
            $message = $message->withParentId($leafId);
        }

        // Create entry
        $messageId = $message->id()->toString();
        $entry = [
            'type' => 'message',
            'id' => $messageId,
            'parentId' => $message->parentId()?->toString(),
            'section' => $section,
            'timestamp' => $message->createdAt->format(DateTimeImmutable::ATOM),
            'data' => $message->toArray(),
        ];

        // Append to file
        $file = $this->sessions[$sessionKey]['file'];
        file_put_contents($file, json_encode($entry) . "\n", FILE_APPEND);

        // Update index
        $this->sessions[$sessionKey]['index'][$messageId] = $entry;
        $this->sessions[$sessionKey]['leafId'] = $message->id();

        return $message;
    }

    #[\Override]
    public function get(MessageSessionId $sessionId, MessageId $messageId): ?Message {
        $this->ensureLoaded($sessionId);
        $sessionKey = $sessionId->toString();
        $entry = $this->sessions[$sessionKey]['index'][$messageId->toString()] ?? null;
        if ($entry === null || $entry['type'] !== 'message') {
            return null;
        }

        return Message::fromArray($entry['data']);
    }

    #[\Override]
    public function getSection(MessageSessionId $sessionId, string $section, ?int $limit = null): Messages {
        $this->ensureLoaded($sessionId);
        $sessionKey = $sessionId->toString();

        $messages = [];
        foreach ($this->sessions[$sessionKey]['index'] as $entry) {
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
    public function getLeafId(MessageSessionId $sessionId): ?MessageId {
        $this->ensureLoaded($sessionId);
        return $this->sessions[$sessionId->toString()]['leafId'];
    }

    #[\Override]
    public function navigateTo(MessageSessionId $sessionId, MessageId $messageId): void {
        $this->ensureLoaded($sessionId);
        $sessionKey = $sessionId->toString();

        $key = $messageId->toString();
        if (!isset($this->sessions[$sessionKey]['index'][$key])) {
            throw new RuntimeException("Message not found: {$messageId}");
        }

        $this->sessions[$sessionKey]['leafId'] = $messageId;
    }

    #[\Override]
    public function getPath(MessageSessionId $sessionId, ?MessageId $toMessageId = null): Messages {
        $this->ensureLoaded($sessionId);
        $sessionKey = $sessionId->toString();

        $targetId = $toMessageId ?? $this->sessions[$sessionKey]['leafId'];
        if ($targetId === null) {
            return Messages::empty();
        }

        // Walk up the parentId chain
        $path = [];
        $currentId = $targetId;

        while ($currentId !== null) {
            $entry = $this->sessions[$sessionKey]['index'][$currentId->toString()] ?? null;
            if ($entry === null || $entry['type'] !== 'message') {
                break;
            }
            array_unshift($path, Message::fromArray($entry['data']));
            $currentId = isset($entry['parentId']) ? new MessageId($entry['parentId']) : null;
        }

        return new Messages(...$path);
    }

    #[\Override]
    public function fork(MessageSessionId $sessionId, MessageId $fromMessageId): MessageSessionId {
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
    public function addLabel(MessageSessionId $sessionId, MessageId $messageId, string $label): void {
        $this->ensureLoaded($sessionId);
        $sessionKey = $sessionId->toString();

        $messageIdString = $messageId->toString();
        if (!isset($this->sessions[$sessionKey]['index'][$messageIdString])) {
            throw new RuntimeException("Message not found: {$messageId}");
        }

        $this->sessions[$sessionKey]['labels'][$messageIdString] = $label;

        // Append label entry to file
        $entry = [
            'type' => 'label',
            'id' => Uuid::uuid4(),
            'parentId' => $this->sessions[$sessionKey]['leafId']?->toString(),
            'targetId' => $messageIdString,
            'label' => $label,
            'timestamp' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
        ];
        file_put_contents($this->sessions[$sessionKey]['file'], json_encode($entry) . "\n", FILE_APPEND);
    }

    #[\Override]
    public function getLabels(MessageSessionId $sessionId): array {
        $this->ensureLoaded($sessionId);
        return $this->sessions[$sessionId->toString()]['labels'];
    }

    // HELPERS ///////////////////////////////////////////////

    private function sessionFile(MessageSessionId $sessionId): string {
        // Sanitize session ID for filename
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sessionId->toString());
        return "{$this->basePath}/{$safe}.jsonl";
    }

    private function ensureLoaded(MessageSessionId $sessionId): void {
        if (isset($this->sessions[$sessionId->toString()])) {
            return;
        }

        $this->loadSession($sessionId);
    }

    private function loadSession(MessageSessionId $sessionId): void {
        $file = $this->sessionFile($sessionId);
        $sessionKey = $sessionId->toString();

        if (!file_exists($file)) {
            throw new RuntimeException("Session not found: {$sessionId}");
        }

        $this->sessions[$sessionKey] = [
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
                $this->sessions[$sessionKey]['index'][$entry['id']] = $entry;
                $this->sessions[$sessionKey]['leafId'] = new MessageId($entry['id']);
            }

            // Track labels
            if ($entry['type'] === 'label') {
                $targetId = $entry['targetId'] ?? null;
                $label = $entry['label'] ?? null;
                if ($targetId !== null && $label !== null) {
                    $this->sessions[$sessionKey]['labels'][$targetId] = $label;
                } elseif ($targetId !== null && $label === null) {
                    // Label removal
                    unset($this->sessions[$sessionKey]['labels'][$targetId]);
                }
            }
        }

        fclose($handle);
    }
}
