{{--
description: Find country capital template for testing templates
variables:
    country:
        description: country name
        type: string
        default: France
schema:
    name: capital
    properties:
        name:
            description: Capital of the country
            type: string
    required: [name]
--}}
<chat>
    <system>
        You are a helpful assistant, respond to the user's questions in a concise manner.
    </system>

    {{-- examples --}}

    <user>
        What is the capital of France?
    </user>

    <assistant>
        {{ json_encode(['name' => 'Paris']) }}
    </assistant>

    {{-- /examples --}}

    <user>
        What is the capital of {{ $country }}?
    </user>
</chat>
