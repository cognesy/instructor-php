<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data;

use Cognesy\Addons\Chat\Exceptions\ChatException;
use Cognesy\Addons\Core\StepContracts\HasStepErrors;
use Cognesy\Addons\Core\StepContracts\HasStepMessages;
use Cognesy\Addons\Core\StepContracts\HasStepUsage;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;
use Throwable;

final class ChatStep implements HasStepUsage, HasStepMessages, HasStepErrors
{
    public readonly string $id;
    public readonly DateTimeImmutable $createdAt;

    private ?Messages $inputMessages;
    private ?Messages $outputMessages;
    private ?Usage $usage;

    private string $participantName;
    private ?InferenceResponse $inferenceResponse;
    private ?InferenceFinishReason $finishReason;
    private array $meta;
    /** @var Throwable[] */
    private array $errors;

    public function __construct(
        string $participantName,
        ?Messages $inputMessages = null,
        ?Messages $outputMessages = null,
        ?Usage $usage = null,
        ?InferenceResponse $inferenceResponse = null,
        ?InferenceFinishReason $finishReason = null,
        array $meta = [],
        array $errors = [],

        ?string $id = null, // for deserialization
        ?DateTimeImmutable $createdAt = null, // for deserialization
    ) {
        $this->id = $id ?? Uuid::uuid4();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();

        $this->participantName = $participantName;
        $this->inputMessages = $inputMessages;
        $this->outputMessages = $outputMessages;
        $this->usage = $usage;
        $this->inferenceResponse = $inferenceResponse;
        $this->finishReason = $finishReason;
        $this->meta = $meta;
        $this->errors = $this->normalizeErrors($errors);
    }

    public static function fromArray(array $stepData) : ChatStep {
        return new ChatStep(
            participantName: $stepData['participantName'],
            inputMessages: isset($stepData['inputMessages']) ? Messages::fromArray($stepData['inputMessages']) : null,
            outputMessages: isset($stepData['outputMessages']) ? Messages::fromArray($stepData['outputMessages']) : null,
            usage: isset($stepData['usage']) ? Usage::fromArray($stepData['usage']) : null,
            inferenceResponse: isset($stepData['inferenceResponse']) ? InferenceResponse::fromArray($stepData['inferenceResponse']) : null,
            finishReason: InferenceFinishReason::tryFrom($stepData['finishReason'] ?? ''),
            meta: $stepData['meta'] ?? [],
            errors: $stepData['errors'] ?? [],
            id: $stepData['id'] ?? null,
            createdAt: isset($stepData['createdAt']) ? new DateTimeImmutable($stepData['createdAt']) : null,
        );
    }

    public static function failure(
        string $participantName,
        Messages $inputMessages,
        Throwable $error,
        ?Messages $outputMessages = null,
        ?array $meta = null,
    ): self {
        $baseError = $error instanceof ChatException ? $error : ChatException::fromThrowable($error);

        return new self(
            participantName: $participantName,
            inputMessages: $inputMessages,
            outputMessages: $outputMessages,
            usage: Usage::none(),
            inferenceResponse: null,
            finishReason: InferenceFinishReason::Other,
            meta: ($meta ?? []) + ['errorType' => get_class($baseError)],
            errors: [$baseError],
        );
    }

    public function participantName(): string {
        return $this->participantName;
    }

    public function inputMessages(): Messages {
        return $this->inputMessages ?? Messages::empty();
    }

    public function outputMessages(): Messages {
        return $this->outputMessages ?? Messages::empty();
    }

//    public function messages(): Messages {
//        $messages = $this->inputMessages ?? Messages::empty();
//        if ($this->outputMessage) {
//            $messages = $messages->appendMessage($this->outputMessage);
//        }
//        return $messages;
//    }

    public function usage(): Usage {
        return $this->usage ?? new Usage();
    }

    public function inferenceResponse(): ?InferenceResponse {
        return $this->inferenceResponse;
    }

    public function finishReason(): ?InferenceFinishReason {
        return $this->finishReason;
    }

    public function meta(): array {
        return $this->meta;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /** @return Throwable[] */
    public function errors(): array
    {
        return $this->errors;
    }

    public function errorsAsString(): string
    {
        if ($this->errors === []) {
            return '';
        }
        return implode("\n", array_map(
            fn(Throwable $error): string => $error->getMessage(),
            $this->errors,
        ));
    }

    public function toArray() : array {
        return [
            'participantName' => $this->participantName,
            'inputMessages' => $this->inputMessages?->toArray(),
            'outputMessages' => $this->outputMessages?->toArray(),
            'usage' => $this->usage?->toArray(),
            'inferenceResponse' => $this->inferenceResponse?->toArray(),
            'finishReason' => $this->finishReason->value ?? null,
            'meta' => $this->meta,
            'errors' => array_map(
                fn(Throwable $error): array => [
                    'message' => $error->getMessage(),
                    'class' => get_class($error),
                ],
                $this->errors,
            ),
            'id' => $this->id,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
        ];
    }

    /** @param array<int, array{message?:string,class?:string}|Throwable> $errors */
    private function normalizeErrors(array $errors): array
    {
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

    private function rehydrateError(string $class, string $message): Throwable
    {
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
