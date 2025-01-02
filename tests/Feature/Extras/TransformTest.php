<?php
namespace Tests\Feature\Extras;

use Cognesy\Instructor\Experimental\Module\Modules\CallClosure;
use Cognesy\Instructor\Utils\Profiler\Profiler;
use Tests\Examples\Module\TestModule;

it('can process a simple task', function() {
    Profiler::mark('start');

    $add = new TestModule;
    $addition = $add->withArgs(numberA: 1, numberB: 2);
    Profiler::mark('fresh - requires loading classes');
    $add = new TestModule;
    $addition = $add->withArgs(numberA: 9991, numberB: 8762);
    Profiler::mark('subsequent call - classes loaded');
    $add = new TestModule;
    $addition = $add->withArgs(numberA: 13, numberB: 342);
    Profiler::mark('subsequent call - classes loaded');
    $add = new TestModule;
    $addition = $add->withArgs(numberA: 12, numberB: 22);
    Profiler::mark('subsequent call - classes loaded');
    $add = new TestModule;
    $addition = $add->withArgs(numberA: 1, numberB: 2);
    Profiler::mark('subsequent call - classes loaded');

    expect($addition->result())->toBe(3);
    expect($addition->get('result'))->toBe(3);

    // calculate time taken
    Profiler::summary();
})->skip("To be reimplemented using new module system");

it('can return example', function() {
    $add = new TestModule;
    $addition = $add->withArgs(numberA: 1, numberB: 2);

    expect($addition->asExample()->inputString())->toBe('{"numberA":1,"numberB":2}');
    expect($addition->asExample()->output())->toBe(['result' => 3]);
})->skip("To be reimplemented using new module system");

it('can process a closure task', function() {
    $add = function(int $a, int $b) : int {
        return $a + $b;
    };
    $addition = new CallClosure($add);
    $sum = $addition->for(a: 1, b: 2);

    expect($sum->result())->toBe(3);
    expect($sum->get('result'))->toBe(3);
})->skip("To be reimplemented using new module system");

//it('can process predict task', function() {
//    $mockLLM = MockLLM::get([
//        '{"user_name": "Jason", "user_age": 28}',
//    ]);
//
//    $instructor = (new Instructor)->withHttpClient($mockLLM);
//    $predict = new Transform(
//        signature: 'text (email containing user data) -> user_name, user_age:int',
//        instructor: $instructor
//    );
//    $prediction = $predict->withArgs(text: 'Jason is 28 years old');
//
//    expect($prediction->get())->toBe(['user_name' => 'Jason', 'user_age' => 28]);
//
//    expect($prediction->get('user_name'))->toBe('Jason');
//    expect($prediction->get('user_age'))->toBe(28);
//});

//it('can process predict task with multiple outputs', function() {
//    $mockLLM = MockLLM::get([
//        '{"topic": "sales", "sentiment": "neutral"}',
//    ]);
//
//    class EmailAnalysis extends SignatureData {
//        #[InputField('text of email')]
//        public string $text = '';
//        #[OutputField('identify most relevant email topic: sales, support, other')]
//        public string $topic = '';
//        #[OutputField('one word sentiment: positive, neutral, negative')]
//        public string $sentiment = '';
//    }
//
//    $predict = new Transform(
//        signature: EmailAnalysis::class,
//        instructor: (new Instructor)->withHttpClient($mockLLM)
//    );
//
//    $analysis = $predict->withArgs(
//        text: 'Can I get pricing for your business support plan?'
//    );
//    expect($analysis->get())->toMatchArray(['topic' => 'sales', 'sentiment' => 'neutral']);
//
//    expect($analysis->get('topic'))->toBe('sales');
//    expect($analysis->get('sentiment'))->toBe('neutral');
//});

