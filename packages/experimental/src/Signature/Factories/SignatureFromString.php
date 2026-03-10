<?php declare(strict_types=1);

namespace Cognesy\Experimental\Signature\Factories;

use Cognesy\Experimental\Signature\Signature;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\SchemaFactory;
use Cognesy\Schema\TypeInfo;
use InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;

class SignatureFromString
{
    public function make(string $signatureString, ?string $description = null): Signature {
        if ($signatureString === '') {
            throw new InvalidArgumentException('Invalid signature string, empty string');
        }

        if (!str_contains($signatureString, Signature::ARROW)) {
            throw new InvalidArgumentException('Invalid signature string, missing arrow -> marker separating inputs and outputs');
        }

        $normalizedSignature = $this->normalizeSignatureString($signatureString);
        [$inputs, $outputs] = explode('>', $normalizedSignature);

        return new Signature(
            input: $this->schemaFromString('inputs', $inputs),
            output: $this->schemaFromString('outputs', $outputs),
            description: $description ?? '',
        );
    }

    private function normalizeSignatureString(string $signatureString) : string {
        return Pipeline::builder()
            ->through(fn(string $str) => trim($str))
            ->through(fn(string $str) => str_replace("\n", ' ', $str))
            ->through(fn(string $str) => str_replace(Signature::ARROW, '>', $str))
            ->executeWith(ProcessingState::with($signatureString))
            ->value();
    }

    private function schemaFromString(string $name, string $typeString) : Schema {
        $schemaFactory = SchemaFactory::default();
        $trimmed = trim($typeString);
        $source = str_starts_with($trimmed, 'array{') && str_ends_with($trimmed, '}')
            ? substr($trimmed, 6, -1)
            : $trimmed;

        $properties = [];
        $required = [];

        foreach (explode(',', $source) as $part) {
            $normalized = trim($part);
            if ($normalized === '') {
                continue;
            }

            [$fieldName, $fieldType, $fieldDescription] = $this->parseStringField($normalized);
            if ($fieldName === '') {
                continue;
            }

            $properties[$fieldName] = $schemaFactory->fromType(
                type: TypeInfo::fromTypeName($fieldType, normalize: false),
                name: $fieldName,
                description: $fieldDescription,
            );
            $required[] = $fieldName;
        }

        return new ObjectSchema(
            type: Type::object(\stdClass::class),
            name: $name,
            description: '',
            properties: $properties,
            required: $required,
        );
    }

    /** @return array{string, string, string} */
    private function parseStringField(string $definition) : array {
        $description = '';
        if (preg_match('/\((.*?)\)\s*$/', $definition, $matches) === 1) {
            $description = trim($matches[1]);
            $definition = trim((string) preg_replace('/\s*\(.*?\)\s*$/', '', $definition));
        }

        $chunks = explode(':', str_replace(' ', '', $definition));

        return [
            trim($chunks[0] ?? ''),
            trim($chunks[1] ?? 'string'),
            $description,
        ];
    }
}
