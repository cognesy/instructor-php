<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Data;

use Cognesy\Addons\StepByStep\Step\Contracts\HasStepErrors;
use Cognesy\Addons\StepByStep\Step\Contracts\HasStepInfo;
use Cognesy\Addons\StepByStep\Step\Contracts\HasStepMessages;
use Cognesy\Addons\StepByStep\Step\Contracts\HasStepToolCalls;
use Cognesy\Addons\StepByStep\Step\Contracts\HasStepToolExecutions;
use Cognesy\Addons\StepByStep\Step\Contracts\HasStepUsage;
use Cognesy\Addons\StepByStep\Step\StepInfo;
use Cognesy\Addons\StepByStep\Step\Traits\HandlesStepErrors;
use Cognesy\Addons\StepByStep\Step\Traits\HandlesStepInfo;
use Cognesy\Addons\StepByStep\Step\Traits\HandlesStepMessages;
use Cognesy\Addons\StepByStep\Step\Traits\HandlesStepToolCalls;
use Cognesy\Addons\StepByStep\Step\Traits\HandlesStepToolExecutions;
use Cognesy\Addons\StepByStep\Step\Traits\HandlesStepUsage;
use Cognesy\Addons\ToolUse\Collections\ToolExecutions;
use Cognesy\Addons\ToolUse\Enums\ToolUseStepType;
use Cognesy\Addons\ToolUse\Exceptions\ToolExecutionException;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Throwable;

final readonly class ToolUseStep implements
    HasStepErrors,
    HasStepInfo,
    HasStepMessages,
    HasStepToolCalls,
    HasStepToolExecutions,
    HasStepUsage
{
    use HandlesStepErrors;
    use HandlesStepInfo;
    use HandlesStepMessages;
    use HandlesStepToolCalls;
    use HandlesStepToolExecutions;
    use HandlesStepUsage;

    public function __construct(
        ?Messages $inputMessages = null,
        ?Messages $outputMessages = null,
        ?Usage $usage = null,
        ?ToolCalls $toolCalls = null,
        ?ToolExecutions $toolExecutions = null,
        ?InferenceResponse $inferenceResponse = null,
        ?ToolUseStepType $stepType = null,
        array $errors = [],

        ?StepInfo $stepInfo = null, // for deserialization
    ) {
        $this->stepInfo = $stepInfo ?? StepInfo::new();

        $this->inputMessages = $inputMessages ?? Messages::empty();
        $this->outputMessages = $outputMessages ?? Messages::empty();
        $this->usage = $usage ?? Usage::none();

        $this->toolCalls = $toolCalls ?? new ToolCalls();
        $this->toolExecutions = $toolExecutions ?? new ToolExecutions();
        $this->inferenceResponse = $inferenceResponse ?? new InferenceResponse();
        $this->stepType = $stepType ?? self::inferStepType(
            $this->inferenceResponse,
            $this->toolExecutions,
        );

        $normalizedErrors = $this->normalizeErrors($errors);
        $this->errors = $normalizedErrors !== []
            ? $normalizedErrors
            : $this->toolExecutions->errors();
    }

    // SERIALIZATION ////////////////////////////////////////

    public function toArray(): array {
        return [
            'stepInfo' => $this->stepInfo->toArray(),
            'inputMessages' => $this->inputMessages->toArray(),
            'outputMessages' => $this->outputMessages->toArray(),
            'toolCalls' => $this->toolCalls->toArray(),
            'toolExecutions' => $this->toolExecutions->toArray(),
            'errors' => array_map(
                fn(Throwable $error): array => [
                    'message' => $error->getMessage(),
                    'class' => get_class($error),
                ],
                $this->errors,
            ),
            'usage' => $this->usage->toArray(),
            'inferenceResponse' => $this->inferenceResponse->toArray(),
            'stepType' => $this->stepType->value,
        ];
    }

    public static function fromArray(array $data): self {
        return new self(
            inputMessages: isset($data['inputMessages']) ? Messages::fromArray($data['inputMessages']) : Messages::empty(),
            outputMessages: isset($data['outputMessages']) ? Messages::fromArray($data['outputMessages']) : Messages::empty(),
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : null,
            toolCalls: isset($data['toolCalls']) ? ToolCalls::fromArray($data['toolCalls']) : null,
            toolExecutions: isset($data['toolExecutions']) ? ToolExecutions::fromArray($data['toolExecutions']) : null,
            inferenceResponse: isset($data['inferenceResponse']) ? InferenceResponse::fromArray($data['inferenceResponse']) : null,
            stepType: isset($data['stepType']) ? ToolUseStepType::from($data['stepType']) : null,
            errors: $data['errors'] ?? [],
            stepInfo: StepInfo::fromArray($data['stepInfo'] ?? []),
        );
    }

    public function toString(): string {
        return ($this->outputMessages->toString() ?: '(no response)')
            . ' ['
            . ($this->hasToolCalls() ? $this->toolCalls->toString() : '(-)')
            . ']';
    }

    // INTERNAL /////////////////////////////////////////////////////////

    private static function inferStepType(
        InferenceResponse $response,
        ToolExecutions $executions
    ): ToolUseStepType {
        return match (true) {
            $executions->hasErrors() => ToolUseStepType::Error,
            $response->hasToolCalls() => ToolUseStepType::ToolExecution,
            default => ToolUseStepType::FinalResponse,
        };
    }

    /**
     * @param array<int, array{message?:string,class?:string}|Throwable> $errors
     * @return Throwable[]
     */
    private function normalizeErrors(array $errors): array {
        $normalized = [];
        foreach ($errors as $error) {
            if ($error instanceof Throwable) {
                $normalized[] = $error;
                continue;
            }

            if (!is_array($error)) {
                continue;
            }

            $message = isset($error['message']) && is_string($error['message'])
                ? $error['message']
                : 'Unknown tool-use error';
            $class = isset($error['class']) && is_string($error['class'])
                ? $error['class']
                : ToolExecutionException::class;

            if (is_a($class, Throwable::class, true)) {
                try {
                    $normalized[] = new $class($message);
                    continue;
                } catch (Throwable) {
                    // fall through to default case
                }
            }

            $normalized[] = new ToolExecutionException($message);
        }

        return $normalized;
    }
}
