<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Closure;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;
use JsonException;
use Override;
use RuntimeException;

final readonly class PolyglotAgentJudge implements CanJudgeAgentEval
{
    /** @param Closure(string): string $invoke */
    private function __construct(private Closure $invoke) {}

    public static function fromInference(Inference $inference): self {
        return new self(static fn (string $prompt): string => $inference
            ->with(messages: Messages::fromString($prompt))
            ->get());
    }

    /** @param Closure(string): string $invoke */
    public static function fromInvoker(Closure $invoke): self {
        return new self($invoke);
    }

    /** @throws JsonException */
    #[Override]
    public function judge(JudgeRequest $request): JudgeScore {
        $prompt = $this->prompt($request);
        $data = json_decode(($this->invoke)($prompt), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['score'], $data['reason']) || !is_numeric($data['score']) || !is_string($data['reason'])) {
            throw new RuntimeException('Judge response must contain numeric score and string reason.');
        }
        return new JudgeScore((float) $data['score'], $data['reason']);
    }

    private function prompt(JudgeRequest $request): string {
        return "Grade an agent response. Return strict JSON {\"score\":0.0,\"reason\":\"evidence\"}.\n"
            . "Criterion: {$request->criterion}\nInput: {$request->input}\nReference: "
            . ($request->reference ?? '') . "\nOutput: {$request->output}";
    }
}
