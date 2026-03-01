<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer;
use Cognesy\Instructor\Tests\Examples\ResponseModel\User;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;

class ConstructorRequiredSchemaProviderForRegression implements CanProvideJsonSchema
{
    public function __construct(private string $schemaName) {}

    public function toJsonSchema(): array {
        return [
            'x-php-class' => User::class,
            'name' => $this->schemaName,
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string'],
            ],
            'required' => ['name', 'email'],
        ];
    }
}

function responseModelFactoryForRegression(): ResponseModelFactory {
    $events = new EventDispatcher('test');
    $config = new StructuredOutputConfig();

    return new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
        $events,
    );
}

// Guards regression from instructor-iwf2 (class-string provider with ctor crashing via uninitialized props).
it('fails fast for class-string schema providers requiring constructor args', function () {
    $factory = responseModelFactoryForRegression();

    expect(fn() => $factory->fromAny(ConstructorRequiredSchemaProviderForRegression::class))
        ->toThrow(
            \InvalidArgumentException::class,
            'requires constructor arguments. Pass a provider instance instead of class-string.'
        );
});

it('keeps provider-instance flow working for providers with constructor args', function () {
    $factory = responseModelFactoryForRegression();

    $model = $factory->fromAny(new ConstructorRequiredSchemaProviderForRegression('user_schema'));

    expect($model->instanceClass())->toBe(ConstructorRequiredSchemaProviderForRegression::class);
    expect($model->returnedClass())->toBe(User::class);
    expect($model->toJsonSchema()['type'])->toBe('object');
});
