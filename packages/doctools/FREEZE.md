# Freeze PHP API

Freeze generates beautiful images of code and terminal output. This PHP API provides a fluent interface to the freeze CLI tool with automatic language detection and comprehensive styling options.

## Quick Start

```php
use Cognesy\Doctor\Freeze\Freeze;
use Cognesy\Doctor\Freeze\FreezeConfig;

// Generate image from file
$result = Freeze::file('script.php')
    ->output('code.png')
    ->theme(FreezeConfig::THEME_DRACULA)
    ->window()
    ->run();

// Generate image from terminal command
$result = Freeze::execute('ls -la')
    ->output('terminal.png')
    ->background('#1e1e2e')
    ->run();
```

## Methods

### Entry Points
```php
Freeze::file($filePath)     // Create from file
Freeze::execute($command)   // Create from terminal command
```

### Essential Options
```php
->output($path)            // Output file (.png, .svg, .webp)
->language($lang)          // Override auto-detection
->theme($theme)            // Color theme
->run()                    // Execute command
```

### Styling
```php
->window()                 // macOS-style window
->showLineNumbers()        // Show line numbers
->background($color)       // Background color
->fontSize($size)          // Font size
->fontFamily($family)      // Font family
->borderRadius($radius)    // Corner radius
->padding($padding)        // Padding (e.g. '20' or '20,40')
->margin($margin)          // Margin
->height($height)          // Terminal height
```

## Themes
```php
FreezeConfig::THEME_DRACULA
FreezeConfig::THEME_GITHUB  
FreezeConfig::THEME_NORD
FreezeConfig::THEME_MONOKAI
// ... see FreezeConfig for all themes
```

## Language Auto-Detection
Automatically detects: `php`, `py`, `js`, `ts`, `java`, `cpp`, `c`, `go`, `rs`, `rb`, `swift`, `kt`, `scala`, `sh`, `sql`, `html`, `css`, `json`, `yaml`, `md`

## Result Handling
```php
$result = Freeze::file('script.php')->run();

if ($result->isSuccessful()) {
    echo "Generated: " . $result->getOutputPath();
    echo "File exists: " . ($result->hasOutputFile() ? 'Yes' : 'No');
} else {
    echo "Error: " . $result->getErrorOutput();
}

// Available methods
$result->isSuccessful()    // bool
$result->failed()          // bool  
$result->getOutputPath()   // string|null
$result->hasOutputFile()   // bool
$result->getCommand()      // string (executed command)
$result->getOutput()       // string (stdout)
$result->getErrorOutput()  // string (stderr)
```

## Complete Example
```php
$result = Freeze::file('Calculator.php')
    ->theme(FreezeConfig::THEME_NORD)
    ->output('./images/calculator.png')
    ->window()
    ->showLineNumbers()
    ->fontFamily('JetBrains Mono')
    ->fontSize(16)
    ->borderRadius(12)
    ->padding('30')
    ->background('#2e3440')
    ->lines('1,50')
    ->run();

if ($result->failed()) {
    throw new Exception($result->getErrorOutput());
}
```

## Requirements
- freeze CLI tool installed (`brew install charmbracelet/tap/freeze`)
- PHP 8.2+
- symfony/process