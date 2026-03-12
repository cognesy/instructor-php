<?php

declare(strict_types=1);

use Cognesy\Xprompt\Prompt;

it('propagates context from parent to child prompts', function () {
    $child = new class extends Prompt {
        public function body(mixed ...$ctx): string|array|null {
            return "child:{$ctx['lang']}";
        }
    };
    $parent = new class extends Prompt {
        public static ?Prompt $child = null;
        public function body(mixed ...$ctx): array {
            return ['parent', static::$child];
        }
    };
    $parent::$child = $child;
    $text = $parent->render(lang: 'en');
    expect($text)->toContain('child:en');
});

it('propagates context through 3 levels', function () {
    $level3 = new class extends Prompt {
        public function body(mixed ...$ctx): string|array|null {
            return "L3:{$ctx['val']}";
        }
    };
    $level2 = new class extends Prompt {
        public static ?Prompt $child = null;
        public function body(mixed ...$ctx): array {
            return ["L2:{$ctx['val']}", static::$child];
        }
    };
    $level2::$child = $level3;

    $level1 = new class extends Prompt {
        public static ?Prompt $child = null;
        public function body(mixed ...$ctx): array {
            return ["L1:{$ctx['val']}", static::$child];
        }
    };
    $level1::$child = $level2;

    $text = $level1->render(val: 'x');
    expect($text)->toContain('L1:x');
    expect($text)->toContain('L2:x');
    expect($text)->toContain('L3:x');
});

it('child with() context merges with parent context', function () {
    $childClass = get_class(new class extends Prompt {
        public function body(mixed ...$ctx): string|array|null {
            return "{$ctx['a']}:{$ctx['b']}";
        }
    });
    $parent = new class extends Prompt {
        public static string $childClass = '';
        public function body(mixed ...$ctx): array {
            return [(static::$childClass)::with(b: 'child_b')];
        }
    };
    $parent::$childClass = $childClass;
    // Parent provides 'a', child has bound 'b'
    $text = $parent->render(a: 'parent_a');
    expect($text)->toBe('parent_a:child_b');
});

it('child bound context takes precedence for same key', function () {
    $childClass = get_class(new class extends Prompt {
        public function body(mixed ...$ctx): string|array|null {
            return "color:{$ctx['color']}";
        }
    });
    $parent = new class extends Prompt {
        public static string $childClass = '';
        public function body(mixed ...$ctx): array {
            // Child overrides 'color' to 'blue'
            return [(static::$childClass)::with(color: 'blue')];
        }
    };
    $parent::$childClass = $childClass;
    // render ctx (from parent propagation) overrides child's bound ctx
    $text = $parent->render(color: 'red');
    expect($text)->toBe('color:red');
});
