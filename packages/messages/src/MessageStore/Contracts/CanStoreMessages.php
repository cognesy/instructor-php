<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Contracts;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
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
    public function createSession(?string $sessionId = null): string;

    /**
     * Check if a session exists.
     */
    public function hasSession(string $sessionId): bool;

    /**
     * Load session into a MessageStore.
     */
    public function load(string $sessionId): MessageStore;

    /**
     * Save entire MessageStore to storage.
     */
    public function save(string $sessionId, MessageStore $store): StoreMessagesResult;

    /**
     * Delete a session and all its messages.
     */
    public function delete(string $sessionId): void;

    // MESSAGE OPERATIONS ////////////////////////////////////

    /**
     * Append a message, setting its parentId to current leaf.
     */
    public function append(string $sessionId, string $section, Message $message): Message;

    /**
     * Get a message by ID.
     */
    public function get(string $sessionId, string $messageId): ?Message;

    /**
     * Get messages from a section.
     */
    public function getSection(string $sessionId, string $section, ?int $limit = null): Messages;

    // BRANCHING OPERATIONS //////////////////////////////////

    /**
     * Get current leaf message ID (where appends attach).
     */
    public function getLeafId(string $sessionId): ?string;

    /**
     * Navigate to a message, making it the leaf for future appends.
     */
    public function navigateTo(string $sessionId, string $messageId): void;

    /**
     * Get path from root to a message (for context building).
     */
    public function getPath(string $sessionId, ?string $toMessageId = null): Messages;

    /**
     * Fork session from a message point into a new session.
     */
    public function fork(string $sessionId, string $fromMessageId): string;

    // LABELS (CHECKPOINTS) //////////////////////////////////

    /**
     * Add a label to a message.
     */
    public function addLabel(string $sessionId, string $messageId, string $label): void;

    /**
     * Get all labels in session.
     *
     * @return array<string, string> messageId => label
     */
    public function getLabels(string $sessionId): array;
}
