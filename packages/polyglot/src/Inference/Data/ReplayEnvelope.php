<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Messages\Message;
use InvalidArgumentException;

/**
 * Versioned adapter-owned replay data aligned with ordered assistant message parts.
 */
final readonly class ReplayEnvelope
{
    public const int CURRENT_VERSION = 1;
    public const string MESSAGE_METADATA_KEY = 'polyglot_replay';

    /** @param null|list<mixed> $parts */
    public function __construct(
        private string $owner,
        private mixed $response = null,
        private ?array $parts = null,
        private int $version = self::CURRENT_VERSION,
    ) {
        if ($owner === '') {
            throw new InvalidArgumentException('Replay envelope owner cannot be empty.');
        }
    }

    /** @param list<mixed> $parts */
    public static function fromParts(string $owner, array $parts, mixed $response = null): ?self
    {
        foreach ($parts as $part) {
            if (!self::isEmptyValue($part)) {
                return new self(owner: $owner, response: $response, parts: $parts);
            }
        }
        return null;
    }

    public static function fromArray(array $data): ?self
    {
        $version = $data['version'] ?? null;
        $owner = $data['owner'] ?? null;
        $parts = $data['parts'] ?? null;
        if ($version !== self::CURRENT_VERSION || !is_string($owner) || $owner === '') {
            return null;
        }
        if ($parts !== null && (!is_array($parts) || !array_is_list($parts))) {
            return null;
        }
        return new self(
            owner: $owner,
            response: $data['response'] ?? null,
            parts: $parts,
            version: $version,
        );
    }

    public static function fromMessage(Message $message, string $owner): ?self
    {
        $data = $message->metadata()->get(self::MESSAGE_METADATA_KEY);
        $envelope = match (true) {
            is_array($data) => self::fromArray($data),
            default => null,
        };
        if ($envelope === null || !$envelope->isOwnedBy($owner)) {
            return null;
        }
        return match ($envelope->alignsWith($message->parts()->count())) {
            true => $envelope,
            false => null,
        };
    }

    public function owner(): string
    {
        return $this->owner;
    }

    public function response(): mixed
    {
        return $this->response;
    }

    /** @return null|list<mixed> */
    public function parts(): ?array
    {
        return $this->parts;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function isOwnedBy(string $owner): bool
    {
        return $this->owner === $owner;
    }

    public function alignsWith(int $partCount): bool
    {
        return $this->parts === null || count($this->parts) === $partCount;
    }

    public function hasPartData(int $position): bool
    {
        if ($this->parts === null || !array_key_exists($position, $this->parts)) {
            return false;
        }
        return !self::isEmptyValue($this->parts[$position]);
    }

    /** @param list<bool> $kept */
    public function retaining(array $kept): ?self
    {
        if ($this->parts === null) {
            return $this;
        }
        if (!$this->alignsWith(count($kept))) {
            return null;
        }

        $parts = [];
        foreach ($this->parts as $position => $part) {
            if ($kept[$position]) {
                $parts[] = $part;
            }
        }
        return new self(
            owner: $this->owner,
            response: $this->response,
            parts: $parts,
            version: $this->version,
        );
    }

    public function toArray(): array
    {
        $data = [
            'version' => $this->version,
            'owner' => $this->owner,
            'response' => $this->response,
        ];
        if ($this->parts !== null) {
            $data['parts'] = $this->parts;
        }
        return $data;
    }

    private static function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
