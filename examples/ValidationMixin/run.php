<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Traits\ValidationMixin;

class UserDetails
{
    use ValidationMixin;

    public string $name;
    public int $age;

    public function validate() : array {
        if ($this->name === strtoupper($this->name)) {
            return [];
        }
        return [[
            'message' => "Name must be in uppercase.",
            'path' => 'name',
            'value' => $this->name
        ]];
    }
}

$user = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => 'jason is 25 years old']],
    responseModel: UserDetails::class,
    maxRetries: 2
);

assert($user->name === "JASON");

dump($user);
