<?php

use Cognesy\Instructor\Extras\Mixin\HandlesSelfInference;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\MockHttp;
use Cognesy\Schema\Attributes\Description;

// Test classes for different extraction modes
class UserWithPublicFields
{
    public string $name;
    public int $age;
    public string $password = '';
}

class UserWithPrivateFields
{
    public string $name;
    private int $age = 0;
    private string $password = '';

    public function getAge(): int {
        return $this->age;
    }

    public function getPassword(): string {
        return $this->password;
    }
}

class UserWithConstructor
{
    public string $name;
    private ?int $age;
    private string $location;
    private string $password;

    public function __construct(
        string $name,                 // required
        int $age,                     // required in constructor
        ?string $location = null,     // optional - nullable
        string $password = '123admin' // optional - has default value
    ) {
        $this->name = $name;
        $this->age = $age;
        $this->location = $location ?? '';
        $this->password = $password;
    }

    public function getAge(): int {
        return $this->age;
    }

    public function getLocation(): string {
        return $this->location;
    }

    public function getPassword(): string {
        return $this->password;
    }
}

class UserWithSetters
{
    #[Description('Name of the user')]
    private string $name;
    #[Description('Age of the user')]
    private ?int $age;
    #[Description('Location of the user')]
    private string $location;
    #[Description('Password of the user')]
    private string $password;

    public function setName(string $name): void {
        $this->name = $name ?: 'Unknown';
    }

    public function getName(): string {
        return $this->name ?? '';
    }

    public function setAge(?int $age): void {
        $this->age = $age;
    }

    public function getAge(): int {
        return $this->age ?? 0;
    }

    public function setLocation(?string $location): void {
        $this->location = $location ?? '';
    }

    public function getLocation(): string {
        return $this->location ?? '';
    }

    public function setPassword(?string $password = ''): void {
        $this->password = $password ?: '123admin';
    }

    public function getPassword(): string {
        return $this->password ?? '';
    }
}

class UserWithMixin
{
    use HandlesSelfInference;

    public int $age;
    public string $name;
}

// Test cases
it('extracts data via public fields', function () {
    $mockHttp = MockHttp::get([
        '{"name":"Jason","age":28,"password":"secret123"}',
    ]);

    $user = (new StructuredOutput)
        ->withHttpClient($mockHttp)
        ->with(
            messages: [['role' => 'user', 'content' => 'Jason is 28 years old. His password is secret123.']],
            responseModel: UserWithPublicFields::class,
        )
        ->get();

    expect($user)->toBeInstanceOf(UserWithPublicFields::class);
    expect($user->name)->toBe('Jason');
    expect($user->age)->toBe(28);
    expect($user->password)->toBe('secret123');
});

it('handles private vs public fields correctly', function () {
    $mockHttp = MockHttp::get([
        '{"name":"Jason","age":28,"password":"secret123"}',
    ]);

    $user = (new StructuredOutput)
        ->withHttpClient($mockHttp)
        ->with(
            messages: [['role' => 'user', 'content' => 'Jason is 28 years old. His password is secret123.']],
            responseModel: UserWithPrivateFields::class,
        )
        ->get();

    expect($user)->toBeInstanceOf(UserWithPrivateFields::class);
    expect($user->name)->toBe('Jason'); // public field is hydrated
    expect($user->getAge())->toBe(0); // private field keeps default value
    expect($user->getPassword())->toBe(''); // private field keeps default value
});

it('extracts data via constructor parameters', function () {
    $mockHttp = MockHttp::get([
        '{"name":"Jason","age":28,"location":"San Francisco","password":"mypassword"}',
    ]);

    $user = (new StructuredOutput)
        ->withHttpClient($mockHttp)
        ->with(
            messages: [['role' => 'user', 'content' => 'Jason is 28 years old, lives in San Francisco. Password: mypassword.']],
            responseModel: UserWithConstructor::class,
        )
        ->get();

    expect($user)->toBeInstanceOf(UserWithConstructor::class);
    expect($user->name)->toBe('Jason');
    expect($user->getAge())->toBe(28);
    expect($user->getLocation())->toBe('San Francisco');
    expect($user->getPassword())->toBe('mypassword');
});

