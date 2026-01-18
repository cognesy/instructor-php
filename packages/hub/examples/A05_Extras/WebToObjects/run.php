<?php
require 'examples/boot.php';

use Cognesy\Auxiliary\Web\Webpage;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Schema\Attributes\Instructions;

class Company {
    public string $name = '';
    public string $location = '';
    public string $description = '';
    public int $minProjectBudget = 0;
    public string $companySize = '';
    #[Instructions('Remove any tracking parameters from the URL')]
    public string $websiteUrl = '';
    /** @var string[] */
    public array $clients = [];
}

$sourceHtml = file_get_contents(__DIR__ . '/companies.html');
$companyGen = Webpage::withHtml($sourceHtml, 'https://example.local/companies')
    ->select('.directory-providers__list')
    ->selectMany(
        selector: '.provider-card',
        callback: fn($item) => $item->cleanup()->asMarkdown(),
        limit: 3
    );

$companies = [];
echo "Extracting company data from:\n\n";
foreach($companyGen as $companyDiv) {
    /** @var string $companyDiv */
    echo " > " . substr($companyDiv, 0, 32) . "...\n\n";
    $company = (new StructuredOutput)
        ->using('openai')
        ->with(
            messages: $companyDiv,
            responseModel: Company::class,
            mode: OutputMode::Json
        )->get();
    $companies[] = $company;
    dump($company);
}

assert(count($companies) === 3);
?>
