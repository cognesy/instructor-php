<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Attributes\Description;
use Cognesy\Instructor\Instructor;

class Employee {
    #[Description('Think step by step to determine the correct year of employment.')]
    public string $chainOfThought;
    public int $yearOfEmployment;
}

$text = 'He was working here for 5 years. Now, in 2019, he is a manager.';

$employee = (new Instructor)->respond(
    [['role' => 'user', 'content' => $text]],
    Employee::class
);

dump($employee);
