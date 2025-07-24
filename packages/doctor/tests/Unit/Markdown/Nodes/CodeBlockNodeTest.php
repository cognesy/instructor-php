<?php

use Cognesy\Doctor\Markdown\Nodes\CodeBlockNode;

describe('CodeBlockNode', function () {
    describe('linesOfCode property', function () {
        it('counts non-empty lines correctly for PHP code', function () {
            $content = "\n\nfunction test() {\n    // This is a comment\n    echo 'hello';\n    \n    return true;\n}"; // Content with PHP tags already removed
            
            $node = new CodeBlockNode(
                id: 'test',
                language: 'php',
                content: $content
            );
            
            expect($node->linesOfCode)->toBe(4); // function test() {, echo 'hello';, return true;, }
        });

        it('excludes comment lines for PHP code', function () {
            $content = "// Single line comment\necho 'code';\n/* Block comment */\nreturn true;";
            
            $node = new CodeBlockNode(
                id: 'test',
                language: 'php',
                content: $content
            );
            
            expect($node->linesOfCode)->toBe(2); // echo 'code'; and return true;
        });

        it('counts non-empty lines correctly for Python code', function () {
            $content = "def test():\n    # This is a comment\n    print('hello')\n    \n    return True";
            
            $node = new CodeBlockNode(
                id: 'test',
                language: 'python',
                content: $content
            );
            
            expect($node->linesOfCode)->toBe(3); // def test():, print('hello'), return True
        });

        it('excludes comment lines for Python code', function () {
            $content = "# This is a comment\nprint('hello')\n# Another comment\nreturn True";
            
            $node = new CodeBlockNode(
                id: 'test',
                language: 'python',
                content: $content
            );
            
            expect($node->linesOfCode)->toBe(2); // print('hello') and return True
        });

        it('counts non-empty lines correctly for JavaScript code', function () {
            $content = "function test() {\n    // This is a comment\n    console.log('hello');\n    \n    return true;\n}";
            
            $node = new CodeBlockNode(
                id: 'test',
                language: 'javascript',
                content: $content
            );
            
            expect($node->linesOfCode)->toBe(4); // function test() {, console.log('hello');, return true;, }
        });

        it('excludes comment lines for JavaScript code', function () {
            $content = "// Single line comment\nconsole.log('code');\n/* Block comment */\nreturn true;";
            
            $node = new CodeBlockNode(
                id: 'test',
                language: 'js',
                content: $content
            );
            
            expect($node->linesOfCode)->toBe(2); // console.log('code'); and return true;
        });

        it('handles empty content', function () {
            $node = new CodeBlockNode(
                id: 'test',
                language: 'php',
                content: ''
            );
            
            expect($node->linesOfCode)->toBe(0);
        });

        it('handles content with only empty lines', function () {
            $node = new CodeBlockNode(
                id: 'test',
                language: 'php',
                content: "\n\n   \n\t\n"
            );
            
            expect($node->linesOfCode)->toBe(0);
        });

        it('handles content with only comments', function () {
            $node = new CodeBlockNode(
                id: 'test',
                language: 'php',
                content: "// Comment 1\n// Comment 2\n/* Block comment */"
            );
            
            expect($node->linesOfCode)->toBe(0);
        });

        it('counts all lines for unknown language (no comment detection)', function () {
            $content = "line 1\n// this looks like comment but language is unknown\nline 3";
            
            $node = new CodeBlockNode(
                id: 'test',
                language: 'unknown',
                content: $content
            );
            
            expect($node->linesOfCode)->toBe(3); // All non-empty lines counted
        });

        it('handles CSS comments', function () {
            $content = "/* CSS comment */\nbody {\n    color: red;\n}\n/* Another comment */";
            
            $node = new CodeBlockNode(
                id: 'test',
                language: 'css',
                content: $content
            );
            
            expect($node->linesOfCode)->toBe(3); // body {, color: red;, }
        });

        it('handles SQL comments', function () {
            $content = "-- SQL comment\nSELECT * FROM users;\n/* Block comment */\nWHERE id = 1;";
            
            $node = new CodeBlockNode(
                id: 'test',
                language: 'sql',
                content: $content
            );
            
            expect($node->linesOfCode)->toBe(2); // SELECT * FROM users;, WHERE id = 1;
        });

        it('handles HTML comments', function () {
            $content = "<!-- HTML comment -->\n<div>Hello</div>\n<!-- Another comment -->\n<span>World</span>";
            
            $node = new CodeBlockNode(
                id: 'test',
                language: 'html',
                content: $content
            );
            
            expect($node->linesOfCode)->toBe(2); // <div>Hello</div>, <span>World</span>
        });

        it('handles shell/bash comments', function () {
            $content = "#!/bin/bash\n# This is a comment\necho 'hello'\n# Another comment\nls -la";
            
            $node = new CodeBlockNode(
                id: 'test',
                language: 'bash',
                content: $content
            );
            
            expect($node->linesOfCode)->toBe(3); // #!/bin/bash, echo 'hello', ls -la
        });
    });
});