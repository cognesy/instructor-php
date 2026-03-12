<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Utils\Profiler\TracksObjectCreation;
use DateTimeImmutable;
use Throwable;

class InferenceAttempt
{
    use TracksObjectCreation;

    public readonly InferenceAttemptId $id;
    public readonly DateTimeImmutable $createdAt;
    public readonly DateTimeImmutable $updatedAt;

    private ?InferenceResponse $response;
    private InferenceUsage $usage;

    private array $errors;
    private ?bool $isFinalized = null;

    // CONSTRUCTORS //////////////////////////////////////////////////////

    public function __construct(
        ?InferenceResponse $response = null,
        ?InferenceUsage $usage = null,
        ?bool $isFinalized = null,
        ?array $errors = null,
        //
        ?InferenceAttemptId $id = null, // for deserialization
        ?DateTimeImmutable $createdAt = null, // for deserialization
        ?DateTimeImmutable $updatedAt = null, // for deserialization
    ) {
        $this->id = $id ?? InferenceAttemptId::generate();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? $this->createdAt;

        $this->response = $response;
        $this->usage = $usage ?? $response?->usage() ?? InferenceUsage::none();
        $this->isFinalized = $isFinalized;
        $this->errors = $errors ?? [];
        $this->trackObjectCreation();
    }

    public static function fromResponse(InferenceResponse $response) : self {
        return new self(response: $response, usage: $response->usage(), isFinalized: true);
    }

    public static function started(): self {
        return new self(usage: InferenceUsage::none(), isFinalized: false);
    }

    public static function fromFailedResponse(
        ?InferenceResponse $response = null,
        ?InferenceUsage $usage = null,
        array $errors = [],
    ) : self {
        return new self(
            response: $response,
            usage: $usage ?? $response?->usage() ?? InferenceUsage::none(),
            isFinalized: true,
            errors: $errors,
        );
    }

    // ACCESSORS /////////////////////////////////////////////////////////

    public function response(): ?InferenceResponse {
        return $this->response;
    }

    public function errors(): array {
        return $this->errors;
    }

    public function isFinalized(): bool {
        return $this->isFinalized === true;
    }

    public function hasResponse(): bool {
        return $this->response !== null;
    }

    public function hasErrors(): bool {
        return !empty($this->errors);
    }

    public function isFailed(): bool {
        if ($this->hasErrors()) {
            return true;
        }
        if ($this->response === null) {
            return false;
        }
        return $this->response->hasFinishedWithFailure();
    }

    public function usage(): InferenceUsage {
        return $this->usage;
    }

    // MUTATORS //////////////////////////////////////////////////////////

    public function with(
        ?InferenceResponse $response = null,
        ?InferenceUsage $usage = null,
        ?bool $isFinalized = null,
        ?array $errors = null
    ): self {
        return new self(
            response: $response ?? $this->response,
            usage: $usage ?? $this->usage,
            isFinalized: $isFinalized ?? $this->isFinalized,
            errors: $errors ?? $this->errors,
            id: $this->id,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withResponse(InferenceResponse $response): self {
        return $this->with(
            response: $response,
            usage: $response->usage(),
            isFinalized: true
        );
    }

    // SERIALIZATION /////////////////////////////////////////////////////

    public function toArray(): array {
        return [
            'id' => $this->id->toString(),
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
            'response' => $this->response?->toArray(),
            'usage' => $this->usage->toArray(),
            'isFinalized' => $this->isFinalized,
            'errors' => $this->errorsToStringArray($this->errors),
        ];
    }

    public static function fromArray(array $data) : self {
        $response = $data['response'] ?? null;
        return new self(
            response: (is_array($response) && $response !== [])
                ? InferenceResponse::fromArray($response)
                : null,
            usage: isset($data['usage']) && is_array($data['usage'])
                ? InferenceUsage::fromArray($data['usage'])
                : null,
            isFinalized: $data['isFinalized'] ?? null,
            errors: $data['errors'] ?? [],
            id: isset($data['id']) ? new InferenceAttemptId($data['id']) : null,
            createdAt: isset($data['createdAt']) ? new DateTimeImmutable($data['createdAt']) : null,
            updatedAt: isset($data['updatedAt']) ? new DateTimeImmutable($data['updatedAt']) : null,
        );
    }

    // PRIVATE HELPERS ////////////////////////////////////////////////////

    private function errorsToStringArray(array $errors) : array {
        return array_map(fn($e) => $e instanceof Throwable ? $e->getMessage() : (string) $e, $errors);
    }

}
