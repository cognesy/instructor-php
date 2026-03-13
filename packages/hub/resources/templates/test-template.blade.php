@foreach($imports as $import):
    use {{ $import }};
@endforeach

beforeEach(function () {
    // empty
});

afterEach(function () {
    // empty
});

/**
 * Test for {{ $id }}
 */
it('test of codeblock {{ $id }}', function () {
    // @doctest id="{{$id}}"

    {{ $code }}

    // @expectations

    expect(true)->toBeTrue();
});
