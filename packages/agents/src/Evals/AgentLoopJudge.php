<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Closure;
use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Builder\Contracts\CanComposeAgentLoop;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\Core\UseHook;
use Cognesy\Agents\Capability\Core\UseTools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Evals\Events\JudgeGuardsNotConfigured;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Enums\HookTrigger;
use Cognesy\Agents\Profile\AgentProfile;
use Cognesy\Agents\Profile\LLMConfigProfile;
use Override;

/**
 * Agentic implementation of `CanJudgeAgentEval`. Runs an independently
 * configured, bounded `AgentLoop` that inspects the target's `AgentRun`,
 * optionally gathers evidence with developer-supplied tools, and submits a
 * validated verdict through the `submit_judgment` terminal tool. See
 * `01-architecture.md`, "AgentLoopJudge", for the full construction/execution
 * contract this class implements.
 *
 * Every `judge()` call gets a fresh builder (from the factory), a fresh loop
 * built from it, a fresh judge `AgentState`, a fresh collected-event list,
 * and a fresh `JudgeSubmissionInbox` - nothing leaks between calls, including
 * repeated calls made on the same `AgentLoopJudge` instance.
 */
final class AgentLoopJudge implements CanJudgeAgentEval
{
    private bool $guardWarningEmitted = false;
    private ?AgentProfile $lastProfile = null;

    /** @param Closure(): CanComposeAgentLoop $builderFactory */
    private function __construct(
        private readonly Closure $builderFactory,
        private readonly JudgePromptRenderer $renderer,
    ) {}

    /**
     * @param callable(): CanComposeAgentLoop $builderFactory Must return a FRESH,
     *        not-yet-built builder on every call - `AgentLoopJudge` calls it once
     *        per `judge()` invocation and adds its own tool and hook before `build()`.
     */
    public static function fromBuilder(callable $builderFactory): self {
        return new self(
            builderFactory: Closure::fromCallable($builderFactory),
            renderer: new JudgePromptRenderer(),
        );
    }

    #[Override]
    public function judge(JudgeRequest $request): JudgeScore {
        $inbox = new JudgeSubmissionInbox();

        $builder = ($this->builderFactory)()
            ->withCapability(new UseTools(new SubmitJudgmentTool($inbox)))
            ->withCapability(new UseHook(
                hook: new JudgeProtocolHook($inbox),
                triggers: HookTriggers::of(HookTrigger::BeforeToolUse, HookTrigger::AfterStep),
                name: 'judge:protocol',
            ));

        $loop = $builder->build();

        /** @var list<object> $events */
        $events = [];
        if ($loop instanceof AgentLoop) {
            $this->lastProfile = $loop->profile();
            $loop->wiretap(function (object $event) use (&$events): void {
                $events[] = $event;
            });
        }

        $this->warnIfGuardsMissing($loop);

        $state = AgentState::empty()
            ->withSystemPrompt($this->renderer->system())
            ->withUserMessage($this->renderer->user($request));

        $finalState = $loop->execute($state);

        if ($finalState->hasErrors() === true) {
            throw new JudgeProtocolException($this->failedRunMessage($finalState));
        }

        $submission = $inbox->get();
        if ($submission === null) {
            throw new JudgeProtocolException($this->missingSubmissionMessage($finalState));
        }

        return new JudgeScore(
            score: $submission->score,
            reason: $submission->reason,
            evidence: $submission->evidence,
            run: AgentRun::fromState($finalState, $events, llmProfile: $this->lastProfile?->llm),
        );
    }

    /**
     * The judge's own LLM configuration for the most recent `judge()` call,
     * mirroring `guardProfile()`: read-only, resolved from the built loop's
     * profile, never fabricated. Null before the first `judge()` call, or when
     * the builder didn't produce a concrete `AgentLoop`, or when its driver
     * never resolved an `LLMConfig`.
     */
    public function llmProfile(): ?LLMConfigProfile {
        return $this->lastProfile?->llm;
    }

    /**
     * Read-only guard provenance for the most recent `judge()` call, meant to be
     * embedded verbatim into slice 5's provenance block. Numeric guard limits
     * (maxSteps, maxTokens, ...) are NOT reachable from a built loop - `UseGuards`
     * and its guard hooks expose no accessors, and `profile()->capabilities` only
     * carries `{name, class}` - so this deliberately reports only what the
     * resolved profile actually exposes: whether `UseGuards` is configured, and
     * which `guard:*` hooks are active. It never fabricates or reflection-scrapes
     * a numeric value; an honest omission is preferable to a made-up one in a
     * durable, cross-run artifact.
     *
     * Returns `['configured' => false, 'hooks' => []]` before the first `judge()`
     * call, or when the builder didn't produce a concrete `AgentLoop`.
     *
     * @return array{configured: bool, hooks: list<string>}
     */
    public function guardProfile(): array {
        if ($this->lastProfile === null) {
            return ['configured' => false, 'hooks' => []];
        }
        return [
            'configured' => $this->hasCapability($this->lastProfile, UseGuards::capabilityName()),
            'hooks' => $this->guardHookNames($this->lastProfile),
        ];
    }

    // INTERNAL ////////////////////////////////////////////////

    /**
     * Read-only guard check: inspects the built loop's resolved profile for the
     * `UseGuards` capability by its exact `capabilityName()`, never by hook name
     * or heuristic, and dispatches a warning event when it's absent. This method
     * has no code path that installs a guard - it only ever reads the profile
     * and, at most, dispatches an event.
     */
    private function warnIfGuardsMissing(object $loop): void {
        if ($this->guardWarningEmitted || !$loop instanceof AgentLoop || $this->lastProfile === null) {
            return;
        }

        if ($this->hasCapability($this->lastProfile, UseGuards::capabilityName())) {
            return;
        }

        $this->guardWarningEmitted = true;
        $loop->eventHandler()->dispatch(new JudgeGuardsNotConfigured(
            capability: UseGuards::capabilityName(),
            suggestedFix: '->withCapability(new UseGuards(maxSteps: 8, maxTokens: 12_000))',
        ));
    }

    private function hasCapability(AgentProfile $profile, string $name): bool {
        foreach ($profile->capabilities->all() as $capability) {
            if ($capability->name === $name) {
                return true;
            }
        }
        return false;
    }

    /** @return list<string> */
    private function guardHookNames(AgentProfile $profile): array {
        $names = [];
        foreach ($profile->hooks->all() as $hookProfile) {
            if ($hookProfile->name !== null && str_starts_with($hookProfile->name, 'guard:')) {
                $names[] = $hookProfile->name;
            }
        }
        return $names;
    }

    private function failedRunMessage(AgentState $state): string {
        $errors = $state->errors()->toMessagesString();
        return sprintf(
            'AgentLoopJudge run failed: %s',
            $errors !== '' ? $errors : 'the judge agent loop reported an error.',
        );
    }

    private function missingSubmissionMessage(AgentState $state): string {
        return sprintf(
            'AgentLoopJudge ended without a submit_judgment call (status=%s, steps=%d). '
            . 'The judge agent must call submit_judgment exactly once.',
            $state->status()?->value ?? 'unknown',
            $state->stepCount(),
        );
    }
}
