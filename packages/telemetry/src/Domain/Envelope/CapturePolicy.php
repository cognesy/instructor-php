<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Envelope;

final readonly class CapturePolicy
{
    public function __construct(
        private CaptureMode $input = CaptureMode::None,
        private CaptureMode $output = CaptureMode::None,
        private CaptureMode $metadata = CaptureMode::None,
    ) {}

    public static function none(): self
    {
        return new self();
    }

    public function input(): CaptureMode
    {
        return $this->input;
    }

    public function output(): CaptureMode
    {
        return $this->output;
    }

    public function metadata(): CaptureMode
    {
        return $this->metadata;
    }

    /** @return array{input: string, output: string, metadata: string} */
    public function toArray(): array
    {
        return [
            'input' => $this->input->value,
            'output' => $this->output->value,
            'metadata' => $this->metadata->value,
        ];
    }

    /** @param array{input?: string, output?: string, metadata?: string} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            input: CaptureMode::from($data['input'] ?? CaptureMode::None->value),
            output: CaptureMode::from($data['output'] ?? CaptureMode::None->value),
            metadata: CaptureMode::from($data['metadata'] ?? CaptureMode::None->value),
        );
    }
}
