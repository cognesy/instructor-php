<?php
namespace Tests\Feature\Extras;

use Cognesy\Instructor\Extras\Signature\Attributes\InputField;
use Cognesy\Instructor\Extras\Signature\Attributes\OutputField;
use Cognesy\Instructor\Extras\Signature\AutoSignature;
use Cognesy\Instructor\Extras\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Signature\SignatureFactory;
use Cognesy\Instructor\Extras\Task\ExecutableTask;
use Cognesy\Instructor\Extras\Task\PredictTask;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Profiler;
use Tests\Examples\Task\TestTask;
use Tests\MockLLM;

it('can process a simple task', function() {
    Profiler::mark('start');
    $task = new TestTask;
    $result = $task->with(['numberA' => 1, 'numberB' => 2])->get();
    Profiler::mark('end');

    expect($result)->toBe(3);
    expect($task->input('numberA'))->toBe(1);
    expect($task->input('numberB'))->toBe(2);
    expect($task->output('result'))->toBe(3);

    // calculate time taken
    Profiler::dump();
});

it('can process predict task', function() {
    $mockLLM = MockLLM::get([
        '{"user_name": "Jason", "user_age":28}',
    ]);
    $instructor = (new Instructor)->withClient($mockLLM);
    $task = new PredictTask('text (email containing user data) -> user_name, user_age:int', $instructor);
    $result = $task->with(['text' => 'Jason is 28 years old'])->get();
    expect($result)->toBe(['user_name' => 'Jason', 'user_age' => 28]);
});

it('can process predict task with multiple outputs', function() {
    $mockLLM = MockLLM::get([
        '{"topic": "sales", "sentiment": "neutral"}',
    ]);
    $instructor = (new Instructor)->withClient($mockLLM);

    class EmailAnalysis extends AutoSignature {
        #[InputField('email content')]
        public string $text;
        #[OutputField('identify most relevant email topic: sales, support, other')]
        public string $topic;
        #[OutputField('one word sentiment: positive, neutral, negative')]
        public string $sentiment;
    }

    $task = new PredictTask(EmailAnalysis::class, $instructor);

    $result = $task->with(['text' => 'Can I get pricing for your custom support service?'])->get();
    expect($result)->toBe(['topic' => 'sales', 'sentiment' => 'neutral']);
});

it('can process composite language program', function() {
    class ReadEmails extends ExecutableTask {
        public function __construct(private array $directoryContents = []) {
            parent::__construct(SignatureFactory::fromString('directory -> string[]'));
        }
        public function forward(string $directory) : array {
            return $this->directoryContents[$directory];
        }
    }

    class ParseEmail extends ExecutableTask {
        public function __construct() {
            parent::__construct(SignatureFactory::fromString('email -> sender, body'));
        }
        public function forward(string $email) : array {
            $parts = explode(',', $email);
            return [
                'sender' => trim(explode(':', $parts[0])[1]),
                'email_body' => trim(explode(':', $parts[1])[1])
            ];
        }
    }

    class EmailAnalysis2 extends AutoSignature {
        #[InputField('content of email')]
        public string $text;
        #[OutputField('identify most relevant email topic: sales, support, other, spam')]
        public string $topic;
        #[OutputField('one word sentiment: positive, neutral, negative')]
        public string $sentiment;
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
    }

    class GetStats extends ExecutableTask {
        private ReadEmails $readEmails;
        private ParseEmail $parseEmail;
        private PredictTask $analyseEmail;

        public function __construct(Instructor $instructor, array $directoryContents = []) {
            parent::__construct($this->signature());
            $this->readEmails = new ReadEmails($directoryContents);
            $this->parseEmail = new ParseEmail();
            $this->analyseEmail = new PredictTask(EmailAnalysis2::class, $instructor);
        }

        public function signature() : Signature {
            return SignatureFactory::fromClassMetadata(EmailStats::class);
        }

        public function forward(string $directory) : array {
            $emails = $this->readEmails->with(['directory' => $directory])->get();
            $aggregateSentiment = 0;
            $categories = new CategoryCount;
            foreach ($emails as $email) {
                $parsedEmail = $this->parseEmail->with(['email' => $email])->get();
                $result = $this->analyseEmail->with(['text' => $parsedEmail['email_body']])->get();
                $topic = (in_array($result['topic'], ['sales', 'support', 'spam'])) ? $result['topic'] : 'other';
                $categories->$topic++;
                if ($topic === 'spam') {
                    continue;
                }
                $aggregateSentiment += match($result['sentiment']) {
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
        'sender: jl@gmail.com, body: I happy about the discount you offered and accept contract renewal',
        'sender: xxx, body: Get Ozempic for free',
        'sender: joe@wp.pl, body: My internet connection keeps failing',
        'sender: paul@x.io, body: How long do I have to wait for the pricing of custom support service?!?',
        'sender: joe@wp.pl, body: 2 weeks of waiting and still no improvement of my connection',
    ];

    $instructor = (new Instructor)->withClient($mockLLM);
    $task = new GetStats($instructor, $directoryContents);
    $result = $task->with(['directory' => 'inbox'])->get();

    expect($result)->toEqual([
        'emails' => 5,
        'spam' => 1,
        'sentimentRatio' => -0.5,
        'spamRatio' => 0.2,
        'categories' => new CategoryCount(2, 2, 1, 0),
    ]);
});
