<?php declare(strict_types=1);

namespace Cognesy\Doctools\Quality\Services;

use Cognesy\Doctools\Quality\Data\DocsQualityConfig;
use Cognesy\Doctools\Quality\Data\QualityRule;

final readonly class RulesProvider
{
    public function __construct(
        private RulesDiscovery $discovery = new RulesDiscovery(),
        private RulesLoader $loader = new RulesLoader(),
    ) {}

    /**
     * @return list<QualityRule>
     */
    public function rulesFor(DocsQualityConfig $config): array
    {
        $rules = [];
        foreach ($this->discovery->discover($config) as $rulesFile) {
            $ruleSet = $this->loader->load($rulesFile);
            foreach ($ruleSet->rules as $rule) {
                $rules[$rule->id] = $rule;
            }
        }

        return array_values($rules);
    }
}

