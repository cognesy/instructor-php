<?php

declare(strict_types=1);

namespace Cognesy\Tell;

use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffort;

/** @deprecated Use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffort. */
class_alias(ReasoningEffort::class, __NAMESPACE__.'\\TellReasoningEffort');
