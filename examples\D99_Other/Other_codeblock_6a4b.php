<?php
// @doctest id=codeblock_6a4b
use Symfony\Component\Validator\Constraints as Assert;

class Person {
    public string $name;
    #[Assert\PositiveOrZero]
    public int $age;
}

$text = "His name is Jason, he is -28 years old.";
$person = (new StructuredOutput)
    ->withResponseClass(Person::class)
    ->with(
        messages: [['role' => 'user', 'content' => $text]],
    )
    ->get();

// if the resulting object does not validate, Instructor throws an exception
?>
