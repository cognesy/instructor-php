<?php declare(strict_types=1);

use Cognesy\Dynamic\StructureFactory;

function dynamicStructureFactoryProbe(int $id, string $name = 'default') : void {}

it('uses non-static api and reuses cached type resolver', function () {
    $method = new ReflectionMethod(StructureFactory::class, 'fromCallable');
    expect($method->isStatic())->toBeFalse();

    $factory = new StructureFactory();
    $readResolver = Closure::bind(
        static fn(StructureFactory $instance) : object => $instance->resolver,
        null,
        StructureFactory::class,
    );

    $before = $readResolver($factory);
    $factory->fromFunctionName('dynamicStructureFactoryProbe');
    $after = $readResolver($factory);

    expect($before)->toBe($after);
});
