<?php

$keyLength = env('SOURCE_ENCRYPTION_LENGTH', 16);

// Generate a default key when one is not defined in the environment.
// You can also create one via: php artisan make:encryptionKey
$key = env('SOURCE_ENCRYPTION_KEY');

if ($key === null || $key === '') {
    $key = bin2hex(random_bytes($keyLength));
}

return [
    'source'      => [], // Configure via php artisan source-encryption:install or --source
    'destination' => 'encrypted-source', // Destination path
    'key' => $key, // custom key
    'key_length'  => $keyLength, // Encryption key length
];
