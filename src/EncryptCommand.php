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
                { --source= : Path(s) to encrypt }
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

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sources = $this->resolveSources();

        if ($sources === []) {
            $this->error('No source paths are configured. Run php artisan source-encryption:install or pass one or more --source options.');

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

        if (empty($this->option('destination'))) {
            $destination = config('source-encryption.destination', 'encrypted-source');
        } else {
            $destination = $this->option('destination');
        }

        if (empty($this->option('key'))) {
            $key = config('source-encryption.key');
        } else {
            $key = $this->option('key');
        }

        if (empty($this->option('keylength'))) {
            $keyLength = (int) config('source-encryption.key_length', env('SOURCE_ENCRYPTION_LENGTH', 16));
        } else {
            $keyLength = (int) $this->option('keylength');
        }

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
        if (empty($this->option('source'))) {
            $sources = config('source-encryption.source', []);
        } else {
            $sources = explode(',', $this->option('source'));
        }

        return array_values(array_filter(array_map(static fn (string $source): string => trim($source), $sources)));
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
