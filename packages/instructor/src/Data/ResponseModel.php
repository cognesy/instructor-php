<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Schema\Data\Schema;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;

final readonly class ResponseModel implements CanProvideJsonSchema
{
    private ToolDefinitions $toolDefinitions;
    private ResponseFormat $responseFormat;
    private StructuredOutputConfig $config;

    public function __construct(
        private Schema $schema,
        private array $jsonSchema,
        private string $schemaName,
        private string $schemaDescription,
        private string $toolName,
        private string $toolDescription,
        private OutputFormat $outputFormat,
        ?ToolDefinitions $toolDefinitions = null,
        ?ResponseFormat $responseFormat = null,
        ?StructuredOutputConfig $config = null,
    ) {
        $this->toolDefinitions = $toolDefinitions ?? ToolDefinitions::empty();
        $this->responseFormat = $responseFormat ?? ResponseFormat::empty();
        $this->config = $config ?? new StructuredOutputConfig();
    }

    public function schemaName(): string
    {
        return $this->schemaName ?: ($this->schema->name() ?: 'default_schema');
    }

    public function schemaDescription(): string
    {
        return $this->schemaDescription ?: $this->config->schemaDescription();
    }

    public function schema(): Schema
    {
        return $this->schema;
    }

    public function config(): StructuredOutputConfig
    {
        return $this->config;
    }

    public function toolName(): string
    {
        return $this->toolName ?: ($this->config->toolName() ?: 'extract_data');
    }

    public function toolDescription(): string
    {
        return $this->toolDescription ?: $this->config->toolDescription();
    }

    public function outputFormat(): OutputFormat
    {
        return $this->outputFormat;
    }

    #[\Override]
    public function toJsonSchema(): array
    {
        return $this->jsonSchema;
    }

    public function toolDefinitions(): ToolDefinitions
    {
        return match ($this->config->outputMode()) {
            OutputMode::Tools => $this->toolDefinitions,
            default => ToolDefinitions::empty(),
        };
    }

    public function responseFormat(): ResponseFormat
    {
        return $this->responseFormat;
    }

    public function toolChoice(): ToolChoice
    {
        return match ($this->config->outputMode()) {
            OutputMode::Tools => ToolChoice::specific($this->toolName() ?: 'extract_data'),
            default => ToolChoice::empty(),
        };
    }
}
