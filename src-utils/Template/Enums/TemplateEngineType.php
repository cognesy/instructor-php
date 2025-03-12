<?php

namespace Cognesy\Utils\Template\Enums;

enum TemplateEngineType : string
{
    case Twig = 'twig';
    case Blade = 'blade';
    case Arrowpipe = 'arrowpipe';
}
