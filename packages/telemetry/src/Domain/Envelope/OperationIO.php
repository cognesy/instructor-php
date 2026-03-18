<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Envelope;

final readonly class OperationIO
{
    public function __construct(
        private mixed $input = null,
        private mixed $output = null,
    ) {}

    public function input(): mixed
    {
        return $this->input;
    }

    public function output(): mixed
    {
        return $this->output;
    }

    /** @return array{input: mixed, output: mixed} */
    public function toArray(): array
    {
        return ['input' => $this->input, 'output' => $this->output];
    }

    /** @param array{input?: mixed, output?: mixed} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            input: $data['input'] ?? null,
            output: $data['output'] ?? null,
        );
    }
}
