<?php
namespace Tests\Feature\Extras;

use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Tasks\Signature\Attributes\InputField;
use Cognesy\Instructor\Extras\Tasks\Signature\Attributes\OutputField;
use Cognesy\Instructor\Extras\Tasks\Signature\AutoSignature;
use Cognesy\Instructor\Extras\Tasks\Task\ExecutableTask;
use Cognesy\Instructor\Extras\Tasks\Task\PredictTask;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Profiler;
use Tests\Examples\Task\TestTask;
use Tests\MockLLM;

it('can process a simple task', function() {
    Profiler::mark('start');
    $add = new TestTask;
    $result = $add->withArgs(numberA: 1, numberB: 2);
    Profiler::mark('end');

    expect($result)->toBe(3);
//    expect($add->input('numberA'))->toBe(1);
//    expect($add->input('numberB'))->toBe(2);
//    expect($add->output('sum'))->toBe(3);

    // calculate time taken
    Profiler::summary();
});

it('can process predict task', function() {
    $mockLLM = MockLLM::get([
        '{"user_name": "Jason", "user_age":28}',
    ]);

    $instructor = (new Instructor)->withClient($mockLLM);
    $predict = new PredictTask('text (email containing user data) -> user_name, user_age:int', $instructor);
    $result = $predict->withArgs(text: 'Jason is 28 years old');

    expect($result->toArray())->toBe(['user_name' => 'Jason', 'user_age' => 28]);

//    expect($predict->input('text'))->toBe('Jason is 28 years old');
//    expect($predict->output('user_name'))->toBe('Jason');
//    expect($predict->output('user_age'))->toBe(28);
});

it('can process predict task with multiple outputs', function() {
    $mockLLM = MockLLM::get([
        '{"topic": "sales", "sentiment": "neutral"}',
    ]);

    class EmailAnalysis2 extends AutoSignature {
        #[InputField('email content')]
        public string $text;
        #[OutputField('identify most relevant email topic: sales, support, other')]
        public string $topic;
        #[OutputField('one word sentiment: positive, neutral, negative')]
        public string $sentiment;

        public static function for(string $text) : static {
            return self::make(text: $text);
        }
    }

    $instructor = (new Instructor)->withClient($mockLLM);
    $task = new PredictTask(EmailAnalysis2::class, $instructor);
    $result = $task->with(EmailAnalysis2::for(text: 'Can I get pricing for your business support plan?'));

    expect($result->toArray())->toBe(['topic' => 'sales', 'sentiment' => 'neutral']);

//    expect($task->input('text'))->toBe('Can I get pricing for your business support plan?');
//    expect($task->output('topic'))->toBe('sales');
//    expect($task->output('sentiment'))->toBe('neutral');
});

it('can process composite language program', function() {
    class EmailAnalysis extends AutoSignature {
        #[InputField('content of email')]
        public string $text;
        #[OutputField('identify most relevant email topic: sales, support, other, spam')]
        public string $topic;
        #[OutputField('one word sentiment: positive, neutral, negative')]
        public string $sentiment;

        public static function for(string $text) : static {
            return self::make(text: $text);
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

    class EmailStats extends AutoSignature {
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
            return self::make(directory: $directory);
        }
    }

    class ReadEmails extends ExecutableTask {
        public function __construct(private array $directoryContents = []) {
            parent::__construct();
        }
        public function signature() : string|Signature {
            return 'directory -> emails';
        }
        public function forward(string $directory) : array {
            return $this->directoryContents[$directory];
        }
    }

    class ParseEmail extends ExecutableTask {
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

    class GetStats extends ExecutableTask {
        private ReadEmails $readEmails;
        private ParseEmail $parseEmail;
        private PredictTask $analyseEmail;

        public function __construct(Instructor $instructor, array $directoryContents = []) {
            parent::__construct();

            $this->readEmails = new ReadEmails($directoryContents);
            $this->parseEmail = new ParseEmail();
            $this->analyseEmail = new PredictTask(signature: EmailAnalysis::class, instructor: $instructor);
        }

        public function signature() : string|Signature {
            return EmailStats::class;
        }

        public function forward(string $directory) : array {
            $emails = $this->readEmails->withArgs(directory: $directory);
            $aggregateSentiment = 0;
            $categories = new CategoryCount;
            foreach ($emails as $email) {
                $parsedEmail = $this->parseEmail->withArgs(email: $email);
                $result = $this->analyseEmail->with(EmailAnalysis::for($parsedEmail['body']));
                $topic = (in_array($result->topic, ['sales', 'support', 'spam'])) ? $result->topic : 'other';
                $categories->$topic++;
                if ($topic === 'spam') {
                    continue;
                }
                $aggregateSentiment += match($result->sentiment) {
                    'positive' => 1,
                    'neutral' => 0,
                    'negative' => -1,
                };
            }
            $spamRatio = $categories->spam / count($emails);
            $sentimentRatio = $aggregateSentiment / (count($emails) - $categories->spam);
            return [
                'emails' => count($emails),
                'spam' => $categories->spam,
                'sentimentRatio' => $sentimentRatio,
                'spamRatio' => $spamRatio,
                'categories' => $categories,
            ];
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
    $task = new GetStats($instructor, $directoryContents);
    $result = $task->with(EmailStats::for('inbox'));

    expect($result)->toEqual([
        'emails' => 5,
        'spam' => 1,
        'sentimentRatio' => -0.5,
        'spamRatio' => 0.2,
        'categories' => new CategoryCount(2, 2, 1, 0),
    ]);
});
