# 'Structure to structure' LLM processing

Instructor provides an addon allowing to implement complex processing flows
using LLM in a modular way. This addon to Instructor has been inspired by DSPy
library for Python (https://github.com/stanfordnlp/dspy).

This example demonstrates multistep processing with LLMs:
 - parse text to extract email data from text (sender, subject and content) -> result is an object containing parsed email data
 - fix spelling mistakes in the subject and content fields -> result is an object containing fixed email subject and content
 - translate subject into specified language -> result is an object containing translated data

All the steps are packaged into a single, reusable module, which is easy to call via:

```
(new ProcessEmail)->withArgs(
   text: $text,
   language: $language,
);
```

`ProcessEmail` inherits from a `Module`, which is a base class for Instructor modules. It returns a predefined object containing, in this case, the data from all steps of processing.

The outputs and flow can be arbitrarily shaped to the needs of specific use case (within the bounds of how Module & Predict components work).

```php
<?php

use Cognesy\Instructor\Clients\Anthropic\AnthropicClient;
use Cognesy\Instructor\Clients\Groq\GroqClient;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Extras\Module\Addons\Predict\Predict;
use Cognesy\Instructor\Extras\Module\CallData\Contracts\HasInputOutputData;
use Cognesy\Instructor\Extras\Module\CallData\Traits\AutoSignature;
use Cognesy\Instructor\Extras\Module\Core\Module;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\InputField;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\OutputField;
use Cognesy\Instructor\Extras\Module\CallData\SignatureData;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Env;

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

// DATA MODEL DECLARATIONS ////////////////////////////////////////////////////////////////

//#[Description('extract email details from text')]
class ParsedEmail extends SignatureData {
    // INPUTS
    #[InputField('text containing email')]
    public string $text;
    // OUTPUTS
    #[OutputField('email address of sender')]
    public string $senderEmail;
    #[OutputField('subject of the email')]
    public string $subject;
    #[OutputField('body of the email')]
    public string $body;
}

class FixedEmail extends SignatureData {
    // INPUTS
    #[InputField('subject of the email')]
    public string $subject;
    #[InputField('body of the email')]
    public string $body;
    // OUTPUTS
    #[OutputField('subject of the email with fixed spelling mistakes')]
    public string $fixedSubject;
    #[OutputField('body of the email with fixed spelling mistakes')]
    public string $fixedBody;
}

// Alternative way to define the class signature data without extending a class
class EmailTranslation implements HasInputOutputData, CanProvideSchema {
    use AutoSignature;
    // INPUTS
    #[InputField('subject of email')]
    public string $subject;
    #[InputField('body of email')]
    public string $body;
    #[InputField('language to translate to')]
    public string $language;
    // OUTPUTS
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
        $instructor = (new Instructor);//->withClient(new AnthropicClient(Env::get('ANTHROPIC_API_KEY')));//->wiretap(fn($e) => $e->printDump());

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
