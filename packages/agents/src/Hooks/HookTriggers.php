<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks;

class HookTriggers
{
    private array $triggers;

    public function __construct(HookTrigger ...$triggers) {
        $this->triggers = $triggers;
    }

    public static function none(): self {
        return new self();
    }

    public static function all(): self {
        return new self(
            HookTrigger::BeforeExecution,
            HookTrigger::BeforeStep,
            HookTrigger::BeforeToolUse,
            HookTrigger::AfterToolUse,
            HookTrigger::AfterStep,
            HookTrigger::AfterExecution,
            HookTrigger::OnError,
        );
    }

    public static function beforeExecution(): self {
        return new self(HookTrigger::BeforeExecution);
    }

    public static function beforeStep(): self {
        return new self(HookTrigger::BeforeStep);
    }

    public static function beforeToolUse(): self {
        return new self(HookTrigger::BeforeToolUse);
    }

    public static function afterToolUse(): self {
        return new self(HookTrigger::AfterToolUse);
    }

    public static function afterStep(): self {
        return new self(HookTrigger::AfterStep);
    }

    public static function afterExecution(): self {
        return new self(HookTrigger::AfterExecution);
    }

    public static function onError(): self {
        return new self(HookTrigger::OnError);
    }

    public static function with(HookTrigger ...$triggers): self {
        return new self(...$triggers);
    }

    public function triggersOn(HookTrigger $type): bool {
        foreach ($this->triggers as $trigger) {
            if ($trigger->equals($type)) {
                return true;
            }
        }
        return false;
    }
}