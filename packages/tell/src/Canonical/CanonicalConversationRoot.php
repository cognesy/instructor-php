<?php

declare(strict_types=1);

namespace Cognesy\Tell\Canonical;

final readonly class CanonicalConversationRoot implements CanonicalRecord
{
    /** @param list<CanonicalMessage> $messages */
    public function __construct(
        private string $id,
        private array $messages = [],
        private ?CanonicalSessionMetadata $session = null,
    ) {
        CanonicalInput::identifier($id, 'conversation id');
        foreach ($messages as $message) {
            if (! $message instanceof CanonicalMessage) {
                throw new CanonicalValidationException('Canonical conversation messages must be messages.');
            }
            if ($message->role() === CanonicalRole::Assistant) {
                throw new CanonicalValidationException('Canonical conversation roots cannot contain assistant messages.');
            }
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        CanonicalInput::assertKeys($data, ['id', 'kind', 'messages', 'schema'], ['session']);
        if ($data['kind'] !== 'conversation') {
            throw new CanonicalValidationException('Canonical record is not a conversation root.');
        }
        if (! is_int($data['schema'])) {
            throw new CanonicalValidationException('Canonical schema must be an integer.');
        }
        CanonicalSchema::assertSupported($data['schema']);
        $messages = array_map(
            static fn (mixed $message): CanonicalMessage => CanonicalMessage::fromArray(CanonicalInput::map($message, 'conversation message')),
            CanonicalInput::list($data['messages'], 'conversation messages'),
        );

        $session = match (true) {
            ! array_key_exists('session', $data) => null,
            default => CanonicalSessionMetadata::fromArray(CanonicalInput::map($data['session'], 'conversation session')),
        };

        return new self(CanonicalInput::identifier($data['id'], 'conversation id'), $messages, $session);
    }

    public function kind(): string
    {
        return 'conversation';
    }

    public function schema(): int
    {
        return CanonicalSchema::VERSION;
    }

    public function id(): string
    {
        return $this->id;
    }

    /** @return list<CanonicalMessage> */
    public function messages(): array
    {
        return $this->messages;
    }

    public function session(): ?CanonicalSessionMetadata
    {
        return $this->session;
    }

    /** @return array<string, mixed> */
    public function toCanonicalArray(): array
    {
        $record = [
            'id' => $this->id,
            'kind' => 'conversation',
            'messages' => array_map(static fn (CanonicalMessage $message): array => $message->toCanonicalArray(), $this->messages),
            'schema' => $this->schema(),
        ];
        if ($this->session !== null) {
            $record['session'] = $this->session->toCanonicalArray();
        }

        return $record;
    }
}
