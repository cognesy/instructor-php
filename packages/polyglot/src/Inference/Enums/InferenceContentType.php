<?php

namespace Cognesy\Polyglot\Inference\Enums;

enum InferenceContentType : string
{
    case Text = 'text';
    case Thinking = 'thinking';
    case Citation = 'citation';
}