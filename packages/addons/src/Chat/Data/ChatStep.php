<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data;

use Cognesy\Addons\Chat\Exceptions\ChatException;
use Cognesy\Addons\Chat\Step\HandlesChatCompletion;
use Cognesy\Addons\Chat\Step\HasChatCompletion;
use Cognesy\Addons\StepByStep\Step\Contracts\HasStepErrors;
use Cognesy\Addons\StepByStep\Step\Contracts\HasStepInfo;
use Cognesy\Addons\StepByStep\Step\Contracts\HasStepMessages;
use Cognesy\Addons\StepByStep\Step\Contracts\HasStepMetadata;
use Cognesy\Addons\StepByStep\Step\Contracts\HasStepUsage;
use Cognesy\Addons\StepByStep\Step\StepInfo;
use Cognesy\Addons\StepByStep\Step\Traits\HandlesStepErrors;
use Cognesy\Addons\StepByStep\Step\Traits\HandlesStepInfo;
use Cognesy\Addons\StepByStep\Step\Traits\HandlesStepMessages;
use Cognesy\Addons\StepByStep\Step\Traits\HandlesStepMetadata;
use Cognesy\Addons\StepByStep\Step\Traits\HandlesStepUsage;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Utils\Metadata;
use Throwable;

/**
 * @implements HasStepErrors<Throwable>
 */
final readonly class ChatStep implements
    HasChatCompletion,
    HasStepErrors,
    HasStepInfo,
    HasStepMessages,
    HasStepMetadata,
    HasStepUsage
{
    use HandlesChatCompletion;
    use HandlesStepErrors;
    use HandlesStepInfo;
    use HandlesStepMessages;
    use HandlesStepMetadata;
    use HandlesStepUsage;

    public function __construct(
        string $participantName,
        ?Messages $inputMessages = null,
        ?Messages $outputMessages = null,
        ?Usage $usage = null,
        ?InferenceResponse $inferenceResponse = null,
        ?InferenceFinishReason $finishReason = null,
        Metadata|array|null $metadata = null,
        array $errors = [],

        ?StepInfo $stepInfo = null, // for deserialization
    ) {
        $this->stepInfo = $stepInfo ?? StepInfo::new();

        $this->participantName = $participantName;
        $this->inputMessages = $inputMessages;
        $this->outputMessages = $outputMessages;
        $this->usage = $usage ?? new Usage();
        $this->inferenceResponse = $inferenceResponse;
        $this->finishReason = $finishReason;
        $this->metadata = $metadata instanceof Metadata ? $metadata : Metadata::fromArray($metadata ?? []);
        $this->errors = $this->normalizeErrors($errors);
    }

    // SERIALIZATION ////////////////////////////////////////

    public function toArray(): array {
        return [
            'participantName' => $this->participantName,
            'inputMessages' => $this->inputMessages?->toArray(),
            'outputMessages' => $this->outputMessages?->toArray(),
            'usage' => $this->usage->toArray(),
            'inferenceResponse' => $this->inferenceResponse?->toArray(),
            'finishReason' => $this->finishReason?->value,
            'meta' => $this->metadata,
            'errors' => array_map(
                static fn(Throwable $error): array => [
                    'message' => $error->getMessage(),
                    'class' => get_class($error),
                ],
                $this->errors,
            ),
            'stepInfo' => $this->stepInfo->toArray(),
        ];
    }

    public static function fromArray(array $stepData): ChatStep {
        return new ChatStep(
            participantName: $stepData['participantName'],
            inputMessages: isset($stepData['inputMessages']) ? Messages::fromArray($stepData['inputMessages']) : null,
            outputMessages: isset($stepData['outputMessages']) ? Messages::fromArray($stepData['outputMessages']) : null,
            usage: isset($stepData['usage']) ? Usage::fromArray($stepData['usage']) : null,
            inferenceResponse: isset($stepData['inferenceResponse']) ? InferenceResponse::fromArray($stepData['inferenceResponse']) : null,
            finishReason: InferenceFinishReason::tryFrom($stepData['finishReason'] ?? ''),
            metadata: $stepData['meta'] ?? [],
            errors: $stepData['errors'] ?? [],
            stepInfo: StepInfo::fromArray($stepData['stepInfo'] ?? []),
        );
    }

    public function toString() : string {
        return $this->participantName . ': ' . $this->outputMessages?->last()?->toString();
    }

    // INTERNAL /////////////////////////////////////////////////

    /** @param array<int, array{message?:string,class?:string}|Throwable> $errors */
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
                : 'Unknown error';
            $class = isset($error['class']) && is_string($error['class']) ? $error['class'] : ChatException::class;

            $normalized[] = $this->rehydrateError($class, $message);
        }

        return $normalized;
    }

    private function rehydrateError(string $class, string $message): Throwable {
        if (is_a($class, Throwable::class, true)) {
            try {
                return new $class($message);
            } catch (Throwable) {
                // fall through to default below
            }
        }
        return new ChatException($message);
    }
}
