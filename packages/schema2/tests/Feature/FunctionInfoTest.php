<?php

use Cognesy\Schema\Exceptions\ReflectionException;
use Cognesy\Schema\Reflection\FunctionInfo;

class FunctionInfoFixture
{
    public function run(string $value, ?int $limit = null) : string {
        return $limit === null ? $value : substr($value, 0, $limit);
    }
}

it('can create FunctionInfo from function and method names', function () {
    $function = FunctionInfo::fromFunctionName('trim');
    expect($function->getName())->toBe('trim');

    $method = FunctionInfo::fromMethodName(FunctionInfoFixture::class, 'run');
    expect($method->isClassMethod())->toBeTrue();
    expect($method->hasParameter('value'))->toBeTrue();
    expect($method->isOptional('limit'))->toBeTrue();
});

it('throws domain exception for missing parameter lookup', function () {
    $method = FunctionInfo::fromMethodName(FunctionInfoFixture::class, 'run');

    expect(fn() => $method->isNullable('missing'))
        ->toThrow(ReflectionException::class, 'Parameter `missing` not found');
});
