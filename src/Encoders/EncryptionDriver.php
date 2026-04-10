<?php

namespace thedepart3d\LaravelSourceEncryption\Encoders;

interface EncryptionDriver
{
    /**
     * @param  array<int, string>  $sources
     * @param  array<string, mixed>  $options
     */
    public function encrypt(array $sources, string $destination, array $options): void;
}
