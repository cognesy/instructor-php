<?php
// @doctest id=codeblock_c169
use Cognesy\Instructor\StructuredOutput;
use Symfony\Component\Validator\Constraints as Assert;

class Person {
    #[Assert\Length(min: 3)]
    public string $name;
    #[Assert\PositiveOrZero]
    public int $age;
}

$text = "His name is JX, aka Jason, he is -28 years old.";
$person = (new StructuredOutput)
    ->with(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
        maxRetries: 3,
    )
    ->get();

// if all LLM's attempts to self-correct the results fail, Instructor throws an exception
?>
