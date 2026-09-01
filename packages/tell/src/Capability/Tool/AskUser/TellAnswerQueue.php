<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Tool\AskUser;

use Cognesy\Tell\Data\TellAnswers;

/** Bounded, in-memory answers supplied before a non-interactive Tell turn. */
final class TellAnswerQueue
{
    /** @var list<array{id: ?string, value: string, source: 'cli'|'file'|'stdin'|'sdk'}> */
    private array $entries;

    public function __construct(TellAnswers $answers) {
        $this->entries = $answers->entries();
    }

    /** @return array{success: bool, answer: ?string, source: ?string, error: ?array{code: string, message: string}} */
    public function take(?string $questionId, array $choices): array {
        $index = $questionId === null ? 0 : $this->find($questionId);
        if ($index === null || !isset($this->entries[$index])) {
            return ['success' => false, 'answer' => null, 'source' => null, 'error' => [
                'code' => 'answer_unavailable',
                'message' => 'No pre-supplied answer is available for this question.',
            ]];
        }
        $entry = $this->entries[$index];
        array_splice($this->entries, $index, 1);
        if ($choices !== [] && !in_array($entry['value'], $choices, true)) {
            return ['success' => false, 'answer' => null, 'source' => $entry['source'], 'error' => [
                'code' => 'invalid_choice',
                'message' => 'The supplied answer is not one of the declared choices.',
            ]];
        }

        return ['success' => true, 'answer' => $entry['value'], 'source' => $entry['source'], 'error' => null];
    }

    public function remaining(): int {
        return count($this->entries);
    }

    private function find(string $id): ?int {
        foreach ($this->entries as $index => $entry) {
            if ($entry['id'] === $id) {
                return $index;
            }
        }

        return null;
    }
}
