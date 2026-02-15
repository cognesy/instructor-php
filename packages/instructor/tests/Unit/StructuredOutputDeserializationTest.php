<?php declare(strict_types=1);

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Instructor\Tests\Support\FakeInferenceRequestDriver;


// 1) Public properties
class SO_PublicProps_User {
    public int $age;
    public string $name;
}

// 2) Setters only
class SO_Setter_User {
    private int $age = 0;
    private string $name = '';
    public function setAge(int $age) : void { $this->age = $age; }
    public function setName(string $name) : void { $this->name = $name; }
    public function age() : int { return $this->age; }
    public function name() : string { return $this->name; }
}

// 3) Constructor args
class SO_Constructor_User {
    private int $age;
    private string $name;
    public function __construct(string $name, int $age) { $this->name = $name; $this->age = $age; }
    public function age() : int { return $this->age; }
    public function name() : string { return $this->name; }
}

it('deserializes via public properties', function () {
    $driver = new FakeInferenceRequestDriver([
        new InferenceResponse(content: '{"name":"Alice","age":30}')
    ]);

    $user = (new StructuredOutput)
        ->withDriver($driver)
        ->with(
            messages: 'Extract user',
            responseModel: SO_PublicProps_User::class,
            mode: OutputMode::Json,
        )
        ->get();

    expect($user)->toBeInstanceOf(SO_PublicProps_User::class);
    expect($user->name)->toBe('Alice');
    expect($user->age)->toBe(30);
});

it('deserializes via setters', function () {
    $driver = new FakeInferenceRequestDriver([
        new InferenceResponse(content: '{"name":"Bob","age":28}')
    ]);

    $user = (new StructuredOutput)
        ->withDriver($driver)
        ->with(
            messages: 'Extract user',
            responseModel: SO_Setter_User::class,
            mode: OutputMode::Json,
        )
        ->get();

    expect($user)->toBeInstanceOf(SO_Setter_User::class);
    expect($user->name())->toBe('Bob');
    expect($user->age())->toBe(28);
});

it('deserializes via constructor args', function () {
    $driver = new FakeInferenceRequestDriver([
        new InferenceResponse(content: '{"name":"Cara","age":33}')
    ]);

    $user = (new StructuredOutput)
        ->withDriver($driver)
        ->with(
            messages: 'Extract user',
            responseModel: SO_Constructor_User::class,
            mode: OutputMode::Json,
        )
        ->get();

    expect($user)->toBeInstanceOf(SO_Constructor_User::class);
    expect($user->name())->toBe('Cara');
    expect($user->age())->toBe(33);
});

