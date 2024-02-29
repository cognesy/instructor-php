<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Instructor;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class UserDetails
{
    public string $name;
    public int $age;

    #[Assert\Callback]
    public function validateName(ExecutionContextInterface $context, mixed $payload) {
        if ($this->name !== strtoupper($this->name)) {
            $context->buildViolation("Name must be in uppercase.")
                ->atPath('name')
                ->setInvalidValue($this->name)
                ->addViolation();
        }
    }
}

$user = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => 'jason is 25 years old']],
    responseModel: UserDetails::class,
    maxRetries: 2
);

assert($user->name === "JASON");

dump($user);
