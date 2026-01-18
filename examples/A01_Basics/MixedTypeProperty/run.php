<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Schema\Attributes\Description;

class UserWithMixedTypeProperty
{
    public string $name;
    #[Description('Any extra information about the user')]
    public mixed $extraInfo;
}

$text = <<<TEXT
    Jason is 25 years old. He plays football and loves to travel.
    TEXT;


$user = (new StructuredOutput)
    ->withDebugPreset('on')
    ->withMessages($text)
    ->withResponseClass(UserWithMixedTypeProperty::class)
    ->get();

dump($user);

assert($user->name === "Jason");
assert($user->extraInfo !== ''); // not empty, but can be any type
?>
