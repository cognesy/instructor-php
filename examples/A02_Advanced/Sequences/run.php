<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;

class Person
{
    public string $name;
    public int $age;
}

$text = <<<TEXT
    Jason is 25 years old. Jane is 18 yo. John is 30 years old. Anna is 2 years younger than him.
    TEXT;

print("INPUT:\n$text\n\n");

print("OUTPUT:\n");
$list = (new StructuredOutput)
    ->onSequenceUpdate(fn($sequence) => dump($sequence->last()))
    //->wiretap(fn($e) => $e->print())
    ->with(
        messages: $text,
        responseModel: Sequence::of(Person::class),
        options: ['stream' => true],
    )
    ->get();


dump(count($list));

assert(count($list) === 4);
?>
