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

class GenerateLeads {
    public function __invoke(array $criteria, array $roles) : array {
        $criteriaStr = Arrays::toBullets($criteria);
        $rolesStr = Arrays::toBullets($roles);
        return (new StructuredOutput)->with(
            messages: [
                ['role' => 'user', 'content' => "Your roles:\n{$rolesStr}\n\n"],
                ['role' => 'user', 'content' => "List companies meeting criteria:\n{$criteriaStr}\n\n"],
            ],
            responseModel: Sequence::of(Company::class),
        )->get()->toArray();
    }
}

$companies = (new GenerateLeads)(
    criteria: [
        "insurtech",
        "located in US, Canada or Europe",
        "mentioned on ProductHunt",
    ],
    roles: [
        "insurtech expert",
        "active participant in VC ecosystem",
    ]
);

dump($companies);
?>