it('handles constructor optional parameters', function () {
    $mockHttp = MockHttp::get([
        '{"name":"Jason","age":28}', // location and password not provided
    ]);

    $user = (new StructuredOutput)
        ->withHttpClient($mockHttp)
        ->with(
            messages: [['role' => 'user', 'content' => 'Jason is 28 years old.']],
            responseModel: UserWithConstructor::class,
        )
        ->get();

    expect($user)->toBeInstanceOf(UserWithConstructor::class);
    expect($user->name)->toBe('Jason');
    expect($user->getAge())->toBe(28);
    expect($user->getLocation())->toBe(''); // null converted to empty string
    expect($user->getPassword())->toBe('123admin'); // default value used
});

it('extracts data via setter methods', function () {
    $mockHttp = MockHttp::get([
        '{"name":"Jane","age":30,"location":"New York","password":"secure456"}',
    ]);

    $user = (new StructuredOutput)
        ->withHttpClient($mockHttp)
        ->with(
            messages: [['role' => 'user', 'content' => 'Jane is 30 years old, lives in New York. Password: secure456.']],
            responseModel: UserWithSetters::class,
        )
        ->get();

    expect($user)->toBeInstanceOf(UserWithSetters::class);
    expect($user->getName())->toBe('Jane');
    expect($user->getAge())->toBe(30);
    expect($user->getLocation())->toBe('New York');
    expect($user->getPassword())->toBe('secure456');
});

it('handles setter default values and nullable parameters', function () {
    $mockHttp = MockHttp::get([
        '{"name":"","location":"Boston","password":""}', // empty name and password, no age
    ]);

    $user = (new StructuredOutput)
        ->withHttpClient($mockHttp)
        ->with(
            messages: [['role' => 'user', 'content' => 'This user lives in Boston.']],
            responseModel: UserWithSetters::class,
        )
        ->get();

    expect($user)->toBeInstanceOf(UserWithSetters::class);
    expect($user->getName())->toBe('Unknown'); // default value from setter for empty name
    expect($user->getAge())->toBe(0); // nullable, not set
    expect($user->getLocation())->toBe('Boston');
    expect($user->getPassword())->toBe('123admin'); // default value from setter for empty password
});

it('works with self-inference mixin', function () {
    $mockHttp = MockHttp::get([
        '{"name":"Alex","age":32}',
    ]);

    $user = UserWithMixin::infer(
        messages: "Alex is 32 years old and works as an engineer.",
        llm: \Cognesy\Polyglot\Inference\LLMProvider::new(),
        httpClient: $mockHttp,
    );

    expect($user)->toBeInstanceOf(UserWithMixin::class);
    expect($user->name)->toBe('Alex');
    expect($user->age)->toBe(32);
});

it('compares extraction modes with same data', function () {
    $sameJson = '{"name":"TestUser","age":25,"password":"test123"}';
    
    // Public fields - all data extracted
    $mockHttp1 = MockHttp::get([$sameJson]);
    $publicUser = (new StructuredOutput)
        ->withHttpClient($mockHttp1)
        ->with(
            messages: [['role' => 'user', 'content' => 'TestUser is 25, password test123']],
            responseModel: UserWithPublicFields::class,
        )
        ->get();
    
    // Private fields - only public name extracted
    $mockHttp2 = MockHttp::get([$sameJson]);
    $privateUser = (new StructuredOutput)
        ->withHttpClient($mockHttp2)
        ->with(
            messages: [['role' => 'user', 'content' => 'TestUser is 25, password test123']],
            responseModel: UserWithPrivateFields::class,
        )
        ->get();
    
    // Constructor - all data extracted via constructor
    $mockHttp3 = MockHttp::get([$sameJson]);
    $constructorUser = (new StructuredOutput)
        ->withHttpClient($mockHttp3)
        ->with(
            messages: [['role' => 'user', 'content' => 'TestUser is 25, password test123']],
            responseModel: UserWithConstructor::class,
        )
        ->get();
    
    // Verify differences
    expect($publicUser->name)->toBe('TestUser');
    expect($publicUser->age)->toBe(25);
    expect($publicUser->password)->toBe('test123');
    
    expect($privateUser->name)->toBe('TestUser'); // public field
    expect($privateUser->getAge())->toBe(0); // private, default value
    expect($privateUser->getPassword())->toBe(''); // private, default value
    
    expect($constructorUser->name)->toBe('TestUser');
    expect($constructorUser->getAge())->toBe(25); // via constructor
    expect($constructorUser->getPassword())->toBe('test123'); // via constructor
});