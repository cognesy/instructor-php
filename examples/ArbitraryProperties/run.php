<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Instructor;

class Property
{
    public string $key;
    public string $value;
}

class UserDetail
{
    public int $age;
    public string $name;
    /** @var Property[] Extract any other properties that might be relevant */
    public array $properties;
}

$text = "Jason is 25 years old. He is a programmer. He has a car. He lives in a small house in Alamo. He likes to play guitar.";

$user = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: UserDetail::class
);

assert($user->age === 25);
assert($user->name === "Jason");
assert(!empty($user->properties));
dump($user);