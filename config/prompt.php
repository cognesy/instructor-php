<?php
return [
    'defaultSetting' => 'twig',

    'settings' => [
        'twig' => [
            'templateType' => 'twig',
            'resourcePath' => '/../../../../prompts/twig',
            'cachePath' => '/tmp/instructor/cache/twig',
            'extension' => '.twig',
            'frontMatterTags' => ['{#---', '---#}'],
            'frontMatterFormat' => 'yaml',
            'metadata' => [
                'autoReload' => true,
            ],
        ],
        'blade' => [
            'templateType' => 'blade',
            'resourcePath' => '/../../../../prompts/blade',
            'cachePath' => '/tmp/instructor/cache/blade',
            'extension' => '.blade.php',
            'frontMatterTags' => ['{{--', '--}}'],
            'frontMatterFormat' => 'yaml',
        ],
    ]
];
