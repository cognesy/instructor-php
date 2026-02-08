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
 * In-memory implementation of message storage.
 *
 * Stores all data in arrays - no persistence between requests.
 * Useful for testing and single-request agent executions.
 */
class InMemoryStorage implements CanStoreMessages
{
    /** @var array<string, array{messages: array<string, Message>, sections: array<string, string[]>, leafId: ?string, labels: array<string, string>}> */
    private array $sessions = [];

    // SESSION OPERATIONS ////////////////////////////////////

    public function createSession(?string $sessionId = null): string {
        $id = $sessionId ?? Uuid::uuid4();
        $this->sessions[$id] = [
            'messages' => [],
            'sections' => [],
            'leafId' => null,
            'labels' => [],
        ];
        return $id;
    }

    public function hasSession(string $sessionId): bool {
        return isset($this->sessions[$sessionId]);
    }

    public function load(string $sessionId): MessageStore {
        $this->ensureSession($sessionId);

        $sections = [];
        foreach ($this->sessions[$sessionId]['sections'] as $sectionName => $messageIds) {
            $messages = [];
            foreach ($messageIds as $msgId) {
                if (isset($this->sessions[$sessionId]['messages'][$msgId])) {
                    $messages[] = $this->sessions[$sessionId]['messages'][$msgId];
                }
            }
            $sections[] = new Section($sectionName, new Messages(...$messages));
        }
        return MessageStore::fromSections(...$sections);
    }

    public function save(string $sessionId, MessageStore $store): StoreMessagesResult {
        $startedAt = new DateTimeImmutable();

        try {
            $this->ensureSession($sessionId);

            // Track existing messages for newMessages count
            $existingIds = array_keys($this->sessions[$sessionId]['messages']);

            // Clear existing and rebuild from store
            $this->sessions[$sessionId]['messages'] = [];
            $this->sessions[$sessionId]['sections'] = [];

            $messagesStored = 0;
            $sectionsStored = 0;
            $newMessages = 0;

            foreach ($store->sections()->all() as $section) {
                $sectionName = $section->name();
                $this->sessions[$sessionId]['sections'][$sectionName] = [];
                $sectionsStored++;

                foreach ($section->messages()->all() as $message) {
                    $this->sessions[$sessionId]['messages'][$message->id] = $message;
                    $this->sessions[$sessionId]['sections'][$sectionName][] = $message->id;
                    $messagesStored++;

                    if (!in_array($message->id, $existingIds, true)) {
                        $newMessages++;
                    }

                    // Update leaf to last message
                    $this->sessions[$sessionId]['leafId'] = $message->id;
                }
            }

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

    public function delete(string $sessionId): void {
        unset($this->sessions[$sessionId]);
    }

    // MESSAGE OPERATIONS ////////////////////////////////////

    public function append(string $sessionId, string $section, Message $message): Message {
        $this->ensureSession($sessionId);

        // Set parentId to current leaf
        $leafId = $this->sessions[$sessionId]['leafId'];
        if ($leafId !== null && $message->parentId() === null) {
            $message = $message->withParentId($leafId);
        }

        // Store message
        $this->sessions[$sessionId]['messages'][$message->id] = $message;

        // Add to section
        if (!isset($this->sessions[$sessionId]['sections'][$section])) {
            $this->sessions[$sessionId]['sections'][$section] = [];
        }
        $this->sessions[$sessionId]['sections'][$section][] = $message->id;

        // Update leaf
        $this->sessions[$sessionId]['leafId'] = $message->id;

        return $message;
    }

    public function get(string $sessionId, string $messageId): ?Message {
        $this->ensureSession($sessionId);
        return $this->sessions[$sessionId]['messages'][$messageId] ?? null;
    }

    public function getSection(string $sessionId, string $section, ?int $limit = null): Messages {
        $this->ensureSession($sessionId);

        $messageIds = $this->sessions[$sessionId]['sections'][$section] ?? [];
        if ($limit !== null) {
            $messageIds = array_slice($messageIds, -$limit);
        }

        $messages = [];
        foreach ($messageIds as $msgId) {
            if (isset($this->sessions[$sessionId]['messages'][$msgId])) {
                $messages[] = $this->sessions[$sessionId]['messages'][$msgId];
            }
        }

        return new Messages(...$messages);
    }

    // BRANCHING OPERATIONS //////////////////////////////////

    public function getLeafId(string $sessionId): ?string {
        $this->ensureSession($sessionId);
        return $this->sessions[$sessionId]['leafId'];
    }

    public function navigateTo(string $sessionId, string $messageId): void {
        $this->ensureSession($sessionId);

        if (!isset($this->sessions[$sessionId]['messages'][$messageId])) {
            throw new RuntimeException("Message not found: {$messageId}");
        }

        $this->sessions[$sessionId]['leafId'] = $messageId;
    }

    public function getPath(string $sessionId, ?string $toMessageId = null): Messages {
        $this->ensureSession($sessionId);

        $targetId = $toMessageId ?? $this->sessions[$sessionId]['leafId'];
        if ($targetId === null) {
            return Messages::empty();
        }

        // Walk up the parentId chain
        $path = [];
        $currentId = $targetId;

        while ($currentId !== null) {
            $message = $this->sessions[$sessionId]['messages'][$currentId] ?? null;
            if ($message === null) {
                break;
            }
            array_unshift($path, $message); // Prepend to get root-first order
            $currentId = $message->parentId();
        }

        return new Messages(...$path);
    }

    public function fork(string $sessionId, string $fromMessageId): string {
        $this->ensureSession($sessionId);

        // Get path to the fork point
        $path = $this->getPath($sessionId, $fromMessageId);

        // Create new session
        $newSessionId = $this->createSession();

        // Copy messages up to fork point
        $section = 'messages'; // Default section for forked messages
        foreach ($path->all() as $message) {
            $this->sessions[$newSessionId]['messages'][$message->id] = $message;
            $this->sessions[$newSessionId]['sections'][$section][] = $message->id;
        }

        // Set leaf to fork point
        $this->sessions[$newSessionId]['leafId'] = $fromMessageId;

        return $newSessionId;
    }

    // LABELS (CHECKPOINTS) //////////////////////////////////

    public function addLabel(string $sessionId, string $messageId, string $label): void {
        $this->ensureSession($sessionId);

        if (!isset($this->sessions[$sessionId]['messages'][$messageId])) {
            throw new RuntimeException("Message not found: {$messageId}");
        }

        $this->sessions[$sessionId]['labels'][$messageId] = $label;
    }

    public function getLabels(string $sessionId): array {
        $this->ensureSession($sessionId);
        return $this->sessions[$sessionId]['labels'];
    }

    // HELPERS ///////////////////////////////////////////////

    private function ensureSession(string $sessionId): void {
        if (!isset($this->sessions[$sessionId])) {
            throw new RuntimeException("Session not found: {$sessionId}");
        }
    }
}
