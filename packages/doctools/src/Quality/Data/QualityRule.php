<?php declare(strict_types=1);

namespace Cognesy\Doctools\Quality\Data;

final readonly class QualityRule
{
    public function __construct(
        public string $id,
        public QualityRuleEngine $engine,
        public QualityRuleScope $scope,
        public string $pattern,
        public string $message,
        public string $severity = 'error',
        public ?string $language = null,
    ) {}
}

