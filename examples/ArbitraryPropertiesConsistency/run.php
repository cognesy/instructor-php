<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

///--- code
use Cognesy\Instructor\Instructor;

class UserDetail
{
    public int $id;
    public string $key;
    public string $value;
}

class UserDetails
{
    /** @var UserDetail[] Extract information for multiple users. Use consistent key names for properties across users. */
    public array $users;
}

$text = "Jason is 25 years old. He is a Python programmer. Amanda is UX designer. John is 40yo and he's CEO.";

$list = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: UserDetails::class
);

assert(!empty($list->users));
dump($list);
