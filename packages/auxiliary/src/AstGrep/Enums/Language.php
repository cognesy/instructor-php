<?php
declare(strict_types=1);

namespace Cognesy\Auxiliary\AstGrep\Enums;

enum Language: string
{
    case PHP = 'php';
    case JAVASCRIPT = 'js';
    case TYPESCRIPT = 'ts';
    case PYTHON = 'python';
    case JAVA = 'java';
    case GO = 'go';
    case RUST = 'rust';
    case C = 'c';
    case CPP = 'cpp';
    case CSHARP = 'csharp';
    case RUBY = 'ruby';
    case KOTLIN = 'kotlin';
    case SWIFT = 'swift';
    case HTML = 'html';
    case CSS = 'css';
    case JSON = 'json';
    case YAML = 'yaml';
    case XML = 'xml';
    case SQL = 'sql';
    case BASH = 'bash';
}