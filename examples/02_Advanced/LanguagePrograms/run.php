# Language programs

```php
<?php

use Cognesy\Instructor\Extras\Tasks\Signature\Attributes\InputField;
use Cognesy\Instructor\Extras\Tasks\Signature\Attributes\OutputField;
use Cognesy\Instructor\Extras\Tasks\Signature\AutoSignature;
use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Tasks\Task\ExecutableTask;
use Cognesy\Instructor\Extras\Tasks\Task\Predict;
use Cognesy\Instructor\Instructor;

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

// DATA MODEL DECLARATIONS ////////////////////////////////////////////////////////////////

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

// TASK DECLARATIONS ////////////////////////////////////////////////////////////////

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
        $sender = trim(explode(':', $parts[0])[1]);
        $body = trim(explode(':', $parts[1])[1]);
        return [
            'sender' => $sender,
            'body' => $body,
        ];
    }
}

class GetStats extends ExecutableTask {
    private ReadEmails $readEmails;
    private ParseEmail $parseEmail;
    private Predict $analyseEmail;

    public function __construct(Instructor $instructor, array $directoryContents = []) {
        parent::__construct();

        $this->readEmails = new ReadEmails($directoryContents);
        $this->parseEmail = new ParseEmail();
        $this->analyseEmail = new Predict(signature: EmailAnalysis::class, instructor: $instructor);
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

// EXECUTION ////////////////////////////////////////////////////////////////

$directoryContents['inbox'] = [
    'sender: jl@gmail.com, body: I am happy about the discount you offered and accept contract renewal',
    'sender: xxx, body: FREE! Get Ozempic and Viagra for free',
    'sender: joe@wp.pl, body: My internet connection keeps failing',
    'sender: paul@x.io, body: How long do I have to wait for the pricing of custom support service?!?',
    'sender: joe@wp.pl, body: 2 weeks of waiting and still no improvement of my connection',
];

$instructor = (new Instructor)->wiretap(fn($e)=>$e->print());
$getStats = new GetStats($instructor, $directoryContents);
$result = $getStats->with(EmailStats::for(directory: 'inbox'));

dump($result);
