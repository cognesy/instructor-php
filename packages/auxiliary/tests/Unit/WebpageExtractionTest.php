<?php declare(strict_types=1);

use Cognesy\Auxiliary\Web\Webpage;

it('extracts provider cards from static html', function () {
    $html = <<<'HTML'
<div class="directory-providers__list">
  <div class="provider-card">
    <h3>Acme Labs</h3>
    <div class="location">New York, NY</div>
  </div>
  <div class="provider-card">
    <h3>Blue River Software</h3>
    <div class="location">Austin, TX</div>
  </div>
</div>
HTML;

    $cards = iterator_to_array(
        Webpage::withHtml($html)
            ->select('.directory-providers__list')
            ->selectMany(
                selector: '.provider-card',
                callback: static fn(Webpage $item) => $item->cleanup()->asMarkdown()
            ),
        false
    );

    expect($cards)->toHaveCount(2);
    expect($cards[0])->toContain('Acme Labs');
    expect($cards[1])->toContain('Blue River Software');
});
