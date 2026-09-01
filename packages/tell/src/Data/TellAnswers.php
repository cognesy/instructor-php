<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

use InvalidArgumentException;

/** Immutable, bounded answers supplied before a non-interactive Tell turn. */
final readonly class TellAnswers
{
    public const int MAX_ANSWERS = 32;
    public const int MAX_ANSWER_BYTES = 8_192;

    /** @var list<array{id: ?string, value: string, source: 'cli'|'file'|'stdin'|'sdk'}> */
    private array $entries;

    /** @param list<array{id: ?string, value: string, source: 'cli'|'file'|'stdin'|'sdk'}> $entries */
    public function __construct(array $entries = []) {
        if (count($entries) > self::MAX_ANSWERS) {
            throw new InvalidArgumentException('Tell accepts at most 32 supplied answers.');
        }
        $ids = [];
        foreach ($entries as $entry) {
            if (!array_key_exists('id', $entry) || !array_key_exists('value', $entry) || !array_key_exists('source', $entry)) {
                throw new InvalidArgumentException('Supplied answers must include id, value, and source fields.');
            }
            if ((!is_string($entry['id']) && $entry['id'] !== null) || !is_string($entry['value'])
                || !in_array($entry['source'], ['cli', 'file', 'stdin', 'sdk'], true)) {
                throw new InvalidArgumentException('Supplied answers must have a string value and a supported source.');
            }
            if (strlen($entry['value']) > self::MAX_ANSWER_BYTES) {
                throw new InvalidArgumentException('Each supplied answer must not exceed 8192 bytes.');
            }
            if ($entry['id'] === null) {
                continue;
            }
            if ($entry['id'] === '' || strlen($entry['id']) > 128 || isset($ids[$entry['id']])) {
                throw new InvalidArgumentException('Supplied answer IDs must be unique non-empty strings.');
            }
            $ids[$entry['id']] = true;
        }
        $this->entries = array_values($entries);
    }

    /** @param list<string> $answers */
    public static function fromStrings(array $answers): self {
        return new self(array_map(
            static fn (string $answer): array => ['id' => null, 'value' => $answer, 'source' => 'sdk'],
            $answers,
        ));
    }

    /** @return list<array{id: ?string, value: string, source: 'cli'|'file'|'stdin'|'sdk'}> */
    public function entries(): array {
        return $this->entries;
    }

    public function count(): int {
        return count($this->entries);
    }
}
