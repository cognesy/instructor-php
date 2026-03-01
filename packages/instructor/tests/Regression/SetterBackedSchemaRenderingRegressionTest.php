<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer;

final class SetterBackedSchemaFixture
{
    private string $name;
    private ?int $age;
    private string $location;
    private string $password;

    public function setName(string $name) : void {
        $this->name = $name;
    }

    public function setAge(int $age) : void {
        $this->age = $age;
    }

    public function setLocation(?string $location) : void {
        $this->location = $location ?? '';
    }

    public function setPassword(?string $password = '') : void {
        $this->password = $password ?? '';
    }
}

it('renders non-empty tool parameters for setter-backed private fields', function () {
    $renderer = new StructuredOutputSchemaRenderer(new StructuredOutputConfig());
    $schema = $renderer->schemaFactory()->schema(SetterBackedSchemaFixture::class);
    $rendering = $renderer->renderFromSchema($schema);

    $parameters = $rendering->toolCallSchema()[0]['function']['parameters'];
    $properties = $parameters['properties'] ?? [];
    $required = $parameters['required'] ?? [];

    expect($parameters['type'] ?? null)->toBe('object');
    expect($properties)->toHaveKeys(['name', 'age', 'location', 'password']);
    expect($required)->toContain('name');
    expect($required)->not->toContain('age');
    expect($required)->not->toContain('location');
    expect($required)->not->toContain('password');
});
