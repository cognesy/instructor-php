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
    /**  Monotonically increasing ID, not larger than 2 */
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
            'message' => "Number of properties must not more than 2.",
            'path' => 'properties',
            'value' => $this->name
        ]];
    }
}

$text = "Jason is 25 years old. He is a programmer. He has a car. He lives in a small house in Alamo. He likes to play guitar.";

try {
    $user = (new Instructor)->respond(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: UserDetail::class,
        maxRetries: 0 // change to 1 or 2 to reattempt generation in case of validation error
    );

    assert($user->age === 25);
    assert($user->name === "Jason");
    assert(count($user->properties) < 3);
    dump($user);
} catch (\Exception $e) {
    dump("Max retries exceeded\nMessage: {$e->getMessage()}");
}