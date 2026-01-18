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
    public string $description;
}

class GenerateCompanyProfiles {
    public function __invoke(array $criteria, array $styles) : array {
        $criteriaStr = Arrays::toBullets($criteria);
        $stylesStr = Arrays::toBullets($styles);
        return (new StructuredOutput)->with(
            messages: [
                ['role' => 'user', 'content' => "List companies meeting criteria:\n{$criteriaStr}\n\n"],
                ['role' => 'user', 'content' => "Use following styles for descriptions:\n{$stylesStr}\n\n"],
            ],
            responseModel: Sequence::of(Company::class),
        )->get()->toArray();
    }
}

$companies = (new GenerateCompanyProfiles)(
    criteria: [
        "insurtech",
        "located in US, Canada or Europe",
        "mentioned on ProductHunt"
    ],
    styles: [
        "brief", // "witty",
        "journalistic", // "buzzword-filled",
    ]
);

dump($companies);
?>
