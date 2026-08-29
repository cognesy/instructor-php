<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\AskUser;

use Cognesy\Agents\Tool\ToolDescriptor;
use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Override;

/** A non-blocking answer source for model-driven Tell tool calls. */
final class AskUserTool extends SimpleTool
{
    public function __construct(private readonly TellAnswerQueue $answers) {
        parent::__construct(new ToolDescriptor(
            name: 'ask_user',
            description: 'Consume one pre-supplied non-interactive Tell answer; never reads from a terminal.',
            metadata: [
                'effect' => 'read',
                'bounds' => ['answers' => TellAnswerQueue::MAX_ANSWERS, 'answerBytes' => TellAnswerQueue::MAX_ANSWER_BYTES],
                'nonInteractive' => true,
                'sensitiveResult' => true,
            ],
            instructions: [
                'source' => 'Answers are supplied before Tell starts with --answer, --answers-file, or --answers-stdin.',
                'unavailable' => 'Returns answer_unavailable immediately if no answer is available; it never prompts a person.',
            ],
        ));
    }

    #[Override]
    public function __invoke(mixed ...$args): array {
        $question = (string) $this->arg($args, 'question', 0, '');
        $id = $this->arg($args, 'id', 1, null);
        $choices = $this->arg($args, 'choices', 2, []);
        $guidance = $this->arg($args, 'guidance', 3, null);
        if ($question === '' || strlen($question) > 2_000 || (!is_string($id) && $id !== null) || (is_string($id) && (strlen($id) > 128)) || (!is_string($guidance) && $guidance !== null) || (is_string($guidance) && strlen($guidance) > 1_000) || !is_array($choices)) {
            return $this->failure('invalid_question', 'Question input does not satisfy ask_user bounds.');
        }
        if (count($choices) > 16) {
            return $this->failure('invalid_question', 'ask_user accepts at most 16 bounded choices.');
        }
        foreach ($choices as $choice) {
            if (!is_string($choice) || strlen($choice) > 512) {
                return $this->failure('invalid_question', 'ask_user accepts at most 16 bounded choices.');
            }
        }
        /** @var list<string> $choices */
        $answer = $this->answers->take($id, $choices);
        if (!$answer['success']) {
            $error = $answer['error'];
            if ($error === null) {
                return $this->failure('answer_unavailable', 'No answer is available for this question.', $answer['source']);
            }

            return $this->failure($error['code'], $error['message'], $answer['source']);
        }

        return [
            'success' => true,
            'answer' => $answer['answer'],
            'source' => $answer['source'],
            'error' => null,
        ];
    }

    #[Override]
    public function toToolSchema(): ToolDefinition {
        return new ToolDefinition('ask_user', $this->description(), [
            'type' => 'object',
            'properties' => [
                'question' => ['type' => 'string', 'description' => 'Bounded non-secret question text.'],
                'id' => ['type' => 'string', 'description' => 'Optional stable question ID for exact answer lookup.'],
                'choices' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional bounded allowed answers.'],
                'guidance' => ['type' => 'string', 'description' => 'Optional non-secret answer guidance.'],
            ],
            'required' => ['question'],
        ]);
    }

    /** @return array{success: false, answer: null, source: ?string, error: array{code: string, message: string}} */
    private function failure(string $code, string $message, ?string $source = null): array {
        return ['success' => false, 'answer' => null, 'source' => $source, 'error' => ['code' => $code, 'message' => $message]];
    }
}
