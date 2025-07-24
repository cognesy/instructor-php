<?php

use Cognesy\Doctor\Markdown\Nodes\ContentNode;

describe('ContentNode', function () {
    describe('codeQuotes method', function () {
        it('extracts single inline code quotes', function () {
            $content = 'This text contains `ClassName` inline code.';
            $node = new ContentNode($content);
            
            expect($node->codeQuotes())->toBe(['ClassName']);
        });

        it('extracts multiple inline code quotes', function () {
            $content = 'Use `ClassName` with `methodName()` and `propertyName`.';
            $node = new ContentNode($content);
            
            expect($node->codeQuotes())->toBe(['ClassName', 'methodName()', 'propertyName']);
        });

        it('handles content without code quotes', function () {
            $content = 'This is plain text without any inline code.';
            $node = new ContentNode($content);
            
            expect($node->codeQuotes())->toBe([]);
        });

        it('handles empty content', function () {
            $node = new ContentNode('');
            
            expect($node->codeQuotes())->toBe([]);
        });

        it('handles code quotes with special characters', function () {
            $content = 'Methods like `user->getName()` and `$config["key"]` are common.';
            $node = new ContentNode($content);
            
            expect($node->codeQuotes())->toBe(['user->getName()', '$config["key"]']);
        });

        it('handles code quotes with spaces and underscores', function () {
            $content = 'Constants like `MAX_ITEMS` and functions like `get_user_data()`.';
            $node = new ContentNode($content);
            
            expect($node->codeQuotes())->toBe(['MAX_ITEMS', 'get_user_data()']);
        });

        it('ignores code block fences', function () {
            $content = 'Here is some `inline` code but ```this is a block``` marker.';
            $node = new ContentNode($content);
            
            expect($node->codeQuotes())->toBe(['inline']);
        });

        it('handles nested backticks correctly', function () {
            $content = 'This contains `some code` and another `different code`.';
            $node = new ContentNode($content);
            
            expect($node->codeQuotes())->toBe(['some code', 'different code']);
        });

        it('handles code quotes at start and end of content', function () {
            $content = '`start` this is middle content `end`';
            $node = new ContentNode($content);
            
            expect($node->codeQuotes())->toBe(['start', 'end']);
        });

        it('handles consecutive code quotes', function () {
            $content = 'Use `first` and `second` together.';
            $node = new ContentNode($content);
            
            expect($node->codeQuotes())->toBe(['first', 'second']);
        });
    });
});