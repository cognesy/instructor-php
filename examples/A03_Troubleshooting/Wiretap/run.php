<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

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
    public int $age;
}

$user = (new StructuredOutput)
    ->wiretap(fn($event) => $event->print())
    ->with(
        messages: [["role" => "user",  "content" => "Contact our CTO, Jason is 28 years old -- Best regards, Tom"]],
        responseModel: UserDetail::class,
        options: ['stream' => true]
    )
    ->get();

dump($user);

assert($user->name === "Jason");
assert($user->role === Role::CTO);
assert($user->age === 28);
?>
