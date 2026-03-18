<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Envelope;

use Cognesy\Telemetry\Domain\Trace\TraceContext;

final readonly class TelemetryEnvelope
{
    public const KEY = 'telemetry';

    /**
     * @param list<string> $tags
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private OperationDescriptor $operation,
        private OperationCorrelation $correlation,
        private ?TraceContext $trace = null,
        private ?CapturePolicy $capture = null,
        private ?OperationIO $io = null,
        private array $tags = [],
        private array $metadata = [],
    ) {}

    public function operation(): OperationDescriptor
    {
        return $this->operation;
    }

    public function correlation(): OperationCorrelation
    {
        return $this->correlation;
    }

    public function trace(): ?TraceContext
    {
        return $this->trace;
    }

    public function capture(): ?CapturePolicy
    {
        return $this->capture;
    }

    public function io(): ?OperationIO
    {
        return $this->io;
    }

    /** @return list<string> */
    public function tags(): array
    {
        return $this->tags;
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function withTrace(?TraceContext $trace): self
    {
        return new self($this->operation, $this->correlation, $trace, $this->capture, $this->io, $this->tags, $this->metadata);
    }

    public function withCapture(?CapturePolicy $capture): self
    {
        return new self($this->operation, $this->correlation, $this->trace, $capture, $this->io, $this->tags, $this->metadata);
    }

    public function withIO(?OperationIO $io): self
    {
        return new self($this->operation, $this->correlation, $this->trace, $this->capture, $io, $this->tags, $this->metadata);
    }

    /** @param list<string> $tags */
    public function withTags(array $tags): self
    {
        return new self($this->operation, $this->correlation, $this->trace, $this->capture, $this->io, $tags, $this->metadata);
    }

    /** @param array<string, mixed> $metadata */
    public function withMetadata(array $metadata): self
    {
        return new self($this->operation, $this->correlation, $this->trace, $this->capture, $this->io, $this->tags, $metadata);
    }

    /**
     * @return array{
     *   operation: array{id: string, type: string, name: string, kind: string},
     *   correlation: array{root_operation_id: string, parent_operation_id?: string, session_id?: string, user_id?: string, conversation_id?: string, request_id?: string},
     *   trace?: array{traceparent: string, tracestate?: string},
     *   capture?: array{input: string, output: string, metadata: string},
     *   io?: array{input: mixed, output: mixed},
     *   tags?: list<string>,
     *   metadata?: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        $data = [
            'operation' => $this->operation->toArray(),
            'correlation' => $this->correlation->toArray(),
        ];

        $data = match ($this->trace) {
            null => $data,
            default => [...$data, 'trace' => $this->trace->toArray()],
        };
        $data = match ($this->capture) {
            null => $data,
            default => [...$data, 'capture' => $this->capture->toArray()],
        };
        $data = match ($this->io) {
            null => $data,
            default => [...$data, 'io' => $this->io->toArray()],
        };
        $data = match ($this->tags) {
            [] => $data,
            default => [...$data, 'tags' => $this->tags],
        };

        return match ($this->metadata) {
            [] => $data,
            default => [...$data, 'metadata' => $this->metadata],
        };
    }

    /**
     * @param array{
     *   operation: array{id: string, type: string, name: string, kind: string},
     *   correlation: array{root_operation_id: string, parent_operation_id?: string, session_id?: string, user_id?: string, conversation_id?: string, request_id?: string},
     *   trace?: array{traceparent: string, tracestate?: string},
     *   capture?: array{input?: string, output?: string, metadata?: string},
     *   io?: array{input?: mixed, output?: mixed},
     *   tags?: list<string>,
     *   metadata?: array<string, mixed>
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            operation: OperationDescriptor::fromArray($data['operation']),
            correlation: OperationCorrelation::fromArray($data['correlation']),
            trace: isset($data['trace']) ? TraceContext::fromArray($data['trace']) : null,
            capture: isset($data['capture']) ? CapturePolicy::fromArray($data['capture']) : null,
            io: isset($data['io']) ? OperationIO::fromArray($data['io']) : null,
            tags: $data['tags'] ?? [],
            metadata: $data['metadata'] ?? [],
        );
    }
}
