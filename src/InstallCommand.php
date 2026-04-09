<?php

namespace thedepart3d\LaravelSourceEncryption;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'source-encryption:install
                { --source=* : Relative paths to encrypt. Repeat the option for multiple paths }
                { --destination= : Destination directory }
                { --keylength= : Encryption key length }
                { --force : Overwrite the published configuration if it already exists }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish and configure the source encryption package';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $configPath = function_exists('config_path')
            ? config_path('source-encryption.php')
            : base_path('config/source-encryption.php');

        if (is_file($configPath) && ! $this->option('force') && ! $this->confirm('The source-encryption config already exists. Overwrite it?')) {
            $this->line('Command canceled.');

            return self::FAILURE;
        }

        $sources = $this->resolveSources();

        if ($sources === []) {
            return self::FAILURE;
        }

        $destination = $this->resolveDestination();

        if ($destination === null) {
            return self::FAILURE;
        }

        $keyLength = $this->resolveKeyLength();

        if ($keyLength === null) {
            return self::FAILURE;
        }

        $directory = dirname($configPath);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($configPath, $this->buildConfig($sources, $destination, $keyLength));

        $this->info('source-encryption.php has been written successfully.');
        $this->line('Configured sources: '.implode(', ', $sources));
        $this->line("Destination directory: {$destination}");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function resolveSources(): array
    {
        $sources = $this->normalizeSources((array) $this->option('source'));

        while ($sources === []) {
            if (! $this->input->isInteractive()) {
                $this->error('No source paths were provided. Pass one or more --source options.');

                return [];
            }

            $answer = $this->ask('Which files or directories should be encrypted? Enter a comma-separated list of relative paths', 'app,routes');
            $sources = $this->normalizeSources([$answer]);
        }

        $invalid = $this->invalidSources($sources);

        while ($invalid !== []) {
            $this->error('These paths do not exist: '.implode(', ', $invalid));

            if (! $this->input->isInteractive()) {
                return [];
            }

            $answer = $this->ask('Enter the source paths again', implode(',', $sources));
            $sources = $this->normalizeSources([$answer]);
            $invalid = $this->invalidSources($sources);
        }

        return $sources;
    }

    private function resolveDestination(): ?string
    {
        $destination = trim((string) $this->option('destination'));

        while ($destination === '') {
            if (! $this->input->isInteractive()) {
                return 'encrypted-source';
            }

            $destination = trim((string) $this->ask('Destination directory', 'encrypted-source'));
        }

        return $destination;
    }

    private function resolveKeyLength(): ?int
    {
        $keyLength = $this->option('keylength');

        while ($keyLength === null || trim((string) $keyLength) === '') {
            if (! $this->input->isInteractive()) {
                return 16;
            }

            $keyLength = $this->ask('Encryption key length', '16');
        }

        if (! is_numeric($keyLength) || (int) $keyLength < 1) {
            $this->error('The encryption key length must be a positive integer.');

            return null;
        }

        return (int) $keyLength;
    }

    /**
     * @param  array<int, string>  $sources
     * @return array<int, string>
     */
    private function invalidSources(array $sources): array
    {
        return array_values(array_filter($sources, static fn (string $source): bool => ! File::exists(base_path($source))));
    }

    /**
     * @param  array<int, string|null>  $values
     * @return array<int, string>
     */
    private function normalizeSources(array $values): array
    {
        $sources = [];

        foreach ($values as $value) {
            foreach (explode(',', (string) $value) as $item) {
                $item = trim($item);

                if ($item === '') {
                    continue;
                }

                $sources[] = ltrim($item, '/');
            }
        }

        return array_values(array_unique($sources));
    }

    /**
     * @param  array<int, string>  $sources
     */
    private function buildConfig(array $sources, string $destination, int $keyLength): string
    {
        $sourcesExport = var_export($sources, true);
        $destinationExport = var_export($destination, true);
        $keyLengthExport = var_export($keyLength, true);

        return <<<PHP
<?php

\$keyLength = env('SOURCE_ENCRYPTION_LENGTH', {$keyLengthExport});

// Generate a default key when one is not defined in the environment.
// You can also create one via: php artisan make:encryptionKey
\$key = env('SOURCE_ENCRYPTION_KEY');

if (\$key === null || \$key === '') {
    \$key = bin2hex(random_bytes(\$keyLength));
}

return [
    'source'      => {$sourcesExport},
    'destination' => {$destinationExport},
    'key' => \$key,
    'key_length'  => \$keyLength,
];
PHP;
    }
}
