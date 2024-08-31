<?php

use Cognesy\Instructor\Container\Container;
use Tests\Examples\Container\TestComponentA;
use Tests\Examples\Container\TestComponentB;

test('returns the same configuration instance', function () {
    $firstInstance = Container::instance();
    $secondInstance = Container::instance();
    expect($firstInstance)->toBe($secondInstance);
});

test('supports declaration and retrieval of components', function () {
    $config = Container::instance();
    $config->object(TestComponentA::class, 'someName');
    $retrievedInstance = $config->get('someName');
    expect($retrievedInstance)->toBeInstanceOf(TestComponentA::class);
});

test('it can register and resolve a component', function () {
    $config = Container::fresh();
    $config->object(TestComponentA::class);

    $component = $config->get(TestComponentA::class);

    expect($component)->toBeInstanceOf(TestComponentA::class);
});

test('it can inject context values into a component', function () {
    $config = Container::fresh();
    $config->object(TestComponentA::class, context: ['value' => 42]);

    $component = $config->get(TestComponentA::class);

    expect($component->value)->toBe(42);
});

test('it can reference a component', function () {
    $config = Container::fresh();
    $config->object(TestComponentA::class);

    $componentRef = $config->reference(TestComponentA::class);
    $component = $componentRef();

    expect($component)->toBeInstanceOf(TestComponentA::class);
});

test('it can override components for testing', function () {
    $config = Container::fresh();
    $config->object(TestComponentA::class);
    $config->override([TestComponentA::class => new TestComponentB()]);

    $component = $config->get(TestComponentA::class);

    expect($component)->toBeInstanceOf(TestComponentB::class);
});

test('it can use a factory method to create instances', function () {
    $config = Container::fresh();
    $config->object(TestComponentA::class, getInstance: function () {
        return new TestComponentA(42);
    });

    $component = $config->get(TestComponentA::class);

    expect($component->value)->toBe(42);
});

test('it can register a component with a custom name', function () {
    $config = Container::fresh();
    $config->object(TestComponentA::class, 'custom_name');

    $component = $config->get('custom_name');

    expect($component)->toBeInstanceOf(TestComponentA::class);
});


test('it detects dependency cycles', function () {
    $config = Container::instance();
    // Setup a dependency cycle
    $config->object(TestComponentA::class, 'A', ['B' => $config->reference('B')]);
    $config->object(TestComponentB::class, 'B', ['A' => $config->reference('A')]);
    $config->get('A');
})->throws(Exception::class, 'Dependency cycle detected for [A]');
