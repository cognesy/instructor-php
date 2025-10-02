<?php
namespace Cognesy\Experimental\Modules\Search;

use Cognesy\Experimental\Module\Core\Module;
use Cognesy\Experimental\Module\Core\Predictor;
use Cognesy\Messages\Messages;

class MakeSubqueries extends Module
{
    protected Predictor $makeSubqueries;

    public function __construct() {
        $this->makeSubqueries = Predictor::fromSignature(
            signature: 'question, context -> list_of_subqueries:string[]',
            description: "Generate relevant subqueries to extract context needed to answer provided question",
        );
    }

    public function for(string $question, string|array $context) : array {
        $context = match(true) {
            is_array($context) => Messages::fromArray($context)->toString(),
            default => $context,
        };
        return ($this)(question: $question, context: $context)
            ->get('subqueries');
    }

    #[\Override]
    protected function forward(mixed ...$callArgs) : array {
        $query = $callArgs['question'];
        $context = $callArgs['context'];
        $result = $this->makeSubqueries->predict(question: $query, context: $context);
        return [
            'subqueries' => $result->get('list_of_subqueries'),
        ];
    }
}
