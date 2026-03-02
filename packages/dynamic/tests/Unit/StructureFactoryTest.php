<?php declare(strict_types=1);

use Cognesy\Dynamic\StructureFactory;

function dynamicStructureFactoryProbe(int $id, string $name = 'default') : void {}

it('uses non-static api and builds structure from callable signatures', function () {
    $method = new ReflectionMethod(StructureFactory::class, 'fromCallable');
    expect($method->isStatic())->toBeFalse();

    $factory = new StructureFactory();
    $structure = $factory->fromFunctionName('dynamicStructureFactoryProbe');

    expect($structure->has('id'))->toBeTrue()
        ->and($structure->has('name'))->toBeTrue();
});
