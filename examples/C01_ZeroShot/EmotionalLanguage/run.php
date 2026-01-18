<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Utils\Arrays;

class Company {
    public string $name;
    public string $country;
    public string $industry;
    public string $websiteUrl;
}

class RespondWithStimulus {
    public function __invoke(array $criteria, string $stimulus) : array {
        $criteriaStr = Arrays::toBullets($criteria);
        return (new StructuredOutput)->with(
            messages: [
                ['role' => 'user', 'content' => "List companies meeting criteria:\n{$criteriaStr}"],
                ['role' => 'user', 'content' => "{$stimulus}"],
            ],
            responseModel: Sequence::of(Company::class),
        )->get()->toArray();
    }
}

$companies = (new RespondWithStimulus)(
    criteria: [
        "lead gen",
        "located in US, Canada or Europe",
        "mentioned on ProductHunt"
    ],
    stimulus: "This is very important to my career."
);

dump($companies);
?>
