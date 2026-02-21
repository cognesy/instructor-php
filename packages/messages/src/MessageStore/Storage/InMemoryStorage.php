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
    /** @var array<string, array{messages: array<string, Message>, sections: array<string, string[]>, leafId: ?MessageId, labels: array<string, string>}> */
    private array $sessions = [];

    // SESSION OPERATIONS ////////////////////////////////////

    #[\Override]
    public function createSession(?MessageSessionId $sessionId = null): MessageSessionId {
        $id = $sessionId ?? MessageSessionId::generate();
        $sessionKey = $id->toString();
        $this->sessions[$sessionKey] = [
            'messages' => [],
            'sections' => [],
            'leafId' => null,
            'labels' => [],
        ];
        return $id;
    }

    #[\Override]
    public function hasSession(MessageSessionId $sessionId): bool {
        return isset($this->sessions[$sessionId->toString()]);
    }

    #[\Override]
    public function load(MessageSessionId $sessionId): MessageStore {
        $this->ensureSession($sessionId);
        $sessionKey = $sessionId->toString();

        $sections = [];
        foreach ($this->sessions[$sessionKey]['sections'] as $sectionName => $messageIds) {
            $messages = [];
            foreach ($messageIds as $msgId) {
                if (isset($this->sessions[$sessionKey]['messages'][$msgId])) {
                    $messages[] = $this->sessions[$sessionKey]['messages'][$msgId];
                }
            }
            $sections[] = new Section($sectionName, new Messages(...$messages));
        }
        return MessageStore::fromSections(...$sections);
    }

    #[\Override]
    public function save(MessageSessionId $sessionId, MessageStore $store): StoreMessagesResult {
        $startedAt = new DateTimeImmutable();
        $sessionKey = $sessionId->toString();

        try {
            $this->ensureSession($sessionId);

            // Track existing messages for newMessages count
            $existingIds = array_keys($this->sessions[$sessionKey]['messages']);

            // Clear existing and rebuild from store
            $this->sessions[$sessionKey]['messages'] = [];
            $this->sessions[$sessionKey]['sections'] = [];

            $messagesStored = 0;
            $sectionsStored = 0;
            $newMessages = 0;

            foreach ($store->sections()->all() as $section) {
                $sectionName = $section->name();
                $this->sessions[$sessionKey]['sections'][$sectionName] = [];
                $sectionsStored++;

                foreach ($section->messages()->all() as $message) {
                    $messageId = $message->id()->toString();
                    $this->sessions[$sessionKey]['messages'][$messageId] = $message;
                    $this->sessions[$sessionKey]['sections'][$sectionName][] = $messageId;
                    $messagesStored++;

                    if (!in_array($messageId, $existingIds, true)) {
                        $newMessages++;
                    }

                    // Update leaf to last message
                    $this->sessions[$sessionKey]['leafId'] = $message->id();
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

    #[\Override]
    public function delete(MessageSessionId $sessionId): void {
        unset($this->sessions[$sessionId->toString()]);
    }

    // MESSAGE OPERATIONS ////////////////////////////////////

    #[\Override]
    public function append(MessageSessionId $sessionId, string $section, Message $message): Message {
        $this->ensureSession($sessionId);
        $sessionKey = $sessionId->toString();

        // Set parentId to current leaf
        $leafId = $this->sessions[$sessionKey]['leafId'];
        if ($leafId !== null && $message->parentId() === null) {
            $message = $message->withParentId($leafId);
        }

        // Store message
        $messageId = $message->id()->toString();
        $this->sessions[$sessionKey]['messages'][$messageId] = $message;

        // Add to section
        if (!isset($this->sessions[$sessionKey]['sections'][$section])) {
            $this->sessions[$sessionKey]['sections'][$section] = [];
        }
        $this->sessions[$sessionKey]['sections'][$section][] = $messageId;

        // Update leaf
        $this->sessions[$sessionKey]['leafId'] = $message->id();

        return $message;
    }

    #[\Override]
    public function get(MessageSessionId $sessionId, MessageId $messageId): ?Message {
        $this->ensureSession($sessionId);
        return $this->sessions[$sessionId->toString()]['messages'][$messageId->toString()] ?? null;
    }

    #[\Override]
    public function getSection(MessageSessionId $sessionId, string $section, ?int $limit = null): Messages {
        $this->ensureSession($sessionId);
        $sessionKey = $sessionId->toString();

        $messageIds = $this->sessions[$sessionKey]['sections'][$section] ?? [];
        if ($limit !== null) {
            $messageIds = array_slice($messageIds, -$limit);
        }

        $messages = [];
        foreach ($messageIds as $msgId) {
            if (isset($this->sessions[$sessionKey]['messages'][$msgId])) {
                $messages[] = $this->sessions[$sessionKey]['messages'][$msgId];
            }
        }

        return new Messages(...$messages);
    }

    // BRANCHING OPERATIONS //////////////////////////////////

    #[\Override]
    public function getLeafId(MessageSessionId $sessionId): ?MessageId {
        $this->ensureSession($sessionId);
        return $this->sessions[$sessionId->toString()]['leafId'];
    }

    #[\Override]
    public function navigateTo(MessageSessionId $sessionId, MessageId $messageId): void {
        $this->ensureSession($sessionId);
        $sessionKey = $sessionId->toString();

        $key = $messageId->toString();
        if (!isset($this->sessions[$sessionKey]['messages'][$key])) {
            throw new RuntimeException("Message not found: {$messageId}");
        }

        $this->sessions[$sessionKey]['leafId'] = $messageId;
    }

    #[\Override]
    public function getPath(MessageSessionId $sessionId, ?MessageId $toMessageId = null): Messages {
        $this->ensureSession($sessionId);
        $sessionKey = $sessionId->toString();

        $targetId = $toMessageId ?? $this->sessions[$sessionKey]['leafId'];
        if ($targetId === null) {
            return Messages::empty();
        }

        // Walk up the parentId chain
        $path = [];
        $currentId = $targetId;

        while ($currentId !== null) {
            $message = $this->sessions[$sessionKey]['messages'][$currentId->toString()] ?? null;
            if ($message === null) {
                break;
            }
            array_unshift($path, $message); // Prepend to get root-first order
            $currentId = $message->parentId();
        }

        return new Messages(...$path);
    }

    #[\Override]
    public function fork(MessageSessionId $sessionId, MessageId $fromMessageId): MessageSessionId {
        $this->ensureSession($sessionId);

        // Get path to the fork point
        $path = $this->getPath($sessionId, $fromMessageId);

        // Create new session
        $newSessionId = $this->createSession();
        $newSessionKey = $newSessionId->toString();

        // Copy messages up to fork point
        $section = 'messages'; // Default section for forked messages
        foreach ($path->all() as $message) {
            $messageId = $message->id()->toString();
            $this->sessions[$newSessionKey]['messages'][$messageId] = $message;
            $this->sessions[$newSessionKey]['sections'][$section][] = $messageId;
        }

        // Set leaf to fork point
        $this->sessions[$newSessionKey]['leafId'] = $fromMessageId;

        return $newSessionId;
    }

    // LABELS (CHECKPOINTS) //////////////////////////////////

    #[\Override]
    public function addLabel(MessageSessionId $sessionId, MessageId $messageId, string $label): void {
        $this->ensureSession($sessionId);
        $sessionKey = $sessionId->toString();

        $key = $messageId->toString();
        if (!isset($this->sessions[$sessionKey]['messages'][$key])) {
            throw new RuntimeException("Message not found: {$messageId}");
        }

        $this->sessions[$sessionKey]['labels'][$key] = $label;
    }

    #[\Override]
    public function getLabels(MessageSessionId $sessionId): array {
        $this->ensureSession($sessionId);
        return $this->sessions[$sessionId->toString()]['labels'];
    }

    // HELPERS ///////////////////////////////////////////////

    private function ensureSession(MessageSessionId $sessionId): void {
        $sessionKey = $sessionId->toString();
        if (!isset($this->sessions[$sessionKey])) {
            throw new RuntimeException("Session not found: {$sessionId}");
        }
    }
}
