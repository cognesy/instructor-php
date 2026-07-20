<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanHandleToolSelection;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer;
use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Events\ResponseModel\ResponseModelBuildModeSelected;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\SchemaFactory;
use Cognesy\Schema\TypeInfo;

/**
 * C1 verification (research/v2-cleanup-plan/02): golden snapshots of the
 * ResponseModel produced for every supported input shape. Doubles as
 * compatibility evidence — any change to schema derivation, naming, or
 * dispatch mode shows up as a fixture diff.
 *
 * Regenerate intentionally with:
 *   RESPONSE_MODEL_SNAPSHOT_UPDATE=1 vendor/bin/pest .../ResponseModelGoldenSnapshotTest.php
 */

class SnapshotUser
{
    public string $name = '';
    public int $age = 0;
}

class SnapshotToolSelector implements CanHandleToolSelection
{
    public function toJsonSchema(): array {
        return [
            'type' => 'object',
            'properties' => ['choice' => ['type' => 'string']],
            'required' => ['choice'],
        ];
    }

    public function toSchema(): Schema {
        return SchemaFactory::default()->schema(SnapshotUser::class);
    }

    public function toToolDefinitions(): ToolDefinitions {
        return ToolDefinitions::empty();
    }
}

function snapshotFactory(array &$modes): ResponseModelFactory {
    $config = new StructuredOutputConfig();
    $events = new EventDispatcher();
    $events->addListener(
        ResponseModelBuildModeSelected::class,
        static function (ResponseModelBuildModeSelected $event) use (&$modes): void {
            $modes[] = $event->data['mode'] ?? '?';
        },
    );
    return new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
        $events,
    );
}

function responseModelSnapshot(mixed $input): array {
    $modes = [];
    $factory = snapshotFactory($modes);
    $model = $factory->fromAny($input);

    return canonicalizeResponseModelSnapshot([
        'dispatchMode' => $modes[0] ?? 'none',
        'schemaClass' => TypeInfo::className($model->schema()->type) ?? '',
        'targetClass' => $model->outputFormat()->targetClass(),
        'schemaName' => $model->schemaName(),
        'outputFormat' => $model->outputFormat()->type->value,
        'jsonSchema' => $model->toJsonSchema(),
    ]);
}

function canonicalizeResponseModelSnapshot(array $snapshot): array {
    canonicalizeSchemaDefaultValueReflectionNoise($snapshot);
    return $snapshot;
}

function canonicalizeSchemaDefaultValueReflectionNoise(array &$node): void {
    if (($node['x-php-class'] ?? null) === Schema::class) {
        unset($node['properties']['defaultValue']['type']);
    }

    foreach ($node as &$value) {
        if (is_array($value)) {
            canonicalizeSchemaDefaultValueReflectionNoise($value);
        }
    }
}

it('locks ResponseModel output per input shape against the golden fixture', function () {
    $jsonSchemaArray = [
        'type' => 'object',
        'name' => 'custom_thing',
        'description' => 'A custom thing',
        'properties' => [
            'title' => ['type' => 'string'],
            'count' => ['type' => 'integer'],
        ],
        'required' => ['title'],
    ];

    $actual = [
        'class-string' => responseModelSnapshot(SnapshotUser::class),
        'instance' => responseModelSnapshot(new SnapshotUser()),
        'json-schema-array' => responseModelSnapshot($jsonSchemaArray),
        'schema-object' => responseModelSnapshot(SchemaFactory::default()->schema(SnapshotUser::class)),
        'json-schema-provider' => responseModelSnapshot(Scalar::integer('age')),
        'schema-provider' => responseModelSnapshot(Sequence::of(SnapshotUser::class)),
        'tool-selection-provider' => responseModelSnapshot(new SnapshotToolSelector()),
    ];

    $path = __DIR__ . '/Fixtures/response-model-snapshots.json';

    if (getenv('RESPONSE_MODEL_SNAPSHOT_UPDATE') === '1' || !file_exists($path)) {
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        expect(file_exists($path))->toBeTrue();
        return;
    }

    $expected = canonicalizeResponseModelSnapshot(json_decode(file_get_contents($path), true));
    expect($actual)->toBe(
        $expected,
        'ResponseModel snapshot drift. If intentional: rerun with RESPONSE_MODEL_SNAPSHOT_UPDATE=1, review the diff, commit.',
    );
});
