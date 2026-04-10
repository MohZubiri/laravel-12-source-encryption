<?php
/**
 * Laravel Source Encryption.
 *
 * @author      The Departed / Mr Robot
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 *
 * @link        https://git.saeedhurzuk.com/MrRobot/Laravel-Source-Encryption
 */

namespace thedepart3d\LaravelSourceEncryption;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use thedepart3d\LaravelSourceEncryption\Encoders\BoltEncryptionDriver;
use thedepart3d\LaravelSourceEncryption\Encoders\EncryptionDriver;
use thedepart3d\LaravelSourceEncryption\Encoders\SourceGuardianEncryptionDriver;

class EncryptCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'encrypt-source
                { --source=* : Path(s) to encrypt. Repeat the option or pass a comma-separated list }
                { --destination= : Destination directory }
                { --driver= : Encoder driver to use (sourceguardian or bolt) }
                { --binary= : Path to the encoder executable for external drivers }
                { --force : Force the operation to run when destination directory already exists }
                { --key= : Custom Encryption Key for the bolt driver }
                { --keylength= : Encryption key length for the bolt driver }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypts app source files';

    private ?array $packageConfig = null;
    private ?array $publishedConfig = null;
    private bool $warnedAboutStaleConfig = false;

    public function handle(): int
    {
        $sources = $this->resolveSources();

        if ($sources === []) {
            $this->error('No source paths are configured. Run php artisan source-encryption:install or pass one or more --source options. If you recently edited config/source-encryption.php, refresh your Laravel configuration cache.');

            return self::FAILURE;
        }

        foreach ($sources as $source) {
            if (! File::exists(base_path($source))) {
                $this->error("File {$source} does not exist.");

                return self::FAILURE;
            }
        }

        $destination = $this->resolveDestination();
        $driverName = $this->resolveDriver();

        if (! $this->option('force')
            && File::exists(base_path($destination))
            && ! $this->confirm("The directory {$destination} already exists. Delete directory?")
        ) {
            $this->line('Command canceled.');

            return self::FAILURE;
        }

        File::deleteDirectory(base_path($destination));
        File::makeDirectory(base_path($destination), 0755, true);

        try {
            $driver = $this->makeDriver($driverName);
            $driver->encrypt($sources, $destination, $this->driverOptions($driverName));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Encrypting completed successfully!');
        $this->info("Driver: {$driverName}");
        $this->info("Destination directory: {$destination}");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function resolveSources(): array
    {
        $sources = $this->normalizeSources((array) $this->option('source'));

        if ($sources !== []) {
            return $sources;
        }

        $runtimeSources = $this->normalizeSources((array) config('source-encryption.source', []));

        if ($runtimeSources !== []) {
            return $runtimeSources;
        }

        $publishedSources = $this->normalizeSources((array) $this->publishedConfigValue('source', []));

        if ($publishedSources !== [] && $this->shouldPreferPublishedConfig('source', config('source-encryption.source', []), $publishedSources)) {
            $this->warnAboutStaleConfiguration();

            return $publishedSources;
        }

        return $publishedSources;
    }

    private function resolveDestination(): string
    {
        $destination = trim((string) $this->option('destination'));

        if ($destination !== '') {
            return $destination;
        }

        $runtimeDestination = trim((string) config('source-encryption.destination', ''));
        $publishedDestination = trim((string) $this->publishedConfigValue('destination', ''));

        if ($this->shouldPreferPublishedConfig('destination', $runtimeDestination, $publishedDestination)) {
            $this->warnAboutStaleConfiguration();

            return $publishedDestination;
        }

        if ($runtimeDestination !== '') {
            return $runtimeDestination;
        }

        if ($publishedDestination !== '') {
            return $publishedDestination;
        }

        return 'encrypted-source';
    }

    private function resolveDriver(): string
    {
        $driver = trim((string) $this->option('driver'));

        if ($driver !== '') {
            return strtolower($driver);
        }

        $runtimeDriver = trim((string) config('source-encryption.driver', ''));
        $publishedDriver = trim((string) $this->publishedConfigValue('driver', ''));

        if ($this->shouldPreferPublishedConfig('driver', $runtimeDriver, $publishedDriver)) {
            $this->warnAboutStaleConfiguration();

            return strtolower($publishedDriver);
        }

        if ($runtimeDriver !== '') {
            return strtolower($runtimeDriver);
        }

        if ($publishedDriver !== '') {
            return strtolower($publishedDriver);
        }

        return 'sourceguardian';
    }

    /**
     * @return array<string, mixed>
     */
    private function driverOptions(string $driver): array
    {
        return match ($driver) {
            'sourceguardian' => [
                'binary' => $this->resolveBinary(),
            ],
            'bolt' => [
                'key' => $this->resolveBoltKey(),
                'key_length' => $this->resolveBoltKeyLength(),
            ],
            default => throw new RuntimeException("Unsupported encryption driver [{$driver}]. Supported drivers are [sourceguardian, bolt]."),
        };
    }

    private function resolveBinary(): ?string
    {
        $binary = trim((string) $this->option('binary'));

        if ($binary !== '') {
            return $binary;
        }

        $runtimeBinary = trim((string) config('source-encryption.binary', ''));
        $publishedBinary = trim((string) $this->publishedConfigValue('binary', ''));

        if ($this->shouldPreferPublishedConfig('binary', $runtimeBinary, $publishedBinary)) {
            $this->warnAboutStaleConfiguration();

            return $publishedBinary !== '' ? $publishedBinary : null;
        }

        if ($runtimeBinary !== '') {
            return $runtimeBinary;
        }

        return $publishedBinary !== '' ? $publishedBinary : null;
    }

    private function resolveBoltKey(): ?string
    {
        $key = trim((string) $this->option('key'));

        if ($key !== '') {
            return $key;
        }

        $runtimeKey = config('source-encryption.key');
        $publishedKey = $this->publishedConfigValue('key');

        if ($this->shouldPreferPublishedConfig('key', $runtimeKey, $publishedKey)) {
            $this->warnAboutStaleConfiguration();

            return is_string($publishedKey) ? $publishedKey : null;
        }

        return is_string($runtimeKey) && trim($runtimeKey) !== ''
            ? $runtimeKey
            : (is_string($publishedKey) && trim($publishedKey) !== '' ? $publishedKey : null);
    }

    private function resolveBoltKeyLength(): int
    {
        $keyLength = $this->option('keylength');

        if ($keyLength !== null && trim((string) $keyLength) !== '') {
            return (int) $keyLength;
        }

        $runtimeKeyLength = (int) config('source-encryption.key_length', env('SOURCE_ENCRYPTION_LENGTH', 16));
        $publishedKeyLength = (int) $this->publishedConfigValue('key_length', env('SOURCE_ENCRYPTION_LENGTH', 16));

        if ($this->shouldPreferPublishedConfig('key_length', $runtimeKeyLength, $publishedKeyLength)) {
            $this->warnAboutStaleConfiguration();

            return $publishedKeyLength;
        }

        return $runtimeKeyLength > 0 ? $runtimeKeyLength : max(1, $publishedKeyLength);
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

    private function shouldPreferPublishedConfig(string $key, mixed $runtimeValue, mixed $publishedValue): bool
    {
        if ($this->isEmptyConfigValue($publishedValue)) {
            return false;
        }

        return $runtimeValue === $this->packageConfigValue($key) && $publishedValue !== $runtimeValue;
    }

    private function isEmptyConfigValue(mixed $value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function packageConfigValue(string $key): mixed
    {
        if ($this->packageConfig === null) {
            $config = require __DIR__.'/../config/source-encryption.php';
            $this->packageConfig = is_array($config) ? $config : [];
        }

        return $this->packageConfig[$key] ?? null;
    }

    private function publishedConfigValue(string $key, mixed $default = null): mixed
    {
        if ($this->publishedConfig === null) {
            $path = function_exists('config_path')
                ? config_path('source-encryption.php')
                : base_path('config/source-encryption.php');

            if (! is_file($path)) {
                $this->publishedConfig = [];
            } else {
                $config = require $path;
                $this->publishedConfig = is_array($config) ? $config : [];
            }
        }

        return $this->publishedConfig[$key] ?? $default;
    }

    private function warnAboutStaleConfiguration(): void
    {
        if ($this->warnedAboutStaleConfig) {
            return;
        }

        $message = 'Using values from config/source-encryption.php because the loaded Laravel configuration still matches the package defaults.';

        if (method_exists($this->laravel, 'configurationIsCached') && $this->laravel->configurationIsCached()) {
            $message .= ' Run php artisan config:clear or php artisan config:cache to refresh the cached configuration.';
        }

        $this->warn($message);
        $this->warnedAboutStaleConfig = true;
    }

    private function makeDriver(string $driver): EncryptionDriver
    {
        return match ($driver) {
            'sourceguardian' => new SourceGuardianEncryptionDriver($this),
            'bolt' => new BoltEncryptionDriver($this),
            default => throw new RuntimeException("Unsupported encryption driver [{$driver}]. Supported drivers are [sourceguardian, bolt]."),
        };
    }
}
