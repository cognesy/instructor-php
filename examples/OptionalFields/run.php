<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Attributes\Description;
use Cognesy\Instructor\Instructor;


class Role
{
    public string $title = '';
}

class UserDetail
{
    public int $age;
    public string $name;
    public ?Role $role = null;
}

$user = (new Instructor)->respond(
    messages: [["role" => "user",  "content" => "Jason is 25 years old."]],
    responseModel: UserDetail::class,
);

assert($user->role == null);
dump($user);
