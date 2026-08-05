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
 * One JSON entry per line. Session trees are expressed as parentId chains in messages,
 * so a single file can hold several branches.
 *
 * File format:
 * - Line 1: Session header {"type":"session","id":"...","createdAt":"..."}
 * - Lines 2+: Entries {"type":"message"|"label","id":"...","parentId":"...","data":{...}}
 *
 * Two write paths, with deliberately different contracts:
 *
 * - append() is append-only: it adds one entry and never touches what is already in the
 *   file. Combined with navigateTo(), which moves the leaf appends attach to, it is how a
 *   session grows, branches included.
 * - save() REPLACES the session with exactly the passed MessageStore - it rewrites the
 *   file, so any message not present in that store is gone, branches included. That is
 *   the same contract InMemoryStorage::save() implements, and load() returns every
 *   message in the session, so the ordinary load -> mutate -> save cycle is lossless.
 *   Saving a store assembled from a SUBSET of a session (a single path, a filtered set
 *   of sections) prunes the session to that subset - by design, but rarely what a caller
 *   wants. Reach for append() when the intent is additive.
 *
 * save() is atomic: the new contents are written to a temp file in the same directory and
 * renamed into place, so an interrupted save leaves the previous file untouched rather
 * than truncated. It was previously a truncate-then-append-per-message sequence, where a
 * throw partway through destroyed the session and then reported failure over lost data.
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
            'leafId' => null,
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

    /**
     * Replaces the session with exactly $store - see the class docblock for why that is
     * the contract and when to use append() instead.
     */
    #[\Override]
    public function save(MessageSessionId $sessionId, MessageStore $store): StoreMessagesResult {
        $startedAt = new DateTimeImmutable();
        $sessionKey = $sessionId->toString();

        try {
            $this->ensureLoaded($sessionId);
            $previousLeafId = $this->sessions[$sessionKey]['leafId'];
            $file = $this->sessionFile($sessionId);

            // Keyed by id, not a list: the previous code asked in_array() once per message
            // saved, which is quadratic in session length on a path that already has to
            // touch every message.
            $existingIds = $this->sessions[$sessionKey]['index'];
            $finalLeafId = $this->resolveLeafIdForSave($store, $previousLeafId);

            // The whole file is buffered before anything is written. That is what makes the
            // write atomic (one temp file, one rename) and what turns a per-message
            // open+write+close into a single write.
            $lines = [
                json_encode([
                    'type' => 'session',
                    'version' => self::VERSION,
                    'id' => $sessionKey,
                    'createdAt' => $startedAt->format(DateTimeImmutable::ATOM),
                    'leafId' => $finalLeafId?->toString(),
                ]) . "\n",
            ];

            $messagesStored = 0;
            $sectionsStored = 0;
            $newMessages = 0;

            foreach ($store->sections()->all() as $section) {
                $sectionsStored++;
                foreach ($section->messages()->all() as $message) {
                    $messageId = $message->id()->toString();
                    $lines[] = json_encode([
                        'type' => 'message',
                        'id' => $messageId,
                        'parentId' => $message->parentId() !== null ? (string) $message->parentId() : null,
                        'section' => $section->name(),
                        'timestamp' => $message->createdAt->format(DateTimeImmutable::ATOM),
                        'data' => $message->toArray(),
                    ]) . "\n";
                    $messagesStored++;

                    if (!isset($existingIds[$messageId])) {
                        $newMessages++;
                    }
                }
            }

            foreach ($this->sessions[$sessionKey]['labels'] as $messageId => $label) {
                $lines[] = json_encode([
                    'type' => 'label',
                    'id' => Uuid::uuid4(),
                    'parentId' => $finalLeafId?->toString(),
                    'targetId' => $messageId,
                    'label' => $label,
                    'timestamp' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
                ]) . "\n";
            }

            $this->writeAtomically($file, implode('', $lines));

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
            'parentId' => $message->parentId() !== null ? (string) $message->parentId() : null,
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
        $visited = [];

        while ($currentId !== null) {
            $currentKey = $currentId->toString();
            if (isset($visited[$currentKey])) {
                // A parentId cycle (only reachable via a hand-built Message or a corrupted
                // session file) would otherwise spin forever. getPath() is a read path, so a
                // partial, root-first path is more useful here than throwing.
                break;
            }
            $visited[$currentKey] = true;

            $entry = $this->sessions[$sessionKey]['index'][$currentKey] ?? null;
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
        $sessionKey = $sessionId->toString();

        if (!isset($this->sessions[$sessionKey]['index'][$fromMessageId->toString()])) {
            throw new RuntimeException("Message not found: {$fromMessageId}");
        }

        // Get path to fork point
        $path = $this->getPath($sessionId, $fromMessageId);

        // Create new session (only after the fork point is confirmed to exist, so a
        // rejected fork does not leave an orphan session file behind)
        $newSessionId = $this->createSession();

        // Copy messages
        foreach ($path->all() as $message) {
            $messageId = $message->id()->toString();
            $entry = $this->sessions[$sessionKey]['index'][$messageId] ?? null;
            $section = is_array($entry)
                ? ($entry['section'] ?? 'messages')
                : 'messages';
            $this->append($newSessionId, $section, $message);
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
            'parentId' => $this->sessions[$sessionKey]['leafId'] !== null ? (string) $this->sessions[$sessionKey]['leafId'] : null,
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

    /**
     * Writes $contents to $file so that readers see either the old file or the new one and
     * never a partial write. The temp file is created in the SAME directory on purpose:
     * rename() is atomic only within a filesystem, so a system temp dir would silently
     * degrade this to a copy. Anything that fails leaves the previous file in place.
     *
     * No locking. Two concurrent saves still resolve to whichever renamed last, and this
     * storage is single-process in current usage; locking cannot be layered on rename()
     * anyway (you cannot hold a lock on a file you are about to replace) and would need a
     * sidecar lock file held across the whole read-modify-write.
     *
     * @throws RuntimeException if the temp file cannot be created, written, or moved
     */
    private function writeAtomically(string $file, string $contents): void {
        // Not tempnam(): when the requested directory is not writable it silently falls back
        // to the system temp dir, which puts the temp file on another filesystem and quietly
        // turns the rename below into a non-atomic copy. Opening 'x' in the target directory
        // instead keeps the file where it must be and fails loudly if it cannot.
        $directory = dirname($file);
        // Checked up front so the ordinary "directory is not writable" failure reports the
        // directory instead of surfacing an fopen() warning naming a temp file the caller
        // never asked for.
        if (!is_writable($directory)) {
            throw new RuntimeException("Cannot write session file - directory is not writable: {$directory}");
        }

        $temp = $file . '.tmp-' . bin2hex(random_bytes(8));
        $handle = @fopen($temp, 'xb');
        if ($handle === false) {
            throw new RuntimeException("Cannot create a temporary session file in: {$directory}");
        }

        try {
            if (@fwrite($handle, $contents) !== strlen($contents)) {
                throw new RuntimeException("Cannot write session file: {$file}");
            }
            fclose($handle);
            $handle = null;

            // fopen() applies the umask, so the temp file already has the mode a fresh
            // session file would get; an existing file's mode is preserved instead, so a
            // save does not silently re-permission a session someone chmod'ed.
            $mode = match (true) {
                file_exists($file) => fileperms($file) & 0777,
                default => null,
            };
            if ($mode !== null) {
                @chmod($temp, $mode);
            }

            if (!@rename($temp, $file)) {
                throw new RuntimeException("Cannot move session file into place: {$file}");
            }
        } catch (\Throwable $e) {
            if ($handle !== null) {
                fclose($handle);
            }
            @unlink($temp);
            throw $e;
        }
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
        $preferredLeafId = null;
        while (($line = fgets($handle)) !== false) {
            $lineNum++;
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $entry = json_decode($line, true);
            // A session file is append-only and may be truncated mid-write, hand-edited, or
            // carry a record written by a newer version. Every line must therefore be proven
            // to be a keyed entry with a type before any key is read: `$entry === null` alone
            // let a scalar or a type-less object through and produced one "Undefined array
            // key" warning per read below. Unrecognized lines are skipped, so an unknown
            // record type from a future writer degrades to being ignored rather than fatal.
            if (!is_array($entry) || !is_string($entry['type'] ?? null)) {
                continue;
            }

            // Skip session header
            if ($entry['type'] === 'session') {
                $preferredLeafValue = $entry['leafId'] ?? null;
                if (is_string($preferredLeafValue) && $preferredLeafValue !== '') {
                    $preferredLeafId = new MessageId($preferredLeafValue);
                }
                continue;
            }

            // Index messages. A message record without a usable id cannot be indexed or
            // become the leaf, and would have reached MessageId with null.
            if ($entry['type'] === 'message') {
                $id = $entry['id'] ?? null;
                if (!is_string($id) || $id === '') {
                    continue;
                }
                $this->sessions[$sessionKey]['index'][$id] = $entry;
                $this->sessions[$sessionKey]['leafId'] = new MessageId($id);
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

        if ($preferredLeafId === null) {
            return;
        }
        if (!isset($this->sessions[$sessionKey]['index'][$preferredLeafId->toString()])) {
            return;
        }
        $this->sessions[$sessionKey]['leafId'] = $preferredLeafId;
    }

    private function resolveLeafIdForSave(MessageStore $store, ?MessageId $previousLeafId): ?MessageId {
        $savedMessageIds = [];
        $lastMessageId = null;

        foreach ($store->sections()->all() as $section) {
            foreach ($section->messages()->all() as $message) {
                $messageId = $message->id()->toString();
                $savedMessageIds[$messageId] = true;
                $lastMessageId = $message->id();
            }
        }

        $previousLeafKey = $previousLeafId?->toString();
        return match (true) {
            $previousLeafKey !== null && isset($savedMessageIds[$previousLeafKey]) => $previousLeafId,
            default => $lastMessageId,
        };
    }
}
