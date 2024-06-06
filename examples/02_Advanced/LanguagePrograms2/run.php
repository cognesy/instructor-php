# Language programs

Instructor provides an addon allowing to implement complex processing flows
using LLM in a modular way. This addon to Instructor has been inspired by DSPy
library for Python (https://github.com/stanfordnlp/dspy).

Key components of language program:
- Module subclasses - encapsulate processing logic
- Signatures - define input and output for data processed by modules

NOTE: Other concepts from DSPy (optimizer, compiler, evaluator) have not been implemented yet.

Module consists of 3 key parts:
- __construct() - initialization of module, prepare dependencies, setup submodules
- signature() - define input and output for data processed by module
- forward() - processing logic, return output data

`Predict` class is a special module, that uses Instructor's structured processing
capabilities to execute inference on provided inputs and return output in a requested
format.

```php
<?php

use Cognesy\Instructor\Extras\Module\Addons\Predict\Predict;
use Cognesy\Instructor\Extras\Module\Core\Module;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\InputField;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\OutputField;
use Cognesy\Instructor\Extras\Module\CallData\SignatureData;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Schema\Attributes\Description;

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

// DATA MODEL DECLARATIONS ////////////////////////////////////////////////////////////////

//#[Description('extract email details from text')]
class ParsedEmail extends SignatureData {
    #[InputField('text containing email')]
    public string $text;

    #[OutputField('email address of sender')]
    public string $senderEmail;
    #[OutputField('subject of the email')]
    public string $subject;
    #[OutputField('body of the email')]
    public string $body;
}

class FixedEmail extends SignatureData {
    #[InputField('subject of the email')]
    public string $subject;
    #[InputField('body of the email')]
    public string $body;

    #[OutputField('subject of the email with fixed spelling mistakes')]
    public string $fixedSubject;
    #[OutputField('body of the email with fixed spelling mistakes')]
    public string $fixedBody;
}

class EmailTranslation extends SignatureData {
    #[InputField('subject of email')]
    public string $subject;
    #[InputField('body of email')]
    public string $body;
    #[InputField('language to translate to')]
    public string $language;

    #[OutputField('translated subject of email')]
    public string $translatedSubject;
    #[OutputField('translated body of email')]
    public string $translatedBody;
}

class Email {
    public function __construct(
        public string $senderEmail,
        public string $subject,
        public string $body
    ) {}
}

class EmailProcessingResults {
    public function __construct(
        public Email $original,
        public Email $fixed,
        public Email $translated
    ) {}
}

// MODULE DECLARATIONS ////////////////////////////////////////////////////////////////////

class ProcessEmail extends Module {
    private Predict $parse;
    private Predict $fix;
    private Predict $translate;

    public function __construct() {
        $instructor = new Instructor();

        $this->parse = new Predict(signature: ParsedEmail::class, instructor: $instructor);
        $this->fix = new Predict(signature: FixedEmail::class, instructor: $instructor);
        $this->translate = new Predict(signature: EmailTranslation::class, instructor: $instructor);
    }

    public function signature(): string {
        return 'text: string, language: string -> result: EmailProcessingResults';
    }

    public function forward(string $text, string $language): EmailProcessingResults {
        $parsedEmail = $this->parse->with(
            ParsedEmail::fromArgs(
                text: $text
            )
        )->result();

        $fixedEmail = $this->fix->with(
            FixedEmail::fromArgs(
                subject: $parsedEmail->subject,
                body: $parsedEmail->body
            )
        )->result();

        $translatedEmail = $this->translate->with(
            EmailTranslation::fromArgs(
                subject: $fixedEmail->fixedSubject,
                body: $fixedEmail->fixedBody,
                language: $language
            )
        )->result();

        return new EmailProcessingResults(
            new Email(
                $parsedEmail->senderEmail,
                $parsedEmail->subject,
                $parsedEmail->body
            ),
            new Email(
                $parsedEmail->senderEmail,
                $fixedEmail->fixedSubject,
                $fixedEmail->fixedBody
            ),
            new Email(
                $parsedEmail->senderEmail,
                $translatedEmail->translatedSubject,
                $translatedEmail->translatedBody
            )
        );
    }
}

// EXECUTE LANGUAGE PROGRAM ///////////////////////////////////////////////////////////////

$text = 'sender: jl@gmail.com, subject: Ofer, body: Im hapy abut the discount you offered and accept contrac renewal';
$language = 'French';

$result = (new ProcessEmail)->withArgs(text: $text, language: $language)->result();

echo "Results:\n";
dump($result);
?>
```
