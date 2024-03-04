<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Instructor;

// PROMPTING HINT: Make Instructor restate the instructions and rules to improve inference accuracy.

/**
 * Identify what kind of job the user is doing.
 * Typical roles we're working with are CEO, CTO, CFO, CMO.
 * Sometimes user does not state their role directly - you will need to make a guess, based on their description.
 */
class Role
{
    /** Restate instructions and rules, so you can correctly determine the title. */
    public string $instructions;
    /** Role description */
    public string $description;
    /* Most likely job title */
    public string $title;
}

/** Details of analyzed user. The key information we're looking for is appropriate role data. */
class UserDetail
{
    public string $name;
    public int $age;
    public Role $role;
}

$instructor = new Instructor;
$user = ($instructor)->respond(
    messages: [["role" => "user",  "content" => "I'm Jason, I'm 28 yo. I am the head of Apex Software, responsible for driving growth of our company."]],
    responseModel: UserDetail::class,
);
dump($user);
