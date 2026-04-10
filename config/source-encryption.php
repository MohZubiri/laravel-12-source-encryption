<?php

return [
    'source'      => [], // Configure via php artisan source-encryption:install or --source
    'destination' => 'encrypted-source', // Destination path
    'driver' => env('SOURCE_ENCRYPTION_DRIVER', 'sourceguardian'), // sourceguardian or bolt
    'binary' => env('SOURCE_ENCRYPTION_BINARY'), // External encoder binary path, for example /usr/local/bin/sgencoder
    'key' => env('SOURCE_ENCRYPTION_KEY'), // Legacy bolt key
    'key_length'  => (int) env('SOURCE_ENCRYPTION_LENGTH', 16), // Legacy bolt key length
];
