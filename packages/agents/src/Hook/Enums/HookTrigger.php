<?php declare(strict_types=1);

namespace Cognesy\Agents\Hook\Enums;

enum HookTrigger: string
{
    case BeforeExecution = 'before_execution';
    case BeforeStep = 'before_step';
    case BeforeInferenceRequest = 'before_inference_request';
    case AfterInferenceResponse = 'after_inference_response';
    case BeforeToolUse = 'before_tool_use';
    case AfterToolUse = 'after_tool_use';
    case AfterStep = 'after_step';
    case OnStop = 'on_stop';

    case AfterExecution = 'after_execution';
    case OnError = 'on_error';

    public function equals(HookTrigger $type): bool {
        return $this === $type;
    }

    /** @return list<string> */
    public function mutableFields(): array {
        return match ($this) {
            self::BeforeExecution,
            self::BeforeStep,
            self::AfterStep,
            self::OnStop,
            self::AfterExecution => ['state', 'metadata'],
            self::BeforeInferenceRequest => ['state', 'inferenceRequest', 'metadata'],
            self::AfterInferenceResponse => ['state', 'inferenceResponse', 'metadata'],
            self::BeforeToolUse => [
                'state',
                'toolCall',
                'isToolExecutionBlocked',
                'toolExecution',
                'errorList',
                'metadata',
            ],
            self::AfterToolUse => ['state', 'toolExecution', 'metadata'],
            self::OnError => ['state', 'errorList', 'metadata'],
        };
    }
}
