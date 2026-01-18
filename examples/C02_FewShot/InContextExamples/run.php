<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Example\Example;
use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;

enum ReviewSentiment : string {
    case Positive = 'positive';
    case Negative = 'negative';
}

class GeneratedReview {
    public string $review;
    public ReviewSentiment $sentiment;
}


class PredictSentiment {
    private int $n = 4;

    public function __invoke(string $review) : ReviewSentiment {
        return (new StructuredOutput)->with(
            messages: [
                ['role' => 'user', 'content' => "Review: {$review}"],
            ],
            responseModel: Scalar::enum(ReviewSentiment::class),
            examples: $this->generateExamples($review),
        )->get();
    }

    private function generate(string $inputReview, ReviewSentiment $sentiment) : array {
        return (new StructuredOutput)->with(
            messages: [
                ['role' => 'user', 'content' => "Generate {$this->n} various {$sentiment->value} reviews based on the input review:\n{$inputReview}"],
                ['role' => 'user', 'content' => "Generated review:"],
            ],
            responseModel: Sequence::of(GeneratedReview::class),
        )->get()->toArray();
    }

    private function generateExamples(string $inputReview) : array {
        $examples = [];
        foreach ([ReviewSentiment::Positive, ReviewSentiment::Negative] as $sentiment) {
            $samples = $this->generate($inputReview, $sentiment);
            foreach ($samples as $sample) {
                $examples[] = Example::fromData($sample->review, $sample->sentiment->value);
            }
        }
        return $examples;
    }
}

$predictSentiment = (new PredictSentiment)('This movie has been very impressive, even considering I lost half of the plot.');

dump($predictSentiment);
?>
