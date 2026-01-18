<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Validation\Exceptions\ValidationException;
use Symfony\Component\Validator\Constraints as Assert;

class UserDetails
{
    public string $name;
    #[Assert\Email]
    #[Assert\NotBlank]
    /** Find user's email provided in the text or empty if it is missing */
    public ?string $email;
}

$caughtException = false;
try {
    $user = (new StructuredOutput)
        ->withResponseClass(UserDetails::class)
        ->withMessages([['role' => 'user', 'content' => "you can reply to me via mail -- Jason"]])
        ->get();
} catch (ValidationException $e) {
    $caughtException = true;
    echo "Validation worked.\n";
} catch (Throwable $e) {
    // Catch any other exception
    echo "Validation failed with unexpected exception: {$e->getMessage()}\n";
}

assert($caughtException === true);
assert(!isset($user));
?>
