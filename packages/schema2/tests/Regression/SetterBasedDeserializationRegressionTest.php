<?php declare(strict_types=1);

use Cognesy\Schema\Factories\SchemaFactory;

class SetterBasedUserFixture
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

it('includes private setter-backed fields in schema and computes required flags', function () {
    $schema = (new SchemaFactory())->schema(SetterBasedUserFixture::class);
    $jsonSchema = $schema->toJsonSchema();

    expect($jsonSchema['properties'])->toHaveKeys(['name', 'age', 'location', 'password']);
    expect($jsonSchema['required'] ?? [])->toContain('name');
    expect($jsonSchema['required'] ?? [])->not->toContain('age');
    expect($jsonSchema['required'] ?? [])->not->toContain('location');
    expect($jsonSchema['required'] ?? [])->not->toContain('password');
});
