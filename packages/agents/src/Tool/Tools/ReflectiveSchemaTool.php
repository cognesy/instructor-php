<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool\Tools;

use Cognesy\Agents\Tool\Traits\HasReflectiveSchema;

abstract class ReflectiveSchemaTool extends SimpleTool
{
    use HasReflectiveSchema;
}
