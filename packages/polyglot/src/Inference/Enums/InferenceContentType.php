<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Enums;

enum InferenceContentType : string
{
    case Text = 'text';
    case Thinking = 'thinking';
    case Citation = 'citation';
}