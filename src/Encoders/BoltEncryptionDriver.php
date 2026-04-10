<?php

namespace thedepart3d\LaravelSourceEncryption\Encoders;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class BoltEncryptionDriver implements EncryptionDriver
{
    /**
     * @var array<int, string>
     */
    private array $warned = [];

    public function __construct(private readonly Command $command)
    {
    }

    /**
     * @param  array<int, string>  $sources
     * @param  array<string, mixed>  $options
     */
    public function encrypt(array $sources, string $destination, array $options): void
    {
        if (! extension_loaded('bolt')) {
            $output = shell_exec('ls ' . ini_get('extension_dir') . ' | grep -i bolt.so');
            $output = $output === null ? 'NO ' : 'Yes';

            $this->command->error('                                               ');
            $this->command->error('  Please install bolt.so https://phpBolt.com   ');
            $this->command->error('  PHP Version '.phpversion(). '                            ');
            $this->command->error('  Extension dir: '.ini_get('extension_dir') .'         ');
            $this->command->error('  Bolt Installed: ' . $output . '                          ');
            $this->command->error('                                               ');

            throw new RuntimeException('The bolt extension is not loaded.');
        }

        $key = trim((string) ($options['key'] ?? ''));
        $keyLength = (int) ($options['key_length'] ?? 16);

        if ($key === '') {
            $key = bin2hex(random_bytes(max(1, $keyLength)));
        }

        foreach ($sources as $source) {
            if (File::isFile(base_path($source))) {
                $this->encryptFile($source, $destination, $key);

                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($source), RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($files as $file) {
                $filePath = Str::replaceFirst(base_path().'/', '', $file->getRealPath());
                $this->encryptFile($filePath, $destination, $key);
            }
        }
    }

    private function encryptFile(string $filePath, string $destination, string $key): void
    {
        if (File::isDirectory(base_path($filePath))) {
            if (! File::exists(base_path($destination.'/'.$filePath))) {
                File::makeDirectory(base_path($destination.'/'.$filePath), 0755, true);
            }

            return;
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        if ($extension !== 'php' && ! $this->looksLikePhp(base_path($filePath))) {
            if (! in_array($extension, $this->warned, true)) {
                $label = $extension !== '' ? $extension : 'non-php';
                $this->command->warn("Encryption of {$label} files is not currently supported by the bolt driver. These files will be copied without change.");
                $this->warned[] = $extension;
            }

            File::ensureDirectoryExists(base_path(dirname($destination.'/'.$filePath)));
            File::copy(base_path($filePath), base_path($destination.'/'.$filePath));

            return;
        }

        $fileContents = File::get(base_path($filePath));
        $prepend = "<?php\nbolt_decrypt( __FILE__ , '{$key}'); return 0;\n##!!!##";

        if (str_starts_with($fileContents, '<?php')) {
            $fileContents = preg_replace('/^\<\?php\s*/', '', $fileContents, 1) ?? $fileContents;
        }

        $cipher = bolt_encrypt($fileContents, $key);

        File::ensureDirectoryExists(base_path(dirname($destination.'/'.$filePath)));
        File::put(base_path($destination.'/'.$filePath), $prepend.$cipher);
    }

    private function looksLikePhp(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $sample = fread($handle, 512);
        fclose($handle);

        return is_string($sample) && str_contains($sample, '<?php');
    }
}