//it('can process composite language program', function() {
//    $mockLLM = MockLLM::get([
//        '{"subject": "Hello", "body": "How are you?", "language": "es"}',
//        '{"translatedSubject": "Hola", "translatedBody": "¿Cómo estás?"}',
//    ]);
//
//    class Email {
//        public function __construct(
//            public string $subject = '',
//            public string $body = ''
//        ) {}
//    }
//
//    class EmailProcessingResults {
//        public function __construct(
//            public Email $original,
//            public Email $fixed,
//            public Email $translated
//        ) {}
//    }
//
//    class ProcessEmail extends Module {
//        private Transform $parse;
//        private Transform $fix;
//        private Transform $translate;
//
//        public function __construct(
//        ) {
//            $instructor = (new Instructor);//->withClient($mockLLM);
//            $this->parse = new Transform(
//                signature: 'text -> subject, body',
//                instructions: 'Parse email into subject and body',
//                instructor: $instructor
//            );
//
//            $this->fix = new Transform(
//                signature: 'subject, body -> subject, body',
//                instructions: 'Fix typos and misspellings in email subject and body',
//                instructor: $instructor
//            );
//
//            $this->translate = new Transform(
//                signature: 'subject, body, language -> translatedSubject, translatedBody',
//                instructions: 'Translate email subject and body to <|language|>',
//                instructor: $instructor
//            );
//        }
//
//        public function signature(): string|Signature {
//            return Signature::define([
//                InputField::string('text', 'email text'),
//                InputField::string('language', 'language to translate to'),
//                OutputField::object('result', EmailProcessingResults::class),
//            ]);
//        }
//
//        public function forward(string $text, string $language): EmailProcessingResults {
//            $parsedEmail = $this->parse->withArgs(text: $text)->get();
//            $fixedEmail = $this->fix->withArgs(subject: $parsedEmail['subject'], body: $parsedEmail['body'])->get();
//            $translatedEmail = $this->translate->withArgs(subject: $fixedEmail['subject'], body: $fixedEmail['body'], language: $language)->get();
//            return new EmailProcessingResults(
//                new Email($parsedEmail['subject'], $parsedEmail['body']),
//                new Email($fixedEmail['subject'], $fixedEmail['body']),
//                new Email($translatedEmail['translatedSubject'], $translatedEmail['translatedBody'])
//            );
//        }
//    }
//
//    $results = (new ProcessEmail)->withArgs(
//        text: 'sender: jl@gmail.com, subject: Ofer, body: Im hapy abut the discount you offered and accept contrac renewal',
//        language: 'Spanish',
//    );
//
//    dd($results->result());
//
//})->skip();

//class ProcessEmail extends Module {
//    private Predict $parse;
//    private Predict $fix;
//    private Predict $translate;
//
//    public function __construct() {
//        $instructor = (new Instructor);//->withClient(new AnthropicClient(Env::get('ANTHROPIC_API_KEY')));//->wiretap(fn($e) => $e->printDump());
//
//        $this->parse = new Predict(signature: ParsedEmail::class, instructor: $instructor);
//        $this->fix = new Predict(signature: FixedEmail::class, instructor: $instructor);
//        $this->translate = new Predict(signature: EmailTranslation::class, instructor: $instructor);
//    }
//
//    public function signature(): string {
//        return 'text: string, language: string -> result: EmailProcessingResults';
//    }
//
//    public function forward(string $text, string $language): EmailProcessingResults {
//        $parsedEmail = $this->parse->with(
//            ParsedEmail::fromArgs(
//                text: $text
//            )
//        )->result();
//
//        $fixedEmail = $this->fix->with(
//            FixedEmail::fromArgs(
//                subject: $parsedEmail->subject,
//                body: $parsedEmail->body
//            )
//        )->result();
//
//        $translatedEmail = $this->translate->with(
//            EmailTranslation::fromArgs(
//                subject: $fixedEmail->fixedSubject,
//                body: $fixedEmail->fixedBody,
//                language: $language
//            )
//        )->result();
//
//        return new EmailProcessingResults(
//            new Email(
//                $parsedEmail->senderEmail,
//                $parsedEmail->subject,
//                $parsedEmail->body
//            ),
//            new Email(
//                $parsedEmail->senderEmail,
//                $fixedEmail->fixedSubject,
//                $fixedEmail->fixedBody
//            ),
//            new Email(
//                $parsedEmail->senderEmail,
//                $translatedEmail->translatedSubject,
//                $translatedEmail->translatedBody
//            )
//        );
//    }
//}
//
//// EXECUTE LANGUAGE PROGRAM ///////////////////////////////////////////////////////////////
//
//$text = 'sender: jl@gmail.com, subject: Ofer, body: Im hapy abut the discount you offered and accept contrac renewal';
//$language = 'French';
//
//$result = (new ProcessEmail)->withArgs(text: $text, language: $language)->result();
