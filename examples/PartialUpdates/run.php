<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

///--- code
use Cognesy\Instructor\Instructor;

class UserRole
{
    /** Monotonically increasing identifier */
    public int $id;
    public string $title = '';
}

class UserDetail
{
    public int $age;
    public string $name;
    public string $location;
    /** @var UserRole[] */
    public array $roles;
    /** @var string[] */
    public array $hobbies;
}

$user = (new Instructor)->request(
    messages: "Jason is 25 years old, he is an engineer and tech lead. He lives in San Francisco. He likes to play soccer and climb mountains.",
    responseModel: UserDetail::class,
)->onPartialUpdate(partialUpdate(...))->get();

function partialUpdate($partial) {
    echo chr(27).chr(91).'H'.chr(27).chr(91).'J';
    var_dump($partial);
}

assert($user->roles[0]->title == 'engineer');
assert($user->roles[1]->title == 'tech lead');
assert($user->location == 'San Francisco');
assert($user->hobbies[0] == 'soccer');
assert($user->hobbies[1] == 'climb mountains');
assert($user->age == 25);
assert($user->name == 'Jason');
