<?php declare(strict_types=1);

namespace Cognesy\Experimental\Signature;

use Cognesy\Experimental\Signature\Internal\SignatureStringRenderer;
use Cognesy\Experimental\Signature\Contracts\HasInputSchema;
use Cognesy\Experimental\Signature\Contracts\HasOutputSchema;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\JsonSchemaParser;
use Cognesy\Schema\SchemaFactory;
use Cognesy\Schema\TypeInfo;

/**
 * Signature represents a specification of the module - its input and output schemas, and a description.
 *
 * Description parameter of the signature is a base, constant description of the module function.
 * It is used to as initial base for the optimization process, along with the input and output schemas,
 * but it never changes as a result of the optimization. This way it can be used in UI to display the
 * brief description of the module.
 *
 * Instructions parameter of the signature is a result of optimization process. It is changing as a result
 * of the optimization. It is used to provide the detailed instructions to the Large Language Model on how to
 * execute the transformation specified by the signature and explained by the description. It may become
 * a very long and complex and is not suitable for UI display.
 */
final readonly class Signature implements HasInputSchema, HasOutputSchema
{
    public const ARROW = '->';

    private Schema $input;
    private Schema $output;
    private string $description;
    private string $shortSignature;
    private string $fullSignature;

    public function __construct(
        Schema $input,
        Schema $output,
        string $description = ''
    ) {
        $this->input = $input;
        $this->output = $output;
        $this->description = $description;
        $this->shortSignature = SignatureStringRenderer::short($this->input, $this->output);
        $this->fullSignature = SignatureStringRenderer::full($this->input, $this->output);
    }

    public function toRequestedSchema(string $name = 'prediction') : Schema {
        return SchemaFactory::withMetadata(
            $this->toOutputSchema(),
            name: $name,
            description: $this->getDescription(),
        );
    }

    public function toShortSignature(): string {
        return $this->shortSignature;
    }

    public function toSignatureString(): string {
        return $this->fullSignature;
    }

    // ACCESSORS ///////////////////////////////////////////////////////

    public function getDescription(): string {
        return $this->description;
    }

    public function inputNames(): array {
        return $this->toInputSchema()->getPropertyNames();
    }

    public function outputNames(): array {
        return $this->toOutputSchema()->getPropertyNames();
    }

    public function hasSingleOutput(): bool {
        return count($this->outputNames()) === 1;
    }

    public function hasArrayOutput(): bool {
        $outputProperty = $this->singleOutputProperty();
        if ($outputProperty === null) {
            return false;
        }

        return $this->output->isArray() || $outputProperty->isArray();
    }

    public function hasObjectOutput(): bool {
        $outputProperty = $this->singleOutputProperty();
        if ($outputProperty === null) {
            return false;
        }

        return $this->output->isObject() || $outputProperty->isObject();
    }

    public function hasEnumOutput(): bool {
        $outputProperty = $this->singleOutputProperty();
        if ($outputProperty === null) {
            return false;
        }

        return $this->output->isEnum() || $outputProperty->isEnum();
    }

    public function hasScalarOutput(): bool {
        $outputProperty = $this->singleOutputProperty();
        if ($outputProperty === null) {
            return false;
        }

        return $this->output->isScalar() || $outputProperty->isScalar();
    }

    public function hasTextOutput(): bool {
        $outputProperty = $this->singleOutputProperty();
        if ($outputProperty === null || !$this->hasScalarOutput()) {
            return false;
        }

        return TypeInfo::toJsonType($this->output->type())->isString()
            || TypeInfo::toJsonType($outputProperty->type())->isString();
    }

    // CONVERSION //////////////////////////////////////////////////////

    #[\Override]
    public function toInputSchema(): Schema {
        return $this->input;
    }

    #[\Override]
    public function toOutputSchema(): Schema {
        return $this->output;
    }

    public function toSchema(): Schema {
        return $this->output;
    }

    // SERIALIZATION ///////////////////////////////////////////////////

    public function toArray() : array {
        $schemaFactory = SchemaFactory::default();

        return [
            'shortSignature' => $this->shortSignature,
            'fullSignature' => $this->fullSignature,
            'description' => $this->description,
            'input' => $schemaFactory->toJsonSchema($this->input),
            'output' => $schemaFactory->toJsonSchema($this->output),
        ];
    }

    public static function fromJsonData(array $data) : static {
        $converter = new JsonSchemaParser;
        return new static(
            $converter->fromJsonSchema($data['input']),
            $converter->fromJsonSchema($data['output']),
            $data['description'] ?? '',
        );
    }

    // INTERNAL ////////////////////////////////////////////////////////

    private function singleOutputProperty() : ?Schema {
        $outputs = $this->outputNames();
        if (count($outputs) !== 1) {
            return null;
        }

        $firstOutput = $outputs[0];
        return $this->output->getPropertySchema($firstOutput);
    }
}
