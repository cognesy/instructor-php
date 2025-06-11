<?php

namespace Cognesy\Polyglot\LLM\Enums;

enum InferenceContentType : string
{
    case Text = 'text';
    case Thinking = 'thinking';
    case Citation = 'citation';
}