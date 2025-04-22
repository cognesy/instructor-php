<?php
return [
    'defaultLibrary' => 'demo-twig',

    'libraries' => [
        'system' => [
            'templateEngine' => 'arrowpipe',
            'resourcePath' => 'prompts/system',
            'cachePath' => '/tmp/instructor/cache/system',
            'extension' => '.tpl',
            'frontMatterTags' => ['{#---', '---#}'],
            'frontMatterFormat' => 'yaml',
            'metadata' => [
                'autoReload' => true,
            ],
        ],
        'examples' => [
            'templateEngine' => 'twig',
            'resourcePath' => 'prompts/examples',
            'cachePath' => '/tmp/instructor/cache/examples',
            'extension' => '.twig',
            'frontMatterTags' => ['{#---', '---#}'],
            'frontMatterFormat' => 'yaml',
            'metadata' => [
                'autoReload' => true,
            ],
        ],
        'demo-twig' => [
            'templateEngine' => 'twig',
            'resourcePath' => 'prompts/demo-twig',
            'cachePath' => '/tmp/instructor/cache/twig',
            'extension' => '.twig',
            'frontMatterTags' => ['{#---', '---#}'],
            'frontMatterFormat' => 'yaml',
            'metadata' => [
                'autoReload' => true,
            ],
        ],
        'demo-blade' => [
            'templateEngine' => 'blade',
            'resourcePath' => 'prompts/demo-blade',
            'cachePath' => '/tmp/instructor/cache/blade',
            'extension' => '.blade.php',
            'frontMatterTags' => ['{{--', '--}}'],
            'frontMatterFormat' => 'yaml',
        ],
    ]
];
