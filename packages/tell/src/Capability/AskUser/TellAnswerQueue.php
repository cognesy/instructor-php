<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\AskUser;

use InvalidArgumentException;
use JsonException;
use Symfony\Component\Console\Input\InputInterface;

/** Bounded, in-memory answers supplied before a non-interactive Tell turn. */
final class TellAnswerQueue
{
    public const int MAX_ANSWERS = 32;
    public const int MAX_ANSWER_BYTES = 8_192;

    /** @var list<array{id: ?string, value: string, source: 'cli'|'file'|'stdin'}> */
    private array $entries;

    /** @param list<array{id: ?string, value: string, source: 'cli'|'file'|'stdin'}> $entries */
    public function __construct(array $entries = [])
    {
        if (count($entries) > self::MAX_ANSWERS) {
            throw new InvalidArgumentException('Tell accepts at most 32 supplied answers.');
        }
        $ids = [];
        foreach ($entries as $entry) {
            if (! is_array($entry) || ! array_key_exists('id', $entry) || ! array_key_exists('value', $entry) || ! array_key_exists('source', $entry)) {
                throw new InvalidArgumentException('Supplied answers must include id, value, and source fields.');
            }
            if ((! is_string($entry['id']) && $entry['id'] !== null) || ! is_string($entry['value']) || ! is_string($entry['source']) || ! in_array($entry['source'], ['cli', 'file', 'stdin'], true)) {
                throw new InvalidArgumentException('Supplied answers must have a string value and a supported source.');
            }
            if (strlen($entry['value']) > self::MAX_ANSWER_BYTES) {
                throw new InvalidArgumentException('Each supplied answer must not exceed 8192 bytes.');
            }
            if ($entry['id'] !== null) {
                if (! is_string($entry['id']) || $entry['id'] === '' || strlen($entry['id']) > 128 || isset($ids[$entry['id']])) {
                    throw new InvalidArgumentException('Supplied answer IDs must be unique non-empty strings.');
                }
                $ids[$entry['id']] = true;
            }
        }
        $this->entries = $entries;
    }

    public static function fromInput(InputInterface $input): self
    {
        /** @var list<string> $cli */
        $cli = $input->getOption('answer');
        $file = $input->getOption('answers-file');
        $stdin = (bool) $input->getOption('answers-stdin');
        if ((! is_array($cli))) {
            throw new InvalidArgumentException('--answer must be repeatable string input.');
        }
        if ($cli !== [] && ((is_string($file) && $file !== '') || $stdin)) {
            throw new InvalidArgumentException('Use either repeatable --answer or exactly one structured answer source.');
        }
        if ((is_string($file) && $file !== '') && $stdin) {
            throw new InvalidArgumentException('Use either --answers-file or --answers-stdin, not both.');
        }
        if ($cli !== []) {
            return new self(array_map(static fn (string $value): array => ['id' => null, 'value' => $value, 'source' => 'cli'], $cli));
        }
        if (! $stdin && (! is_string($file) || $file === '')) {
            return new self;
        }
        $raw = match (true) {
            $stdin => stream_get_contents(STDIN, 262_145),
            default => is_file($file) ? file_get_contents($file) : false,
        };
        if (! is_string($raw) || strlen($raw) > 262_144 || preg_match('//u', $raw) !== 1) {
            throw new InvalidArgumentException('Structured answers must be readable UTF-8 JSON no larger than 262144 bytes.');
        }
        try {
            $items = json_decode($raw, true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Structured answers must be a valid JSON array.');
        }
        if (! is_array($items) || ! array_is_list($items)) {
            throw new InvalidArgumentException('Structured answers must be a JSON array.');
        }
        $source = $stdin ? 'stdin' : 'file';
        $entries = [];
        foreach ($items as $item) {
            $entry = is_string($item) ? ['id' => null, 'value' => $item] : $item;
            if (! is_array($entry) || ! array_key_exists('value', $entry) || ! is_string($entry['value'])
                || (isset($entry['id']) && ! is_string($entry['id']))) {
                throw new InvalidArgumentException('Each structured answer must be a string or an object with string value and optional id.');
            }
            $entries[] = ['id' => $entry['id'] ?? null, 'value' => $entry['value'], 'source' => $source];
        }

        return new self($entries);
    }

    /** @return array{success: bool, answer: ?string, source: ?string, error: ?array{code: string, message: string}} */
    public function take(?string $questionId, array $choices): array
    {
        $index = $questionId === null ? 0 : $this->find($questionId);
        if ($index === null || ! isset($this->entries[$index])) {
            return ['success' => false, 'answer' => null, 'source' => null, 'error' => [
                'code' => 'answer_unavailable',
                'message' => 'No pre-supplied answer is available for this question.',
            ]];
        }
        $entry = $this->entries[$index];
        array_splice($this->entries, $index, 1);
        if ($choices !== [] && ! in_array($entry['value'], $choices, true)) {
            return ['success' => false, 'answer' => null, 'source' => $entry['source'], 'error' => [
                'code' => 'invalid_choice',
                'message' => 'The supplied answer is not one of the declared choices.',
            ]];
        }

        return ['success' => true, 'answer' => $entry['value'], 'source' => $entry['source'], 'error' => null];
    }

    public function remaining(): int
    {
        return count($this->entries);
    }

    private function find(string $id): ?int
    {
        foreach ($this->entries as $index => $entry) {
            if ($entry['id'] === $id) {
                return $index;
            }
        }

        return null;
    }
}
