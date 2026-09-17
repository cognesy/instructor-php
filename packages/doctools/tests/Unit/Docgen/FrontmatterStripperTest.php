<?php

declare(strict_types=1);

use Cognesy\Doctools\Docgen\FrontmatterStripper;

it('removes frontmatter and markdownlint directives from generated MDX', function () {
    $source = <<<MD
        ---
        title: Example
        ---

        <!-- markdownlint-disable MD013 -->

        # Example
        MD;

    expect((new FrontmatterStripper)->stripContent($source))->toBe("# Example");
});

it('removes markdownlint directives without frontmatter', function () {
    $source = "<!-- markdownlint-disable MD025 -->\n\n# Example\n";

    expect((new FrontmatterStripper)->stripContent($source))->toBe("# Example\n");
});

it('preserves ordinary HTML comments', function () {
    $source = "<!-- keep this comment -->\n\n# Example\n";

    expect((new FrontmatterStripper)->stripContent($source))->toBe($source);
});
