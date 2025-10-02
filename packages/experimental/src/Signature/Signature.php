<?php declare(strict_types=1);

namespace Cognesy\Experimental\Signature;

use Cognesy\Dynamic\Structure;
use Cognesy\Dynamic\StructureFactory;
use Cognesy\Experimental\Signature\Contracts\HasInputSchema;
use Cognesy\Experimental\Signature\Contracts\HasOutputSchema;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Factories\JsonSchemaToSchema;

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
        $this->shortSignature = $this->makeShortSignatureString();
        $this->fullSignature = $this->makeSignatureString();
    }

    static public function toStructure(string $name, Signature $signature) : Structure {
        return StructureFactory::fromSchema($name, $signature->toOutputSchema(), $signature->getDescription());
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
        return (count($this->outputNames()) == 1);
    }

    public function hasArrayOutput(): bool {
        $outputs = $this->outputNames();
        $firstOutput = $outputs[0] ?? null;
        return (count($outputs) == 1)
            && (
                $this->output->isArray()
                || $this->output->getPropertySchema($firstOutput)->isArray()
            );
    }

    public function hasObjectOutput(): bool {
        $outputs = $this->outputNames();
        $firstOutput = $outputs[0] ?? null;
        return (count($outputs) == 1)
            && (
                $this->output->isObject()
                || $this->output->getPropertySchema($firstOutput)->isObject()
            );
    }

    public function hasEnumOutput(): bool {
        $outputs = $this->outputNames();
        $firstOutput = $outputs[0] ?? null;
        return (count($outputs) == 1)
            && (
                $this->output->isEnum()
                || $this->output->getPropertySchema($firstOutput)->isEnum()
            );
    }

    public function hasScalarOutput(): bool {
        $outputs = $this->outputNames();
        $firstOutput = $outputs[0] ?? null;
        return (count($outputs) == 1)
            && (
                $this->output->isScalar()
                || $this->output->getPropertySchema($firstOutput)->isScalar()
            );
    }

    public function hasTextOutput(): bool {
        $outputs = $this->outputNames();
        $firstOutput = $outputs[0] ?? null;
        return $this->hasScalarOutput()
            && (
                $this->output->typeDetails()->isString()
                || $this->output->getPropertySchema($firstOutput)->typeDetails()->isString()
            );
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
        return [
            'shortSignature' => $this->shortSignature,
            'fullSignature' => $this->fullSignature,
            'description' => $this->description,
            'input' => $this->input->toJsonSchema(),
            'output' => $this->output->toJsonSchema(),
        ];
    }

    public static function fromJsonData(array $data) : static {
        $converter = new JsonSchemaToSchema;
        return new static(
            $converter->fromJsonSchema($data['input']),
            $converter->fromJsonSchema($data['output']),
            $data['description'] ?? '',
        );
    }

    // INTERNAL ////////////////////////////////////////////////////////

    protected function makeShortSignatureString() : string {
        return $this->renderSignature($this->shortPropertySignature(...));
    }

    protected function makeSignatureString() : string {
        return $this->renderSignature($this->propertySignature(...));
    }

    private function renderSignature(callable $nameRenderer) : string {
        $inputs = $this->mapProperties($this->input->getPropertySchemas(), $nameRenderer);
        $outputs = $this->mapProperties($this->output->getPropertySchemas(), $nameRenderer);
        return implode('', [
            implode(', ', $inputs),
            (" " . Signature::ARROW . " "),
            implode(', ', $outputs)
        ]);
    }

    private function mapProperties(array $properties, callable $nameRenderer) : array {
        return array_map(
            fn(Schema $propertySchema) => $nameRenderer($propertySchema),
            $properties
        );
    }

    private function propertySignature(Schema $schema) : string {
        $description = '';
        if (!empty($schema->description())) {
            $description = ' (' . $schema->description() . ')';
        }
        return $schema->name() . ':' . $schema->typeDetails()->toString() . $description;
    }

    private function shortPropertySignature(Schema $schema) : string {
        return $schema->name();
    }
}
