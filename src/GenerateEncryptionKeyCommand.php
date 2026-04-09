<?php

namespace thedepart3d\LaravelSourceEncryption;

use Illuminate\Console\Command;

class GenerateEncryptionKeyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:encryptionKey {--show : Output the generated key instead of updating the environment file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a source encryption key';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $keyLength = (int) config('source-encryption.key_length', env('SOURCE_ENCRYPTION_LENGTH', 16));
        $token = bin2hex(random_bytes($keyLength));

        if ($this->option('show')) {
            $this->line("SOURCE_ENCRYPTION_KEY={$token}");

            return self::SUCCESS;
        }

        $path = $this->laravel->environmentFilePath();

        if (! is_file($path)) {
            $this->error('Unable to locate the environment file.');

            return self::FAILURE;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            $this->error('Unable to read the environment file.');

            return self::FAILURE;
        }

        $line = "SOURCE_ENCRYPTION_KEY={$token}";

        if (preg_match('/^SOURCE_ENCRYPTION_KEY=.*$/m', $contents) === 1) {
            $contents = preg_replace('/^SOURCE_ENCRYPTION_KEY=.*$/m', $line, $contents, 1);
        } else {
            $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }

        if ($contents === null || file_put_contents($path, $contents) === false) {
            $this->error('Unable to write the environment file.');

            return self::FAILURE;
        }

        $this->info('SOURCE_ENCRYPTION_KEY has been updated in your environment file.');

        return self::SUCCESS;
    }
}
