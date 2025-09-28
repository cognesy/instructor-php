<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Deferred\Data;

use Cognesy\Experimental\Pipeline2\Deferred\Enums\ExecutionStage;

final class ExecutionSnapshot implements \JsonSerializable
{
    public function __construct(
        public string $executionId,
        public int $index, // -1 means "before first boundary"
        public ExecutionStage $stage,
        public mixed $payload,
        public bool $isFinished = false,
    ) {}

    public static function new(
        string $executionId,
        mixed $initialPayload
    ): self {
        return new self(
            executionId: $executionId,
            index: -1,
            stage: ExecutionStage::Init,
            payload: $initialPayload,
            isFinished: false
        );
    }

    public static function progress(
        string $executionId,
        int $index,
        ExecutionStage $stage,
        mixed $payload
    ): self {
        return new self(
            executionId: $executionId,
            index: $index,
            stage: $stage,
            payload: $payload,
            isFinished: false
        );
    }

    public static function finished(string $executionId, mixed $result): self {
        return new self(
            executionId: $executionId,
            index: PHP_INT_MAX,
            stage: ExecutionStage::Terminal,
            payload: $result,
            isFinished: true
        );
    }

    public function jsonSerialize(): array {
        return $this->toArray();
    }

    public static function fromArray(array $data): self {
        $payloadValue = json_decode($data['payload'] ?? '');
        return new self(
            executionId: (string) $data['executionId'],
            index: (int) $data['index'],
            stage: ExecutionStage::from($data['stage']),
            payload: $payloadValue,
            isFinished: (bool) $data['isFinished'],
        );
    }

    public function toArray(): array {
        $payloadData = json_encode($this->payload);
        return [
            'executionId' => $this->executionId,
            'index' => $this->index,
            'stage' => $this->stage->value,
            'payload' => $payloadData,
            'isFinished' => $this->isFinished,
        ];
    }
}
