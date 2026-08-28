<?php

declare(strict_types=1);

namespace Cognesy\InstructorHub\Utils;

use Cognesy\Utils\Cli\CliMarkdown as SharedCliMarkdown;

/**
 * @deprecated Moved to Cognesy\Utils\Cli\CliMarkdown so CLI packages other than
 * the hub can render Markdown without depending on the example runner. This
 * subclass only preserves the published class name and will be removed in 3.x.
 */
class CliMarkdown extends SharedCliMarkdown {}
