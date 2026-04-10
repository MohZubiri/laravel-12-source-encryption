<?php

return [
    'source'      => [], // Configure via php artisan source-encryption:install or --source
    'destination' => 'encrypted-source', // Destination path
    'key' => env('SOURCE_ENCRYPTION_KEY'), // custom key
    'key_length'  => (int) env('SOURCE_ENCRYPTION_LENGTH', 16), // Encryption key length
];
