<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Cognesy\Agents\Tool\ToolDescriptor;
use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;
use InvalidArgumentException;
use Override;

/**
 * The judge's terminal tool. Calling it records a `JudgeSubmission` into the
 * injected `JudgeSubmissionInbox` and ends the judge run - `JudgeProtocolHook`
 * stops the loop on the next `AfterStep` and blocks any further
 * `submit_judgment` call. See `01-architecture.md` "Terminal submission
 * ownership".
 *
 * Argument validation happens here, not in `JudgeSubmission`'s constructor,
 * so a malformed call fails as an ordinary tool failure (caught by
 * `HasResultWrapper::use()`) rather than an uncaught exception - that failure
 * is what drives `AgentState::hasErrors()` and, in turn,
 * `AgentLoopJudge::judge()`'s `JudgeProtocolException`.
 */
final class SubmitJudgmentTool extends SimpleTool
{
    public const string TOOL_NAME = 'submit_judgment';

    public function __construct(private readonly JudgeSubmissionInbox $inbox) {
        parent::__construct(new ToolDescriptor(
            name: self::TOOL_NAME,
            description: <<<'DESC'
Submit your final judgment for this evaluation. Call this exactly once, when
you have enough evidence to decide against the given criterion. Calling this
tool ends the judge run.
DESC,
            metadata: [
                'name' => self::TOOL_NAME,
                'summary' => 'Submit the judge verdict and end the judge run.',
                'namespace' => 'judge',
                'tags' => ['judge', 'terminal'],
            ],
            instructions: [
                'parameters' => [
                    'score' => 'Score between 0 and 1, inclusive.',
                    'reason' => 'Concise, observable justification for the score.',
                    'evidence' => 'Optional list of short evidence strings.',
                ],
                'returns' => 'Confirmation that the judgment was recorded.',
            ],
        ));
    }

    #[Override]
    public function __invoke(mixed ...$args): array {
        $score = $this->arg($args, 'score', 0);
        $reason = $this->arg($args, 'reason', 1);
        $evidence = $this->arg($args, 'evidence', 2, []);

        if (!is_int($score) && !is_float($score)) {
            throw new InvalidArgumentException('submit_judgment: "score" must be a number.');
        }
        $score = (float) $score;
        if (!is_finite($score) || $score < 0.0 || $score > 1.0) {
            throw new InvalidArgumentException('submit_judgment: "score" must be between 0 and 1.');
        }

        if (!is_string($reason) || trim($reason) === '') {
            throw new InvalidArgumentException('submit_judgment: "reason" must be a non-empty string.');
        }

        if (!is_array($evidence)) {
            throw new InvalidArgumentException('submit_judgment: "evidence" must be a list of strings.');
        }
        foreach ($evidence as $item) {
            if (!is_string($item)) {
                throw new InvalidArgumentException('submit_judgment: "evidence" must be a list of strings.');
            }
        }

        $this->inbox->submit(new JudgeSubmission(
            score: $score,
            reason: $reason,
            evidence: JudgeEvidence::of(...array_values($evidence)),
        ));

        return ['submitted' => true];
    }

    #[Override]
    public function toToolSchema(): ToolDefinition {
        return ToolDefinition::fromArray(ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::number('score', 'Score between 0 and 1, inclusive.'),
                    JsonSchema::string('reason', 'Concise, observable justification for the score.'),
                    JsonSchema::array('evidence', JsonSchema::string('item'), 'Optional list of short evidence strings.'),
                ])
                ->withRequiredProperties(['score', 'reason']),
        )->toArray());
    }
}
