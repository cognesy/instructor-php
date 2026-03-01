<?php declare(strict_types=1);

use Cognesy\Schema\Reflection\PropertyInfo;

final class PropertyInfoSetterVoidReturnTypeRegressionSubject
{
    private string $name = '';

    public function setName(string $name) : void {
        $this->name = $name;
    }
}

final class PropertyInfoSetterIntersectionReturnTypeRegressionSubject
{
    private string $name = '';

    public function setName(string $name) : IteratorAggregate&Countable {
        $this->name = $name;
        return new class implements IteratorAggregate, Countable {
            public function getIterator() : Traversable {
                return new ArrayIterator([]);
            }

            public function count() : int {
                return 0;
            }
        };
    }
}

it('treats void setter as deserializable', function () {
    $propertyInfo = PropertyInfo::fromName(PropertyInfoSetterVoidReturnTypeRegressionSubject::class, 'name');

    expect($propertyInfo->isDeserializable())->toBeTrue();
});

it('rejects setter with non-void intersection return type', function () {
    $propertyInfo = PropertyInfo::fromName(PropertyInfoSetterIntersectionReturnTypeRegressionSubject::class, 'name');

    expect($propertyInfo->isDeserializable())->toBeFalse();
});

