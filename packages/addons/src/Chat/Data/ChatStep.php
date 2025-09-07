<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;

final class ChatStep
{
    public readonly string $id;
    public readonly DateTimeImmutable $createdAt;

    private string $participantName;
    private ?Messages $inputMessages;
    private ?Message $outputMessage;
    private ?Usage $usage;
    private ?InferenceResponse $inferenceResponse;
    private ?string $finishReason;
    private array $meta;

    public function __construct(
        string $participantName,
        ?Messages $inputMessages = null,
        ?Message $outputMessage = null,
        ?Usage $usage = null,
        ?InferenceResponse $inferenceResponse = null,
        ?string $finishReason = null,
        array $meta = [],

        ?string $id = null, // for deserialization
        ?DateTimeImmutable $createdAt = null, // for deserialization
    ) {
        $this->id = $id ?? Uuid::uuid4();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();

        $this->participantName = $participantName;
        $this->inputMessages = $inputMessages;
        $this->outputMessage = $outputMessage;
        $this->usage = $usage;
        $this->inferenceResponse = $inferenceResponse;
        $this->finishReason = $finishReason;
        $this->meta = $meta;
    }

    public static function fromArray(array $stepData) : ChatStep {
        return new ChatStep(
            participantName: $stepData['participantName'],
            inputMessages: isset($stepData['inputMessages']) ? Messages::fromArray($stepData['inputMessages']) : null,
            outputMessage: isset($stepData['outputMessage']) ? Message::fromArray($stepData['outputMessage']) : null,
            usage: isset($stepData['usage']) ? Usage::fromArray($stepData['usage']) : null,
            inferenceResponse: isset($stepData['inferenceResponse']) ? InferenceResponse::fromArray($stepData['inferenceResponse']) : null,
            finishReason: $stepData['finishReason'] ?? null,
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

    public function outputMessage(): Message {
        return $this->outputMessage ?? Message::empty();
    }

    public function messages(): Messages {
        $messages = $this->inputMessages ?? Messages::empty();
        if ($this->outputMessage) {
            $messages = $messages->appendMessage($this->outputMessage);
        }
        return $messages;
    }

    public function usage(): Usage {
        return $this->usage ?? new Usage();
    }

    public function inferenceResponse(): ?InferenceResponse {
        return $this->inferenceResponse;
    }

    public function finishReason(): ?string {
        return $this->finishReason;
    }

    public function meta(): array {
        return $this->meta;
    }

    public function toArray() : array {
        return [
            'participantName' => $this->participantName,
            'inputMessages' => $this->inputMessages?->toArray(),
            'outputMessage' => $this->outputMessage?->toArray(),
            'usage' => $this->usage?->toArray(),
            'inferenceResponse' => $this->inferenceResponse?->toArray(),
            'finishReason' => $this->finishReason,
            'meta' => $this->meta,
            'id' => $this->id,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}

