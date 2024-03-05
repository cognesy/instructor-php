<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Traits\ValidationMixin;

class Property
{
    /**  Monotonically increasing ID */
    public string $index;
    public string $key;
    public string $value;
}

class UserDetail
{
    use ValidationMixin;

    public int $age;
    public string $name;
    /** @var Property[] List other extracted properties - not more than 2. */
    public array $properties;

    public function validate() : array
    {
        if (count($this->properties) < 3) {
            return [];
        }
        return [[
            'message' => "Number of properties must be less than 3.",
            'path' => 'properties',
            'value' => $this->name
        ]];
    }
}

$text = "Jason is 25 years old. He is a programmer. He has a car. He lives in a small house in Alamo. He likes to play guitar.";

$user = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: UserDetail::class,
    //maxRetries: 2
);

assert($user->age === 25);
assert($user->name === "Jason");
assert(count($user->properties) < 3);
dump($user);
