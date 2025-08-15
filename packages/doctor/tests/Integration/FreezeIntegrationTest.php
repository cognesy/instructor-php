<?php

declare(strict_types=1);

use Cognesy\Doctor\Freeze\Freeze;
use Cognesy\Doctor\Freeze\FreezeConfig;

beforeEach(function () {
    // Ensure tmp directory exists
    if (!is_dir('./tmp')) {
        mkdir('./tmp', 0755, true);
    }
    
    // Clean up any existing test files
    $testFiles = glob('./tmp/freeze_integration_*');
    foreach ($testFiles as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
});

afterEach(function () {
    // Clean up test files after each test
    $testFiles = glob('./tmp/freeze_integration_*');
    foreach ($testFiles as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
});

test('freeze API builds correct command for Python file with auto-language detection', function () {
    $testFile = './tmp/freeze_integration_script.py';
    $outputFile = './tmp/freeze_integration_output.png';
    
    file_put_contents($testFile, <<<'PYTHON'
def fibonacci(n):
    if n <= 1:
        return n
    return fibonacci(n-1) + fibonacci(n-2)

print("Fibonacci sequence:")
for i in range(8):
    print(f"fib({i}) = {fibonacci(i)}")
PYTHON);

    try {
        $command = Freeze::file($testFile)
            ->output($outputFile)
            ->theme(FreezeConfig::THEME_DRACULA)
            ->window()
            ->showLineNumbers();
        
        $builtCommand = $command->buildCommandString();
        
        expect($builtCommand)
            ->toContain('freeze')
            ->toContain('--language')
            ->toContain('python')
            ->toContain('--theme')
            ->toContain('dracula')
            ->toContain('--output')
            ->toContain($outputFile)
            ->toContain('--window')
            ->toContain('--show-line-numbers')
            ->toContain($testFile);
            
    } finally {
        if (file_exists($testFile)) {
            unlink($testFile);
        }
    }
});

test('freeze API builds correct command for JavaScript with explicit language', function () {
    $testFile = './tmp/freeze_integration_script.js';
    $outputFile = './tmp/freeze_integration_js_output.svg';
    
    file_put_contents($testFile, <<<'JAVASCRIPT'
class Calculator {
    constructor() {
        this.result = 0;
    }
    
    add(value) {
        this.result += value;
        return this;
    }
    
    multiply(value) {
        this.result *= value;
        return this;
    }
    
    getResult() {
        return this.result;
    }
}

const calc = new Calculator();
console.log(calc.add(5).multiply(3).getResult());
JAVASCRIPT);

    try {
        $command = Freeze::file($testFile)
            ->language('typescript') // Override auto-detection
            ->output($outputFile)
            ->theme(FreezeConfig::THEME_GITHUB)
            ->fontSize(14)
            ->borderRadius(8);
        
        $builtCommand = $command->buildCommandString();
        
        expect($builtCommand)
            ->toContain('freeze')
            ->toContain('--language')
            ->toContain('typescript') // Should use explicit language, not auto-detected 'javascript'
            ->toContain('--theme')
            ->toContain('github')
            ->toContain('--output')
            ->toContain($outputFile)
            ->toContain('--font.size')
            ->toContain('14')
            ->toContain('--border.radius')
            ->toContain('8')
            ->toContain($testFile);
            
    } finally {
        if (file_exists($testFile)) {
            unlink($testFile);
        }
    }
});

test('freeze API builds correct command for terminal execution', function () {
    $outputFile = './tmp/freeze_integration_terminal.png';
    
    $command = Freeze::execute('echo "Hello from Freeze Integration Test"')
        ->output($outputFile)
        ->background('#0f0f23')
        ->height(200)
        ->config(FreezeConfig::CONFIG_FULL);
    
    $builtCommand = $command->buildCommandString();
    
    expect($builtCommand)
        ->toContain('freeze')
        ->toContain('-x')
        ->toContain('echo "Hello from Freeze Integration Test"')
        ->toContain('--config')
        ->toContain('full')
        ->toContain('--output')
        ->toContain($outputFile)
        ->toContain('--background')
        ->toContain('#0f0f23')
        ->toContain('--height')
        ->toContain('200');
});

test('freeze API handles comprehensive styling options', function () {
    $testFile = './tmp/freeze_integration_comprehensive.php';
    $outputFile = './tmp/freeze_integration_comprehensive.png';
    
    file_put_contents($testFile, <<<'PHP'
<?php

declare(strict_types=1);

class FreezeDemo
{
    private array $items = [];
    
    public function add(string $item): self
    {
        $this->items[] = $item;
        return $this;
    }
    
    public function getItems(): array
    {
        return $this->items;
    }
}

$demo = new FreezeDemo();
$demo->add('Hello')->add('World');
print_r($demo->getItems());
PHP);

    try {
        $command = Freeze::file($testFile)
            ->output($outputFile)
            ->theme(FreezeConfig::THEME_NORD)
            ->window()
            ->showLineNumbers()
            ->fontFamily('JetBrains Mono')
            ->fontSize(16)
            ->lineHeight(1.4)
            ->borderRadius(12)
            ->borderWidth(2)
            ->borderColor('#4c566a')
            ->padding('30')
            ->margin('20')
            ->background('#2e3440')
            ->lines('1,20');
        
        $builtCommand = $command->buildCommandString();
        
        expect($builtCommand)
            ->toContain('freeze')
            ->toContain('--language')
            ->toContain('php')
            ->toContain('--theme')
            ->toContain('nord')
            ->toContain('--window')
            ->toContain('--show-line-numbers')
            ->toContain('--font.family')
            ->toContain('JetBrains Mono')
            ->toContain('--font.size')
            ->toContain('16')
            ->toContain('--line-height')
            ->toContain('1.4')
            ->toContain('--border.radius')
            ->toContain('12')
            ->toContain('--border.width')
            ->toContain('2')
            ->toContain('--border.color')
            ->toContain('#4c566a')
            ->toContain('--padding')
            ->toContain('30')
            ->toContain('--margin')
            ->toContain('20')
            ->toContain('--background')
            ->toContain('#2e3440')
            ->toContain('--lines')
            ->toContain('1,20')
            ->toContain($testFile);
            
    } finally {
        if (file_exists($testFile)) {
            unlink($testFile);
        }
    }
});

test('freeze API result object provides correct information', function () {
    $testFile = './tmp/freeze_integration_result_test.py';
    $outputFile = './tmp/freeze_integration_result_output.png';
    
    file_put_contents($testFile, 'print("result test")');
    
    try {
        $result = Freeze::file($testFile)
            ->output($outputFile)
            ->theme(FreezeConfig::THEME_BASE)
            ->run();
        
        // Test result object structure regardless of execution success
        expect($result)->toBeInstanceOf(\Cognesy\Doctor\Freeze\FreezeResult::class)
            ->and($result->getCommand())->toBeString()
            ->and($result->getCommand())->toContain('freeze')
            ->and($result->getCommand())->toContain($testFile)
            ->and($result->getCommand())->toContain($outputFile)
            ->and($result->getCommand())->toContain('--theme')
            ->and($result->getCommand())->toContain('base')
            ->and($result->getOutputPath())->toBe($outputFile)
            ->and($result->getOutput())->toBeString()
            ->and($result->getErrorOutput())->toBeString();
        
        // Test boolean methods work correctly
        expect($result->isSuccessful())->toBeBool()
            ->and($result->failed())->toBeBool()
            ->and($result->failed())->toBe(!$result->isSuccessful()); // Should be opposites
            
    } finally {
        if (file_exists($testFile)) {
            unlink($testFile);
        }
        if (file_exists($outputFile)) {
            unlink($outputFile);
        }
    }
});

test('freeze API handles non-existent files gracefully', function () {
    $nonExistentFile = './tmp/freeze_integration_nonexistent.php';
    $outputFile = './tmp/freeze_integration_failure.png';
    
    // Ensure the file doesn't exist
    if (file_exists($nonExistentFile)) {
        unlink($nonExistentFile);
    }
    
    try {
        $result = Freeze::file($nonExistentFile)
            ->output($outputFile)
            ->run();
        
        expect($result->isSuccessful())->toBeFalse()
            ->and($result->failed())->toBeTrue()
            ->and($result->hasOutputFile())->toBeFalse()
            ->and(file_exists($outputFile))->toBeFalse()
            ->and($result->getCommand())->toContain($nonExistentFile)
            ->and($result->getOutputPath())->toBe($outputFile);
            
    } finally {
        if (file_exists($outputFile)) {
            unlink($outputFile);
        }
    }
});

test('freeze API language auto-detection works for common file types', function () {
    $testCases = [
        ['./tmp/test.py', 'python'],
        ['./tmp/test.js', 'javascript'],
        ['./tmp/test.php', 'php'],
        ['./tmp/test.java', 'java'],
        ['./tmp/test.cpp', 'cpp'],
        ['./tmp/test.rs', 'rust'],
        ['./tmp/test.go', 'go'],
        ['./tmp/test.rb', 'ruby'],
    ];
    
    foreach ($testCases as [$file, $expectedLanguage]) {
        file_put_contents($file, 'test content');
        
        $command = Freeze::file($file)->output('./tmp/output.png');
        $builtCommand = $command->buildCommandString();
        
        expect($builtCommand)->toContain("--language '$expectedLanguage'");
        
        unlink($file);
    }
});