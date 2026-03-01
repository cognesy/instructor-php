<?php declare(strict_types=1);

namespace Cognesy\Doctools\Quality\Data;

final readonly class QualityRuleSet
{
    /**
     * @param list<QualityRule> $rules
     */
    public function __construct(
        public string $sourcePath,
        public array $rules,
    ) {}
}

