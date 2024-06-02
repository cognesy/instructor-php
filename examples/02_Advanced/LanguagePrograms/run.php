# Language programs

```php
<?php

use Cognesy\Instructor\Extras\Module\Addons\Predict\Predict;
use Cognesy\Instructor\Extras\Module\Core\Module;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\InputField;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\OutputField;
use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\CallData\SignatureData;
use Cognesy\Instructor\Instructor;
use Tests\MockLLM;

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

// DATA MODEL DECLARATIONS ////////////////////////////////////////////////////////////////

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

$directoryContents['inbox'] = [
    'sender: jl@gmail.com, body: I am happy about the discount you offered and accept contract renewal',
    'sender: xxx, body: Get Ozempic for free',
    'sender: joe@wp.pl, body: My internet connection keeps failing',
    'sender: paul@x.io, body: How long do I have to wait for the pricing of custom support service?!?',
    'sender: joe@wp.pl, body: 2 weeks of waiting and still no improvement of my connection',
];

$instructor = (new Instructor);
$getStats = new GetStats($instructor, $directoryContents);
$emailStats = $getStats->with(EmailStats::for('inbox'));

echo "Results:\n";
dump($emailStats->get());