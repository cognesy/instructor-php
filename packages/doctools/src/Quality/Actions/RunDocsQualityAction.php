<?php declare(strict_types=1);

namespace Cognesy\Doctools\Quality\Actions;

use Cognesy\Doctools\Quality\Data\DocsQualityConfig;
use Cognesy\Doctools\Quality\Data\DocsQualityResult;
use Cognesy\Doctools\Quality\Services\DocsQualityService;

final readonly class RunDocsQualityAction
{
    public function __construct(
        private DocsQualityService $qa,
    ) {}

    public function __invoke(DocsQualityConfig $config): DocsQualityResult
    {
        return $this->qa->run($config);
    }
}
