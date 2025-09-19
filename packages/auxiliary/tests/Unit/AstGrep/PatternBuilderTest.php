<?php
declare(strict_types=1);

use Cognesy\Auxiliary\AstGrep\PatternBuilder;

it('builds class instantiation patterns', function () {
    $pattern = PatternBuilder::create()
        ->classInstantiation('UserModel')
        ->build();

    expect($pattern)->toBe('new UserModel($$$)');
});

it('builds method call patterns', function () {
    $pattern = PatternBuilder::create()
        ->methodCall('execute')
        ->build();

    expect($pattern)->toBe('$OBJ->execute($$$)');
});

it('builds static method call patterns', function () {
    $pattern = PatternBuilder::create()
        ->staticMethodCall('Factory', 'create')
        ->build();

    expect($pattern)->toBe('Factory::create($$$)');
});

it('builds function call patterns', function () {
    $pattern = PatternBuilder::create()
        ->functionCall('array_map')
        ->build();

    expect($pattern)->toBe('array_map($$$)');
});

it('builds class definition patterns', function () {
    $pattern = PatternBuilder::create()
        ->classDefinition('UserController')
        ->build();

    expect($pattern)->toBe('class UserController');
});

it('builds class extends patterns', function () {
    $pattern = PatternBuilder::create()
        ->classExtends('BaseController')
        ->build();

    expect($pattern)->toBe('class $CLASS extends BaseController');
});

it('builds class implements patterns', function () {
    $pattern = PatternBuilder::create()
        ->classImplements('Serializable')
        ->build();

    expect($pattern)->toBe('class $CLASS implements Serializable');
});

it('builds trait use patterns', function () {
    $pattern = PatternBuilder::create()
        ->traitUse('LoggerTrait')
        ->build();

    expect($pattern)->toBe('use LoggerTrait;');
});

it('builds property access patterns', function () {
    $pattern = PatternBuilder::create()
        ->propertyAccess('name')
        ->build();

    expect($pattern)->toBe('$OBJ->name');
});

it('builds static property access patterns', function () {
    $pattern = PatternBuilder::create()
        ->staticPropertyAccess('Config', 'instance')
        ->build();

    expect($pattern)->toBe('Config::$instance');
});

it('builds assignment patterns', function () {
    $pattern = PatternBuilder::create()
        ->assignment('$user')
        ->build();

    expect($pattern)->toBe('$user = $VALUE');
});

it('builds array access patterns', function () {
    $pattern = PatternBuilder::create()
        ->arrayAccess()
        ->build();

    expect($pattern)->toBe('$ARRAY[$KEY]');
});

it('builds namespace patterns', function () {
    $pattern = PatternBuilder::create()
        ->namespace('App\\Models')
        ->build();

    expect($pattern)->toBe('namespace App\\Models');
});

it('builds use statement patterns', function () {
    $pattern = PatternBuilder::create()
        ->useStatement('Illuminate\\Support\\Facades\\DB')
        ->build();

    expect($pattern)->toBe('use Illuminate\\Support\\Facades\\DB');
});

it('builds return statement patterns', function () {
    $pattern = PatternBuilder::create()
        ->returnStatement()
        ->build();

    expect($pattern)->toBe('return $VALUE');
});

it('builds throw statement patterns', function () {
    $pattern = PatternBuilder::create()
        ->throwStatement()
        ->build();

    expect($pattern)->toBe('throw $EXCEPTION');
});

it('builds if statement patterns', function () {
    $pattern = PatternBuilder::create()
        ->ifStatement()
        ->build();

    expect($pattern)->toBe('if ($CONDITION)');
});

it('builds foreach loop patterns', function () {
    $pattern = PatternBuilder::create()
        ->foreachLoop()
        ->build();

    expect($pattern)->toBe('foreach ($ARRAY as $VALUE)');
});

it('builds try-catch patterns', function () {
    $pattern = PatternBuilder::create()
        ->tryCatch('RuntimeException', '$ex')
        ->build();

    expect($pattern)->toBe('try { $$$ } catch (RuntimeException $ex) { $$$ }');
});

it('allows custom patterns', function () {
    $pattern = PatternBuilder::create()
        ->custom('match ($VALUE) { $$$ }')
        ->build();

    expect($pattern)->toBe('match ($VALUE) { $$$ }');
});

it('can be cast to string', function () {
    $builder = PatternBuilder::create()->methodCall('test');

    expect((string)$builder)->toBe('$OBJ->test($$$)');
});