<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data;

use Cognesy\Addons\Core\StepContracts\HasStepMessages;
use Cognesy\Addons\Core\StepContracts\HasStepUsage;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;

final class ChatStep implements HasStepUsage, HasStepMessages
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

    public function __construct(
        string $participantName,
        ?Messages $inputMessages = null,
        ?Messages $outputMessages = null,
        ?Usage $usage = null,
        ?InferenceResponse $inferenceResponse = null,
        ?InferenceFinishReason $finishReason = null,
        array $meta = [],

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
            id: $stepData['id'] ?? null,
            createdAt: isset($stepData['createdAt']) ? new DateTimeImmutable($stepData['createdAt']) : null,
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

    public function toArray() : array {
        return [
            'participantName' => $this->participantName,
            'inputMessages' => $this->inputMessages?->toArray(),
            'outputMessages' => $this->outputMessages?->toArray(),
            'usage' => $this->usage?->toArray(),
            'inferenceResponse' => $this->inferenceResponse?->toArray(),
            'finishReason' => $this->finishReason->value ?? null,
            'meta' => $this->meta,
            'id' => $this->id,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
