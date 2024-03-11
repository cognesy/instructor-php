<?php

use Cognesy\Instructor\Utils\Optional;

test('it can create an Optional instance with a value', function () {
    $optional = Optional::of('value');

    $this->assertTrue($optional->exists());
    $this->assertSame('value', $optional->getOrElse('default'));
});

test('it can create an Optional instance with null', function () {
    $optional = Optional::of(null);

    $this->assertFalse($optional->exists());
    $this->assertSame('default', $optional->getOrElse('default'));
});

test('it can apply a function to the value', function () {
    $optional = Optional::of('   hello   ');

    $trimmed = $optional->apply(fn($value) => trim($value));

    $this->assertTrue($trimmed->exists());
    $this->assertSame('hello', $trimmed->getOrElse('default'));
});

test('it can chain multiple functions', function () {
    $optional = Optional::of('   hello   ');

    $lengthOfTrimmed = $optional
        ->apply(fn($value) => trim($value))
        ->apply(fn($value) => strlen($value));

    $this->assertTrue($lengthOfTrimmed->exists());
    $this->assertSame(5, $lengthOfTrimmed->getOrElse(-1));
});

test('it returns an Optional with null when applying a function to null', function () {
    $optional = Optional::of(null);

    $applied = $optional->apply(fn($value) => strlen($value));

    $this->assertFalse($applied->exists());
    $this->assertSame(0, $applied->getOrElse(0));
});