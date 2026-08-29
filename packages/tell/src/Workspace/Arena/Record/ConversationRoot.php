<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Arena\Record;

use Cognesy\Tell\Workspace\Arena\RecordException;
use Override;

final readonly class ConversationRoot implements StoredRecord
{
    /** @param list<Message> $messages */
    public function __construct(
        private string $id,
        private array $messages = [],
        private ?SessionMetadata $session = null,
    ) {
        Input::identifier($id, 'conversation id');
        foreach ($messages as $message) {
            if (!$message instanceof Message) {
                throw new RecordException('Arena conversation root entries must be record messages.');
            }
            if ($message->role() === Role::Assistant) {
                throw new RecordException('Arena conversation roots cannot contain assistant messages.');
            }
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self {
        Input::assertKeys($data, ['id', 'kind', 'messages', 'schema'], ['session']);
        if ($data['kind'] !== 'conversation') {
            throw new RecordException('Arena record is not a conversation root.');
        }
        Input::schema($data['schema']);
        $messages = array_map(
            static fn (mixed $message): Message => Message::fromArray(Input::map($message, 'conversation message')),
            Input::list($data['messages'], 'conversation messages'),
        );

        $session = match (true) {
            !array_key_exists('session', $data) => null,
            default => SessionMetadata::fromArray(Input::map($data['session'], 'conversation session')),
        };

        return new self(Input::identifier($data['id'], 'conversation id'), $messages, $session);
    }

    #[Override]
    public function kind(): string {
        return 'conversation';
    }

    public function id(): string {
        return $this->id;
    }

    /** @return list<Message> */
    public function messages(): array {
        return $this->messages;
    }

    public function session(): ?SessionMetadata {
        return $this->session;
    }

    #[Override]
    /** @return array<string, mixed> */
    public function toArray(): array {
        $record = [
            'id' => $this->id,
            'kind' => 'conversation',
            'messages' => array_map(static fn (Message $message): array => $message->toArray(), $this->messages),
            'schema' => StoredRecord::SCHEMA_VERSION,
        ];
        if ($this->session !== null) {
            $record['session'] = $this->session->toArray();
        }

        return $record;
    }
}
