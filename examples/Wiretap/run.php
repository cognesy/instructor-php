<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Instructor;
use Symfony\Component\Validator\Constraints as Assert;

enum Role : string {
    case CEO = 'ceo';
    case CTO = 'cto';
    case Developer = 'developer';
    case Other = 'other';
}

class UserDetail
{
    public string $name;
    public Role $role;
    #[Assert\Positive]
    public int $age;
}

$user = (new Instructor)
    ->wiretap(fn($event) => $event->print())
    ->respond(
        messages: [["role" => "user",  "content" => "Contact our CTO, Jason is -28 years old -- Tom"]],
        responseModel: UserDetail::class,
        maxRetries: 2,
    )
;

assert($user->role == null);
