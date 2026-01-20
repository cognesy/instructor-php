<?php
require 'examples/boot.php';

use Cognesy\Events\Event;
use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Validation\Traits\ValidationMixin;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Schema\Attributes\Description;
use Cognesy\Utils\Str;

class UserDetails
{
    use ValidationMixin;

    #[Description('User name extracted from the text.')]
    public string $name;
    #[Description('User details in format: key=value.')]
    /** @var string[]  */
    public array $details;

    public function validate() : ValidationResult {
        $details = $this->requireDetails();
        return match($details->isValid()) {
            true => $this->requireNoPII(),
            false => $details,
        };
    }

    private function requireDetails() : ValidationResult {
        $data = implode('\n', $this->details);
        return match(trim($data) === '') {
            true => ValidationResult::fieldError(
                field: 'details',
                value: $data,
                message: "Provide at least one detail in key=value format.",
            ),
            false => ValidationResult::valid(),
        };
    }

    private function requireNoPII() : ValidationResult {
        return match($this->hasPII()) {
            true => ValidationResult::fieldError(
                field: 'details',
                value: implode('\n', $this->details),
                message: "Details contain sensitive PII (phone, SSN, email). Remove only sensitive identifiers."
            ),
            false => ValidationResult::valid(),
        };
    }

    private function hasPII() : bool {
        $data = implode('\n', $this->details);
        $prompt = <<<TEXT
Determine if the context contains sensitive PII such as phone numbers, SSNs, credit card numbers, or email addresses.
Ignore names, ages, and job titles for this check.
TEXT;
        return match($data === '') {
            true => false,
            false => (new StructuredOutput)
                ->with(
                    messages: "Context:\n$data\n\n$prompt",
                    responseModel: Scalar::boolean('hasSensitivePII', 'Does the context contain sensitive PII?'),
                )
                ->getBoolean(),
        };
    }
}

$text = <<<TEXT
    My name is Jason. I am is 25 years old. I am developer.
    My phone number is +1 123 34 45 and social security number is 123-45-6789
    TEXT;

$user = (new StructuredOutput)
    ->wiretap(fn(Event $e) => $e->print()) // let's check the internals of Instructor processing
    ->with(
        messages: $text,
        responseModel: UserDetails::class,
        maxRetries: 2
    )->get();

dump($user);

assert($user->details !== []);
assert(!Str::contains(implode('\n', $user->details), '123-45-6789'));
?>
