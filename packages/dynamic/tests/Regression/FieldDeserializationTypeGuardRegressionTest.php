<?php declare(strict_types=1);

use Cognesy\Dynamic\Field;
use Cognesy\Dynamic\Structure;

if (!class_exists(DynamicDeserializationTarget::class, false)) {
    class DynamicDeserializationTarget
    {
        public string $name = '';
    }
}

it('throws clear exception for non-array nested structure input', function () {
    $structure = Structure::define('Root', [
        Field::structure('child', [
            Field::string('name'),
        ]),
    ]);

    expect(fn() => $structure->fromArray(['child' => 'not-array']))
        ->toThrow(Exception::class, 'Structure field `child` expects array input');
});

it('throws clear exception for non-array object input', function () {
    $structure = Structure::define('Root', [
        Field::object('target', DynamicDeserializationTarget::class),
    ]);

    expect(fn() => $structure->fromArray(['target' => 'not-array']))
        ->toThrow(Exception::class, 'expects array input');
});
