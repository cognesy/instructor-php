<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Contracts;

use Cognesy\Messages\Message;
use Cognesy\Messages\MessageId;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageSessionId;
use Cognesy\Messages\MessageStore\Data\StoreMessagesResult;
use Cognesy\Messages\MessageStore\MessageStore;

/**
 * Contract for message storage backends.
 *
 * Supports various backends: in-memory, JSONL files, database.
 * Branching is supported via Message::$parentId - the storage
 * tracks the current leaf and maintains the tree structure.
 */
interface CanStoreMessages
{
    // SESSION OPERATIONS ////////////////////////////////////

    /**
     * Create a new session and return its ID.
     */
    public function createSession(?MessageSessionId $sessionId = null): MessageSessionId;

    /**
     * Check if a session exists.
     */
    public function hasSession(MessageSessionId $sessionId): bool;

    /**
     * Load session into a MessageStore.
     */
    public function load(MessageSessionId $sessionId): MessageStore;

    /**
     * Save entire MessageStore to storage.
     */
    public function save(MessageSessionId $sessionId, MessageStore $store): StoreMessagesResult;

    /**
     * Delete a session and all its messages.
     */
    public function delete(MessageSessionId $sessionId): void;

    // MESSAGE OPERATIONS ////////////////////////////////////

    /**
     * Append a message, setting its parentId to current leaf.
     */
    public function append(MessageSessionId $sessionId, string $section, Message $message): Message;

    /**
     * Get a message by ID.
     */
    public function get(MessageSessionId $sessionId, MessageId $messageId): ?Message;

    /**
     * Get messages from a section.
     */
    public function getSection(MessageSessionId $sessionId, string $section, ?int $limit = null): Messages;

    // BRANCHING OPERATIONS //////////////////////////////////

    /**
     * Get current leaf message ID (where appends attach).
     */
    public function getLeafId(MessageSessionId $sessionId): ?MessageId;

    /**
     * Navigate to a message, making it the leaf for future appends.
     */
    public function navigateTo(MessageSessionId $sessionId, MessageId $messageId): void;

    /**
     * Get path from root to a message (for context building).
     */
    public function getPath(MessageSessionId $sessionId, ?MessageId $toMessageId = null): Messages;

    /**
     * Fork session from a message point into a new session.
     */
    public function fork(MessageSessionId $sessionId, MessageId $fromMessageId): MessageSessionId;

    // LABELS (CHECKPOINTS) //////////////////////////////////

    /**
     * Add a label to a message.
     */
    public function addLabel(MessageSessionId $sessionId, MessageId $messageId, string $label): void;

    /**
     * Get all labels in session.
     *
     * @return array<string, string> messageId => label
     */
    public function getLabels(MessageSessionId $sessionId): array;
}
