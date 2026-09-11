<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Message;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\Json\JsonExtractor;
use Cognesy\Utils\Profiler\TracksObjectCreation;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents a response from the LLM.
 */
final readonly class InferenceResponse
{
    use TracksObjectCreation;

    public InferenceResponseId $id;
    public DateTimeImmutable $createdAt;
    public DateTimeImmutable $updatedAt;

    private string $finishReason;

    private Message $message;
    private InferenceUsage $usage;
    private HttpResponse $responseData;

    private bool $isPartial;

    public function __construct(
        ?Message $message = null,
        string $finishReason = '',
        ?InferenceUsage $usage = null,
        ?HttpResponse $responseData = null,
        bool $isPartial = false,
        //
        ?InferenceResponseId $id = null, // for deserialization
        ?DateTimeImmutable $createdAt = null, // for deserialization
        ?DateTimeImmutable $updatedAt = null, // for deserialization
    ) {
        $this->id = $id ?? InferenceResponseId::generate();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? $this->createdAt;

        $this->message = $message ?? new Message(role: MessageRole::Assistant);
        if (!$this->message->isAssistant()) {
            throw new InvalidArgumentException('InferenceResponse message must have the assistant role.');
        }
        $this->finishReason = $finishReason;
        $this->responseData = $responseData ?? HttpResponse::empty();
        $this->usage = $usage ?? new InferenceUsage();

        $this->isPartial = $isPartial;
        $this->trackObjectCreation();
    }

    public static function empty() : self {
        return new self();
    }

    // ACCESSORS /////////////////////////////////////////////

    public function message(): Message {
        return $this->message;
    }

    public function usage(): InferenceUsage {
        return $this->usage;
    }

    public function finishReason(): InferenceFinishReason {
        return InferenceFinishReason::fromText($this->finishReason);
    }

    public function isPartial(): bool {
        return $this->isPartial;
    }

    public function responseData(): HttpResponse {
        return $this->responseData;
    }

    public function hasFinishReason(): bool {
        return $this->finishReason !== '';
    }

    public function findJsonData(): Json {
        $extracted = JsonExtractor::first($this->message->content()->toString());
        return match ($extracted) {
            null => Json::none(),
            default => Json::fromArray($extracted),
        };
    }

    public function findToolCallJsonData(): Json {
        $toolCalls = $this->message->toolCalls();
        return match (true) {
            $toolCalls->hasNone() => Json::fromArray([]),
            $toolCalls->hasSingle() => Json::fromArray($toolCalls->first()?->args() ?? []),
            default => Json::fromArray($toolCalls->toArray()),
        };
    }

    // MUTATORS //////////////////////////////////////////////

    public function with(
        ?Message $message = null,
        ?string $finishReason = null,
        ?InferenceUsage $usage = null,
        ?HttpResponse $responseData = null,
        ?bool $isPartial = null,
    ): self {
        return new self(
            message: $message ?? $this->message,
            finishReason: $finishReason ?? $this->finishReason,
            usage: $usage ?? $this->usage,
            responseData: $responseData ?? $this->responseData,
            isPartial: $isPartial ?? $this->isPartial,
            id: $this->id,
            createdAt: $this->createdAt,
            // Carried over, not recomputed -- see InferenceRequest::with().
            updatedAt: $this->updatedAt,
        );
    }

    public function withMessage(Message $message): self {
        return $this->with(message: $message);
    }

    /** @param array<array-key, mixed> $data */
    private static function assertCanonicalPayload(array $data): void {
        $hasFlattenedResponseData = array_key_exists('content', $data)
            || array_key_exists('reasoningContent', $data)
            || array_key_exists('toolCalls', $data);

        if ($hasFlattenedResponseData) {
            throw new InvalidArgumentException(
                'Flattened content, reasoningContent, and toolCalls fields are not supported; encode the assistant turn in message.',
            );
        }
    }

    // SERIALIZATION /////////////////////////////////////////

    public function toArray(): array {
        return [
            'message' => $this->message->toArray(),
            'finishReason' => $this->finishReason,
            'usage' => $this->usage->toArray(),
            'responseData' => $this->responseData->toArray(), // raw response data
            'isPartial' => $this->isPartial,
            //
            'id' => $this->id->toString(),
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    public static function fromArray(array $data): self {
        self::assertCanonicalPayload($data);

        $responseData = $data['responseData'] ?? null;
        $messageData = $data['message'] ?? null;
        if (!is_array($messageData)) {
            throw new InvalidArgumentException('InferenceResponse data must contain an assistant message.');
        }

        return new self(
            message: Message::fromArray($messageData),
            finishReason: $data['finishReason'] ?? '',
            usage: (isset($data['usage']) && is_array($data['usage']))
                ? InferenceUsage::fromArray($data['usage'])
                : null,
            responseData: (is_array($responseData) && $responseData !== [])
                ? HttpResponse::fromArray($responseData)
                : null,
            isPartial: $data['isPartial'] ?? false,
            //
            id: isset($data['id']) ? new InferenceResponseId($data['id']) : null,
            createdAt: isset($data['createdAt']) ? new DateTimeImmutable($data['createdAt']) : null,
            updatedAt: isset($data['updatedAt']) ? new DateTimeImmutable($data['updatedAt']) : null,
        );
    }

    public function hasFinishedWithFailure() : bool {
        return InferenceFinishReason::fromText($this->finishReason)->isOneOf(
            InferenceFinishReason::Error,
            InferenceFinishReason::ContentFilter,
            InferenceFinishReason::Length,
        );
    }

}
