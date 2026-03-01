<?php declare(strict_types=1);

namespace Cognesy\Schema\Tests\Support;

use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\SchemaFactory;

final class ToolCallSchemaFixtureBuilder
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function render(
        SchemaFactory $schemaFactory,
        Schema $schema,
        string $name,
        string $description,
    ) : array {
        $referenceState = [];
        $onObjectRef = static function (string $className) use (&$referenceState) : void {
            if (!isset($referenceState[$className])) {
                $referenceState[$className] = false;
            }
        };

        $parameters = $schemaFactory->toJsonSchema($schema, $onObjectRef);
        $definitions = self::definitions($schemaFactory, $referenceState, $onObjectRef);
        if ($definitions !== []) {
            $parameters['$defs'] = $definitions;
        }

        return [[
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => $parameters,
            ],
        ]];
    }

    /**
     * @param array<string, bool> $referenceState
     * @return array<string, array<string, mixed>>
     */
    private static function definitions(
        SchemaFactory $schemaFactory,
        array &$referenceState,
        callable $onObjectRef,
    ) : array {
        $definitions = [];
        while (self::hasQueued($referenceState)) {
            $className = self::dequeue($referenceState);
            if ($className === null) {
                break;
            }

            $schema = $schemaFactory->schema($className);
            $definitions[self::classKey($className)] = $schemaFactory->toJsonSchema($schema, $onObjectRef);
        }

        return array_reverse($definitions);
    }

    /**
     * @param array<string, bool> $referenceState
     */
    private static function hasQueued(array $referenceState) : bool {
        foreach ($referenceState as $rendered) {
            if ($rendered === false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, bool> $referenceState
     */
    private static function dequeue(array &$referenceState) : ?string {
        foreach ($referenceState as $className => $rendered) {
            if ($rendered === false) {
                $referenceState[$className] = true;
                return $className;
            }
        }

        return null;
    }

    private static function classKey(string $className) : string {
        return str_replace('\\', '.', ltrim($className, '\\'));
    }
}
