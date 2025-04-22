<?php
namespace Cognesy\Experimental\Module\Modules\Code\Enums;

enum Language : string {
    case PHP = 'PHP';
    case JavaScript = 'JavaScript';
    case TypeScript = 'TypeScript';
    case Java = 'Java';
    case C = 'C';
    case CPlusPlus = 'C++';
    case Python = 'Python';
    case Go = 'Go';
    case Rust = 'Rust';
    case Swift = 'Swift';
    case Kotlin = 'Kotlin';
    case Ruby = 'Ruby';
    case Perl = 'Perl';
    case CSharp = 'C#';
    case VisualBasic = 'Visual Basic';
    case FSharp = 'F#';
    case Lua = 'Lua';
    case Scala = 'Scala';
    case Groovy = 'Groovy';
    case Dart = 'Dart';
    case Other = 'Other';

    public function toExtension(): string {
        return match ($this) {
            self::PHP => 'php',
            self::JavaScript => 'js',
            self::TypeScript => 'ts',
            self::Java => 'java',
            self::C => 'c',
            self::CPlusPlus => 'cpp',
            self::Python => 'py',
            self::Go => 'go',
            self::Rust => 'rs',
            self::Swift => 'swift',
            self::Kotlin => 'kt',
            self::Ruby => 'rb',
            self::Perl => 'pl',
            self::CSharp => 'cs',
            self::VisualBasic => 'vb',
            self::FSharp => 'fs',
            self::Lua => 'lua',
            self::Scala => 'scala',
            self::Groovy => 'groovy',
            self::Dart => 'dart',
            self::Other => 'other',
            default => 'other',
        };
    }

    public static function fromExtension(string $extension) : self {
        return match ($extension) {
            'php' => self::PHP,
            'js' => self::JavaScript,
            'ts' => self::TypeScript,
            'java' => self::Java,
            'c' => self::C,
            'cpp' => self::CPlusPlus,
            'py' => self::Python,
            'go' => self::Go,
            'rs' => self::Rust,
            'swift' => self::Swift,
            'kt' => self::Kotlin,
            'rb' => self::Ruby,
            'pl' => self::Perl,
            'cs' => self::CSharp,
            'vb' => self::VisualBasic,
            'fs' => self::FSharp,
            'lua' => self::Lua,
            'scala' => self::Scala,
            'groovy' => self::Groovy,
            'dart' => self::Dart,
            default => self::Other,
        };
    }
}