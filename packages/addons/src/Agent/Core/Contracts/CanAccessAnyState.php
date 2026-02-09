<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Core\Contracts;

use Cognesy\Addons\Agent\Contracts\ToolInterface;

interface CanAccessAnyState extends ToolInterface
{
    public function withState(object $state): self;
}
