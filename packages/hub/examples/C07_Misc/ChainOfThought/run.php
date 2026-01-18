<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Schema\Attributes\Instructions;

class Employee {
    #[Instructions('Think step by step to determine the correct year of employment.')]
    public string $reasoning;
    public int $yearOfEmployment;
    // ... other data fields of your employee class
}

$text = 'He was working here for 5 years. Now, in 2019, he is a manager.';

$employee = (new StructuredOutput)->with(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: Employee::class
)->get();


dump($employee);

assert($employee->yearOfEmployment === 2014);
?>
