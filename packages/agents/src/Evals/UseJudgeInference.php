<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Override;

/**
 * Recommended driver capability for judge builders passed to
 * `AgentLoopJudge::fromBuilder()`. Installs a `ToolCallingDriver` with
 * `temperature: 0.0` by default, because slice 6's `--repeat=N` measures
 * target variance, not judge noise - a wobbly judge would confound it.
 * Caller-supplied `$options` win over the default, e.g.
 * `new UseJudgeInference(options: ['temperature' => 0.7])`.
 *
 * `ToolCallingDriver` exposes no seam to change inference options after
 * `build()` (its `with()` method is private, and it has no `options()`
 * accessor), so a temperature-zero default has to be delivered here, at
 * driver construction, rather than patched onto a developer-supplied driver
 * afterward. `AgentLoopJudge` never installs this capability on the
 * developer's behalf - it is documented as the recommended judge driver,
 * not injected.
 *
 * @implements CanProvideAgentCapability<CanConfigureAgent>
 */
final readonly class UseJudgeInference implements CanProvideAgentCapability
{
    public function __construct(
        private ?LLMProvider $llm = null,
        private array $options = [],
        private int $maxRetries = 1,
    ) {}

    #[Override]
    public static function capabilityName(): string {
        return 'use_judge_inference';
    }

    #[Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $llm = $this->llm ?? LLMProvider::new();

        $retryPolicy = match (true) {
            $this->maxRetries > 1 => new InferenceRetryPolicy(maxAttempts: $this->maxRetries),
            default => null,
        };

        return $agent->withToolUseDriver(
            new ToolCallingDriver(
                llm: $llm,
                options: ['temperature' => 0.0, ...$this->options],
                retryPolicy: $retryPolicy,
                events: $agent->events(),
                inference: InferenceRuntime::fromProvider($llm, events: $agent->events()),
            ),
        );
    }
}
