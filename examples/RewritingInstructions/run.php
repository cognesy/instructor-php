<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

///--- code
use Cognesy\Instructor\Instructor;

// PROMPTING HINT: Make Instructor rewrite the instructions and rules to improve LLM inference results.

/**
 * Identify what kind of job the user is doing.
 * Typical roles we're working with are CEO, CTO, CFO, CMO.
 * Sometimes user does not state their role directly - you will need to make a guess, based on their description.
 */
class Role
{
    /** Rewrite the instructions and rules in a concise form to correctly determine the user's title - just the essence. */
    public string $instructions;
    /** Role description */
    public string $description;
    /** Most likely job title */
    public string $title;
}

class UserDetail
{
    public string $name;
    public int $age;
    public Role $role;
}

$instructor = new Instructor;
$user = $instructor->respond(
    messages: [["role" => "user",  "content" => "I'm Jason, I'm 28 yo. I am responsible for driving growth of our company."]],
    responseModel: UserDetail::class,
);
dump($user);
