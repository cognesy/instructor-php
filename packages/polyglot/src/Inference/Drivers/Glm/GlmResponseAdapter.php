<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Glm;

use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleReasoningAdapter;

/**
 * Thin provider-named subclass — behavior lives in the shared
 * OpenAICompatibleReasoningAdapter (reasoning-key extraction, cumulative
 * streamed usage). Kept so direct class references remain valid.
 */
class GlmResponseAdapter extends OpenAICompatibleReasoningAdapter
{
}
