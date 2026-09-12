<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Models;

use Cognesy\Polyglot\Inference\Reasoning\ReasoningCapabilities;

final readonly class ModelCapabilities
{
    public ReasoningCapabilities $reasoning;

    public function __construct(
        public SupportStatus $streaming = SupportStatus::Unknown,
        public SupportStatus $tools = SupportStatus::Unknown,
        public SupportStatus $toolChoice = SupportStatus::Unknown,
        public SupportStatus $jsonObject = SupportStatus::Unknown,
        public SupportStatus $jsonSchema = SupportStatus::Unknown,
        public SupportStatus $responseFormatWithTools = SupportStatus::Unknown,
        ?ReasoningCapabilities $reasoning = null,
    ) {
        $this->reasoning = $reasoning ?? ReasoningCapabilities::unknown();
    }

    public static function fromArray(array $data): self {
        ModelRecordFields::validate($data, [
            'streaming', 'tools', 'toolChoice', 'jsonObject', 'jsonSchema',
            'responseFormatWithTools', 'reasoning',
        ], 'capabilities');
        return new self(
            streaming: SupportStatus::fromMixed($data['streaming'] ?? null),
            tools: SupportStatus::fromMixed($data['tools'] ?? null),
            toolChoice: SupportStatus::fromMixed($data['toolChoice'] ?? null),
            jsonObject: SupportStatus::fromMixed($data['jsonObject'] ?? null),
            jsonSchema: SupportStatus::fromMixed($data['jsonSchema'] ?? null),
            responseFormatWithTools: SupportStatus::fromMixed(
                $data['responseFormatWithTools'] ?? null,
            ),
            reasoning: ReasoningCapabilities::fromArray(ModelRecordFields::object($data, 'reasoning', 'capabilities')),
        );
    }

    /** @return array<string, string|array<string, mixed>> */
    public function toArray(): array {
        $facts = array_filter([
            'streaming' => $this->streaming->value,
            'tools' => $this->tools->value,
            'toolChoice' => $this->toolChoice->value,
            'jsonObject' => $this->jsonObject->value,
            'jsonSchema' => $this->jsonSchema->value,
            'responseFormatWithTools' => $this->responseFormatWithTools->value,
        ], static fn (string $status): bool => $status !== SupportStatus::Unknown->value);
        $reasoning = $this->reasoning->toArray();

        return [
            ...$facts,
            ...match ($reasoning) {
                [] => [],
                default => ['reasoning' => $reasoning],
            },
        ];
    }

}
