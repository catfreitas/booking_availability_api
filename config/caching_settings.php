<?php

// config/caching_settings.php

return [
    'availability' => [
        'key_prefix' => 'availability',
        'base_tags' => ['availability'],
        'property_tag_prefix' => 'property',
        'ttl_seconds' => 60 * 60 * 24,
    ],

    // You could add other caching configurations here for different parts of your app
    // 'another_feature' => [
    //     'key_prefix' => 'another_feature_prefix',
    //     'base_tags' => ['another_feature_tag'],
    //     'ttl_seconds' => 60 * 30, // 30 minutes
    // ],
];
