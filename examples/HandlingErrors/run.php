<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Instructor;

class UserDetail
{
    public int $age;
    public string $name;
    public ?string $role = null;
}

class MaybeUser
{
    public ?UserDetail $result = null;
    public ?string $errorMessage = '';
    public bool $error = false;

    public function get(): ?UserDetail
    {
        return $this->error ? null : $this->result;
    }
}

$user = (new Instructor)->respond(
    [['role' => 'user', 'content' => 'We don\'t know anything about this guy.']],
    MaybeUser::class
);

dump($user);
