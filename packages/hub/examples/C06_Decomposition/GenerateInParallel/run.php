<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;

class Point {
    public function __construct(
        public int $index,
        public string $description
    ) {}
}

class Skeleton {
    public function __construct(
        /** @var Point[] */
        public array $points
    ) {}
}

class Response {
    public function __construct(
        public string $response
    ) {}
}

class SkeletonOfThoughtGenerator {
    public function __invoke(string $question): array {
        $skeleton = $this->getSkeleton($question);
        return $this->expandPointsSequentially($question, $skeleton);
    }
    
    private function getSkeleton(string $question): Skeleton {
        return (new StructuredOutput)->with(
            messages: [
                [
                    'role' => 'user',
                    'content' => "You're an organizer responsible for only giving the skeleton (not the full content) for answering the question.
Provide the skeleton in a list of points (numbered 1., 2., 3., etc.) to answer the question.
Instead of writing a full sentence, each skeleton point should be very short with only 3-5 words.
Generally, the skeleton should have 3-10 points.

Now, please provide the skeleton for the following question.

<question>
{$question}
</question>

Skeleton:"
                ],
            ],
            responseModel: Skeleton::class,
        )->get();
    }
    
    private function expandPoint(string $question, Skeleton $skeleton, int $pointIndex): Response {
        $skeletonText = '';
        foreach ($skeleton->points as $point) {
            $skeletonText .= "{$point->index}. {$point->description}\n";
        }
        
        return (new StructuredOutput)->with(
            messages: [
                [
                    'role' => 'user',
                    'content' => "You're responsible for continuing the writing of one and only one point in the overall answer to the following question.

<question>
{$question}
</question>

The skeleton of the answer is:

<skeleton>
{$skeletonText}
</skeleton>

Continue and only continue the writing of point {$pointIndex}.
Write it **very shortly** in 1-2 sentences and do not continue with other points!"
                ],
            ],
            responseModel: Response::class,
        )->get();
    }
    
    private function expandPointsSequentially(string $question, Skeleton $skeleton): array {
        $responses = [];
        foreach ($skeleton->points as $point) {
            $response = $this->expandPoint($question, $skeleton, $point->index);
            $responses[] = [
                'point' => $point,
                'content' => $response->response
            ];
        }
        return $responses;
    }
}

$results = (new SkeletonOfThoughtGenerator)(
    "Compose an engaging travel blog post about a recent trip to Hawaii, highlighting cultural experiences and must-see attractions."
);

echo "Generated Content:\n";
echo str_repeat("=", 50) . "\n";

foreach ($results as $result) {
    echo "Point {$result['point']->index}: {$result['point']->description}\n";
    echo "{$result['content']}\n\n";
}

dump($results);
?>
