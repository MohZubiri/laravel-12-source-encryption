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
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

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
                { --force : Force the operation to run when destination directory already exists }
                { --key= : Custom Encryption Key}
                { --keylength= : Encryption key length }';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypts App Source Files';
    protected $warned = [];
    private ?array $packageConfig = null;
    private ?array $publishedConfig = null;
    private bool $warnedAboutStaleConfig = false;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sources = $this->resolveSources();

        if ($sources === []) {
            $this->error('No source paths are configured. Run php artisan source-encryption:install or pass one or more --source options. If you recently edited config/source-encryption.php, refresh your Laravel configuration cache.');

            return self::FAILURE;
        }

        if (!extension_loaded('bolt')) {
            $output = shell_exec('ls ' . ini_get('extension_dir') . ' | grep -i bolt.so');
            if ($output === NULL) {
                $output = "NO ";
            } else {
                $output = "Yes";
            }
            // Do not change spaces it all aligns perfectly when displayed
            $this->error('                                               ');
            $this->error('  Please install bolt.so https://phpBolt.com   ');
            $this->error('  PHP Version '.phpversion(). '                            ');
            $this->error('  Extension dir: '.ini_get('extension_dir') .'         ');
            $this->error('  Bolt Installed: ' . $output . '                          ');
            $this->error('                                               ');
            return self::FAILURE;
        }

        $destination = $this->resolveDestination();

        $key = $this->resolveKey();

        $keyLength = $this->resolveKeyLength();

        if (empty($key)) {
            $key = bin2hex(random_bytes($keyLength));
        }

        if (!$this->option('force')
            && File::exists(base_path($destination))
            && !$this->confirm("The directory $destination already exists. Delete directory?")
        ) {
            $this->line('Command canceled.');

            return self::FAILURE;
        }

        File::deleteDirectory(base_path($destination));
        File::makeDirectory(base_path($destination));

        foreach ($sources as $source) {
            if (!File::exists(base_path($source))) {
                $this->error("File $source does not exist.");

                return self::FAILURE;
            }

            @File::makeDirectory(base_path($destination.'/'.File::dirname($source)), 493, true);
            if (File::isFile(base_path($source))) {
                $this->encryptFile($source, $destination, $key);
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($source), RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                $filePath = Str::replaceFirst(base_path(), '', $file->getRealPath());
                $this->encryptFile($filePath, $destination, $key);
            }
        }

        $this->info('Encrypting Completed Successfully!');
        $this->info("Destination directory: $destination");

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

    private function resolveKey(): ?string
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

    private function resolveKeyLength(): int
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

    private function encryptFile(string $filePath, string $destination, string $key): void
    {
        if (File::isDirectory(base_path($filePath))) {
            if (!File::exists(base_path($destination.$filePath))) {
                File::makeDirectory(base_path("$destination/$filePath"), 493, true);
            }

            return;
        }

        $extension = Str::after($filePath, '.');

        if ($extension == 'blade.php' || $extension != 'php') {
            if (!in_array($extension, $this->warned)) {
                $this->warn("Encryption of $extension files is not currently supported. These files will be copied without change.");
                $this->warned[] = $extension;
            }
            File::copy(base_path($filePath), base_path("$destination/$filePath"));

            return;
        }

        $fileContents = File::get(base_path($filePath));

        $prepend = "<?php
bolt_decrypt( __FILE__ , '$key'); return 0;
##!!!##";
        $pattern = '/\<\?php/m';
        preg_match($pattern, $fileContents, $matches);
        if (!empty($matches[0])) {
            $fileContents = preg_replace($pattern, '', $fileContents);
        }
        $cipher = bolt_encrypt($fileContents, $key);
        File::isDirectory(base_path(dirname("$destination/$filePath"))) or File::makeDirectory(base_path(dirname("$destination/$filePath")), 0755, true, true);
        File::put(base_path("$destination/$filePath"), $prepend.$cipher);

        unset($cipher);
        unset($fileContents);
    }
}
