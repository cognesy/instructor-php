<?php declare(strict_types=1);

use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Factories\JsonSchemaToSchema;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;
use Cognesy\Schema\Tests\Examples\RefsCollision\NA\User as NAUser;
use Cognesy\Schema\Tests\Examples\RefsCollision\NB\User as NBUser;
use Cognesy\Schema\Tests\Examples\RefsCollision\Root as CollisionRoot;
use Cognesy\Schema\Tests\Examples\Schema\SelfReferencingClass;
use Cognesy\Schema\Tests\Examples\Schema\SimpleClass;
use Cognesy\Schema\Tests\Examples\TransitiveRefs\Root as TransitiveRoot;
use Cognesy\Schema\Visitors\SchemaToJsonSchema;

require_once dirname(__DIR__, 4) . '/vendor/autoload.php';

bootstrapLegacySchemaAutoload();

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--write', '--check'], true)) {
    fwrite(STDERR, "Usage: php packages/schema2/tools/baseline/generate.php [--write|--check]\n");
    exit(2);
}

$fixturesDir = dirname(__DIR__, 2) . '/tests/Baseline/fixtures';
$fixtures = buildFixtures();

if ($mode === '--write') {
    writeFixtures($fixturesDir, $fixtures);
    fwrite(STDOUT, "Wrote baseline fixtures to {$fixturesDir}\n");
    exit(0);
}

$ok = checkFixtures($fixturesDir, $fixtures);
exit($ok ? 0 : 1);

function bootstrapLegacySchemaAutoload() : void {
    $root = dirname(__DIR__, 4);
    spl_autoload_register(static function (string $class) use ($root): void {
        $prefixes = [
            'Cognesy\\Schema\\Tests\\' => $root . '/packages/schema/tests/',
            'Cognesy\\Schema\\' => $root . '/packages/schema/src/',
        ];

        foreach ($prefixes as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
            return;
        }
    }, true, true);
}

/** @return array<string, mixed> */
function buildFixtures() : array {
    $factoryInline = new SchemaFactory(useObjectReferences: false);
    $factoryRefs = new SchemaFactory(useObjectReferences: true);

    $simpleInlineSchema = $factoryInline->schema(SimpleClass::class)->toJsonSchema();
    $selfRefInlineSchema = $factoryInline->schema(SelfReferencingClass::class)->toJsonSchema();

    $transitiveToolCall = renderToolCallSchema($factoryRefs, TransitiveRoot::class);
    $collisionToolCall = renderToolCallSchema($factoryRefs, CollisionRoot::class);

    $jsonSchemaWithoutClass = [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
        ],
        'required' => ['id', 'name'],
    ];
    $fromJson = (new JsonSchemaToSchema())->fromJsonSchema(
        $jsonSchemaWithoutClass,
        customName: 'shape_without_class',
        customDescription: 'Object without x-php-class',
    );

    $unionCases = [
        'int|float' => typeDetailsSnapshot('int|float'),
        'int|string' => typeDetailsSnapshot('int|string'),
        NAUser::class . '|' . NBUser::class => typeDetailsSnapshot(NAUser::class . '|' . NBUser::class),
    ];

    return [
        'schema_simple_inline' => $simpleInlineSchema,
        'schema_self_ref_inline' => $selfRefInlineSchema,
        'toolcall_transitive_defs' => $transitiveToolCall,
        'toolcall_defs_collision' => $collisionToolCall,
        'jsonschema_object_without_class' => [
            'resolved_type' => $fromJson->typeDetails()->toArray(),
            'resolved_json_schema' => $fromJson->toJsonSchema(),
        ],
        'typedetails_union_policy' => $unionCases,
    ];
}

/** @return array<string, mixed> */
function renderToolCallSchema(SchemaFactory $factory, string $className) : array {
    $builder = new ToolCallBuilder($factory);
    $schema = $factory->schema($className);
    $jsonSchema = (new SchemaToJsonSchema())->toArray($schema, $builder->onObjectRef(...));

    $toolCall = $builder->renderToolCall($jsonSchema, 'baseline_tool', 'baseline tool schema');
    return $toolCall[0] ?? [];
}

/** @return array<string, mixed> */
function typeDetailsSnapshot(string $typeSpec) : array {
    try {
        $type = TypeDetails::fromTypeName($typeSpec);
        return [
            'ok' => true,
            'type' => $type->toArray(),
            'as_string' => $type->toString(),
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'error_class' => $e::class,
            'error_message' => $e->getMessage(),
        ];
    }
}

/** @param array<string,mixed> $fixtures */
function writeFixtures(string $dir, array $fixtures) : void {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $manifest = [
        'generator' => 'packages/schema2/tools/baseline/generate.php',
        'source' => 'packages/schema (legacy baseline)',
        'generated_at' => gmdate('c'),
        'fixtures' => array_keys($fixtures),
    ];

    file_put_contents($dir . '/manifest.json', encode(normalize($manifest)) . PHP_EOL);

    foreach ($fixtures as $name => $payload) {
        file_put_contents($dir . '/' . $name . '.json', encode(normalize($payload)) . PHP_EOL);
    }
}

/** @param array<string,mixed> $fixtures */
function checkFixtures(string $dir, array $fixtures) : bool {
    $ok = true;
    foreach ($fixtures as $name => $payload) {
        $path = $dir . '/' . $name . '.json';
        $expected = encode(normalize($payload)) . PHP_EOL;
        if (!is_file($path)) {
            fwrite(STDERR, "Missing fixture: {$path}\n");
            $ok = false;
            continue;
        }
        $actual = (string) file_get_contents($path);
        if ($actual === $expected) {
            continue;
        }
        fwrite(STDERR, "Fixture mismatch: {$path}\n");
        $ok = false;
    }

    if ($ok) {
        fwrite(STDOUT, "Baseline fixtures match.\n");
    }

    return $ok;
}

function encode(mixed $value) : string {
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Failed to encode JSON fixture');
    }
    return $json;
}

function isAssoc(array $array) : bool {
    return $array !== [] && array_keys($array) !== range(0, count($array) - 1);
}

function normalize(mixed $value) : mixed {
    if (!is_array($value)) {
        return $value;
    }

    $normalized = [];
    foreach ($value as $key => $item) {
        $normalized[$key] = normalize($item);
    }

    if (!isAssoc($normalized)) {
        return $normalized;
    }

    ksort($normalized);
    return $normalized;
}
