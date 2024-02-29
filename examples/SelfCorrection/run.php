<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Instructor;
use Symfony\Component\Validator\Constraints as Assert;

class UserDetails
{
    public string $name;
    #[Assert\Email]
    public string $email;
}

$user = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => "you can reply to me via jason@gmailcom -- Jason"]],
    responseModel: UserDetails::class,
    maxRetries: 2
);

assert($user->email === "jason@gmail.com");
dump($user);
