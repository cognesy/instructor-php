<?php

namespace Cognesy\Template\Enums;

enum FrontMatterFormat : string
{
    case Yaml = 'yaml';
    case Json = 'json';
    case Toml = 'toml';
    case None = 'none';
}
