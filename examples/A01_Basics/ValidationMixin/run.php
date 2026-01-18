<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Validation\Traits\ValidationMixin;
use Cognesy\Instructor\Validation\ValidationResult;

class UserDetails
{
    use ValidationMixin;

    public string $name;
    public int $birthYear;
    public int $graduationYear;

    public function validate() : ValidationResult {
        if ($this->graduationYear > $this->birthYear) {
            return ValidationResult::valid();
        }
        return ValidationResult::fieldError(
            field: 'graduationYear',
            value: $this->graduationYear,
            message: "Graduation year has to be bigger than birth year."
        );
    }
}

$user = (new StructuredOutput)
    ->wiretap(fn($e) => $e->print())
    ->withResponseClass(UserDetails::class)
    ->with(
        messages: [['role' => 'user', 'content' => 'Jason was born in 2000 and graduated in 18.']],
        model: 'gpt-4o-mini',
        maxRetries: 2,
    )->get();


dump($user);

assert($user->graduationYear === 2018);
?>
