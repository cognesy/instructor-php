<?php declare(strict_types=1);

use Cognesy\Dynamic\Structure;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Transformation\Contracts\CanTransformSelf;
use Cognesy\Schema\SchemaBuilder;

final readonly class ExplicitStructureNestedValue
{
    public function __construct(
        public int $count,
    ) {}
}

it('is self-deserializing without implicitly transforming away', function () {
    $structure = Structure::fromSchema(
        SchemaBuilder::define('issue')
            ->option('status', ['open', 'closed'])
            ->schema(),
    );
    $result = $structure->fromArray(['status' => 'open']);

    expect($structure)->toBeInstanceOf(CanDeserializeSelf::class)
        ->and($structure)->not->toBeInstanceOf(CanTransformSelf::class)
        ->and($result)->toBeInstanceOf(Structure::class)
        ->and($result->toArray())->toBe(['status' => 'open']);
});

it('validates scalar option values through the shared validator', function () {
    $structure = Structure::fromSchema(
        SchemaBuilder::define('issue')
            ->option('status', ['open', 'closed'])
            ->schema(),
        ['status' => 'pending'],
    );

    expect($structure->validate()->isInvalid())->toBeTrue()
        ->and($structure->validate()->getErrors()[0]->field)->toBe('status');
});

it('keeps multi-field data inside Structure until explicit conversion', function () {
    $structure = Structure::fromSchema(
        SchemaBuilder::define('issue')
            ->string('status')
            ->int('priority')
            ->schema(),
    );
    $result = $structure->fromArray([
        'status' => 'open',
        'priority' => 2,
    ]);

    expect($result)->toBeInstanceOf(Structure::class)
        ->and($result->toArray())->toBe([
            'status' => 'open',
            'priority' => 2,
        ]);
});

it('hydrates valid nested class values and keeps them in Structure', function () {
    $structure = Structure::fromSchema(
        SchemaBuilder::define('wrapper')
            ->object('nested', ExplicitStructureNestedValue::class)
            ->schema(),
    );
    $result = $structure->fromArray([
        'nested' => ['count' => 3],
    ]);

    expect($result)->toBeInstanceOf(Structure::class)
        ->and($result->get('nested'))->toBeInstanceOf(ExplicitStructureNestedValue::class)
        ->count->toBe(3);
});
