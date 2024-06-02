<?php
namespace Tests\Feature\Extras;

use Cognesy\Instructor\Extras\Module\Addons\CallClosure\CallClosure;
use Cognesy\Instructor\Extras\Module\Addons\Predict\Predict;
use Cognesy\Instructor\Extras\Module\Core\Module;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\InputField;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\OutputField;
use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\TaskData\SignatureData;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Profiler;
use Tests\Examples\Module\TestModule;
use Tests\MockLLM;

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
    expect($addition->get('sum'))->toBe(3);

    // calculate time taken
    Profiler::summary();
});

it('can process a closure task', function() {
    $add = function(int $a, int $b) : int {
        return $a + $b;
    };
    $addition = new CallClosure($add);
    $sum = $addition->withArgs(a: 1, b: 2);

    expect($sum->result())->toBe(3);
    expect($sum->get('result'))->toBe(3);
});

it('can process predict task', function() {
    $mockLLM = MockLLM::get([
        '{"user_name": "Jason", "user_age": 28}',
    ]);

    $instructor = (new Instructor)->withClient($mockLLM);
    $predict = new Predict(
        signature: 'text (email containing user data) -> user_name, user_age:int',
        instructor: $instructor
    );
    $prediction = $predict->withArgs(text: 'Jason is 28 years old');

    expect($prediction->get())->toBe(['user_name' => 'Jason', 'user_age' => 28]);

    expect($prediction->get('user_name'))->toBe('Jason');
    expect($prediction->get('user_age'))->toBe(28);
});

it('can process predict task with multiple outputs', function() {
    $mockLLM = MockLLM::get([
        '{"topic": "sales", "sentiment": "neutral"}',
    ]);

    class EmailAnalysis2 extends SignatureData {
        #[InputField('email content')]
        public string $text;
        #[OutputField('identify most relevant email topic: sales, support, other')]
        public string $topic;
        #[OutputField('one word sentiment: positive, neutral, negative')]
        public string $sentiment;

        public static function for(string $text) : static {
            return self::fromArgs(text: $text);
        }
    }

    $instructor = (new Instructor)->withClient($mockLLM);
    $predict = new Predict(EmailAnalysis2::class, $instructor);
    $analysis = $predict->with(EmailAnalysis2::for(text: 'Can I get pricing for your business support plan?'));

    expect($analysis->get())->toMatchArray(['topic' => 'sales', 'sentiment' => 'neutral']);

    expect($analysis->get('topic'))->toBe('sales');
    expect($analysis->get('sentiment'))->toBe('neutral');
});

it('can process composite language program', function() {
    class EmailAnalysis extends SignatureData {
        #[InputField('content of email')]
        public string $text;
        #[OutputField('identify most relevant email topic: sales, support, other, spam')]
        public string $topic;
        #[OutputField('one word sentiment: positive, neutral, negative')]
        public string $sentiment;

        public static function for(string $text) : static {
            return self::fromArgs(text: $text);
        }
    }

    class CategoryCount {
        public function __construct(
            public int $sales = 0,
            public int $support = 0,
            public int $spam = 0,
            public int $other = 0,
        ) {}
    }

    class EmailStats extends SignatureData {
        #[InputField('directory containing emails')]
        public string $directory;
        #[OutputField('number of emails')]
        public int $emails;
        #[OutputField('number of spam emails')]
        public int $spam;
        #[OutputField('average sentiment ratio')]
        public float $sentimentRatio;
        #[OutputField('spam ratio')]
        public float $spamRatio;
        #[OutputField('category counts')]
        public CategoryCount $categories;

        static public function for(string $directory) : static {
            return self::fromArgs(directory: $directory);
        }
    }

    class ReadEmails extends Module {
        public function __construct(
            private array $directoryContents = []
        ) {}
        public function signature() : string|Signature {
            return 'directory -> emails';
        }
        public function forward(string $directory) : array {
            return $this->directoryContents[$directory];
        }
    }

    class ParseEmail extends Module {
        public function signature() : string|Signature {
            return 'email -> sender, body';
        }
        protected function forward(string $email) : array {
            $parts = explode(',', $email);
            return [
                'sender' => trim(explode(':', $parts[0])[1]),
                'body' => trim(explode(':', $parts[1])[1]),
            ];
        }
    }

    class GetStats extends Module {
        private ReadEmails $readEmails;
        private ParseEmail $parseEmail;
        private Predict $analyseEmail;

        public function __construct(Instructor $instructor, array $directoryContents = []) {
            $this->readEmails = new ReadEmails($directoryContents);
            $this->parseEmail = new ParseEmail();
            $this->analyseEmail = new Predict(signature: EmailAnalysis::class, instructor: $instructor);
        }

        public function signature() : string|Signature {
            return EmailStats::class;
        }

        public function forward(string $directory) : EmailStats {
            $emails = $this->readEmails->withArgs(directory: $directory)->get('emails');
            $aggregateSentiment = 0;
            $categories = new CategoryCount;
            foreach ($emails as $email) {
                $parsedEmail = $this->parseEmail->withArgs(email: $email);
                $emailAnalysis = $this->analyseEmail->with(EmailAnalysis::for($parsedEmail->get('body')));
                $topic = $emailAnalysis->get('topic');
                $sentiment = $emailAnalysis->get('sentiment');
                $topic = (in_array($topic, ['sales', 'support', 'spam'])) ? $topic : 'other';
                $categories->$topic++;
                if ($topic === 'spam') {
                    continue;
                }
                $aggregateSentiment += match($sentiment) {
                    'positive' => 1,
                    'neutral' => 0,
                    'negative' => -1,
                };
            }
            $spamRatio = $categories->spam / count($emails);
            $sentimentRatio = $aggregateSentiment / (count($emails) - $categories->spam);

            $result = new EmailStats;
            $result->emails = count($emails);
            $result->spam = $categories->spam;
            $result->sentimentRatio = $sentimentRatio;
            $result->spamRatio = $spamRatio;
            $result->categories = $categories;
            return $result;
        }
    }

    $mockLLM = MockLLM::get([
        '{"topic": "sales", "sentiment": "positive"}',
        '{"topic": "support", "sentiment": "negative"}',
        '{"topic": "spam", "sentiment": "neutral"}',
        '{"topic": "sales", "sentiment": "negative"}',
        '{"topic": "support", "sentiment": "negative"}',
    ]);

    $directoryContents['inbox'] = [
        'sender: jl@gmail.com, body: I am happy about the discount you offered and accept contract renewal',
        'sender: xxx, body: Get Ozempic for free',
        'sender: joe@wp.pl, body: My internet connection keeps failing',
        'sender: paul@x.io, body: How long do I have to wait for the pricing of custom support service?!?',
        'sender: joe@wp.pl, body: 2 weeks of waiting and still no improvement of my connection',
    ];

    $instructor = (new Instructor)->withClient($mockLLM);
    $getStats = new GetStats($instructor, $directoryContents);
    $emailStats = $getStats->with(EmailStats::for('inbox'));

    expect($emailStats->get())->toEqual([
        'emails' => 5,
        'spam' => 1,
        'sentimentRatio' => -0.5,
        'spamRatio' => 0.2,
        'categories' => new CategoryCount(2, 2, 1, 0),
    ]);
});
