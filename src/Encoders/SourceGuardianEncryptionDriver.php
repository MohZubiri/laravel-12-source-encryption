<?php

namespace thedepart3d\LaravelSourceEncryption\Encoders;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class SourceGuardianEncryptionDriver implements EncryptionDriver
{
    public function __construct(private readonly Command $command)
    {
    }

    /**
     * @param  array<int, string>  $sources
     * @param  array<string, mixed>  $options
     */
    public function encrypt(array $sources, string $destination, array $options): void
    {
        $binary = $this->resolveBinary($options['binary'] ?? null);
        $stagingRoot = $this->makeTempDirectory();
        $inputRoot = $stagingRoot.'/input';

        File::makeDirectory($inputRoot, 0755, true);

        try {
            foreach ($sources as $source) {
                $this->stageSource($source, $inputRoot);
            }

            $fileList = $this->buildFileList($inputRoot);

            if ($fileList === []) {
                throw new RuntimeException('SourceGuardian did not find any PHP files to encode in the selected sources.');
            }

            File::put($inputRoot.'/.sourceguardian-filelist.txt', implode(PHP_EOL, $fileList).PHP_EOL);

            $command = $this->buildCommand($binary, base_path($destination));
            [$exitCode, $output] = $this->runCommand($command, $inputRoot);

            if ($exitCode !== 0) {
                throw new RuntimeException("SourceGuardian encoder failed.\n".$output);
            }

            $this->restoreExecutablePermissions($inputRoot, base_path($destination));
        } finally {
            File::deleteDirectory($stagingRoot);
        }
    }

    private function resolveBinary(mixed $configuredBinary): string
    {
        $candidates = [];

        if (is_string($configuredBinary) && trim($configuredBinary) !== '') {
            $candidates[] = trim($configuredBinary);
        }

        array_push($candidates, 'sgencoder', 'sourceguardian');

        foreach (array_unique($candidates) as $candidate) {
            $resolved = $this->resolveExecutablePath($candidate);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        throw new RuntimeException('Unable to locate the SourceGuardian CLI encoder. Set SOURCE_ENCRYPTION_BINARY or source-encryption.binary to the encoder executable path.');
    }

    private function resolveExecutablePath(string $candidate): ?string
    {
        if (str_contains($candidate, DIRECTORY_SEPARATOR) || str_starts_with($candidate, '.')) {
            return is_file($candidate) && is_executable($candidate) ? $candidate : null;
        }

        $lookupCommand = sprintf('command -v %s 2>/dev/null', escapeshellarg($candidate));
        $resolved = trim((string) shell_exec($lookupCommand));

        return $resolved !== '' ? $resolved : null;
    }

    private function makeTempDirectory(): string
    {
        $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'source-encryption-'.bin2hex(random_bytes(8));

        File::makeDirectory($path, 0755, true);

        return $path;
    }

    private function stageSource(string $source, string $inputRoot): void
    {
        $sourcePath = base_path($source);
        $targetPath = $inputRoot.'/'.$source;

        File::ensureDirectoryExists(dirname($targetPath));

        if (File::isFile($sourcePath)) {
            File::copy($sourcePath, $targetPath);

            return;
        }

        File::copyDirectory($sourcePath, $targetPath);
    }

    /**
     * @return array<int, string>
     */
    private function buildFileList(string $inputRoot): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($inputRoot, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relativePath = str_replace($inputRoot.DIRECTORY_SEPARATOR, '', $file->getPathname());

            if ($this->shouldEncode($relativePath, $file->getPathname())) {
                $files[] = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
            }
        }

        sort($files);

        return $files;
    }

    private function shouldEncode(string $relativePath, string $absolutePath): bool
    {
        if (strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) === 'php') {
            return true;
        }

        $handle = @fopen($absolutePath, 'rb');

        if ($handle === false) {
            return false;
        }

        $sample = fread($handle, 512);
        fclose($handle);

        return is_string($sample) && str_contains($sample, '<?php');
    }

    private function buildCommand(string $binary, string $destination): string
    {
        $parts = [
            escapeshellarg($binary),
            '-r',
            '-o', escapeshellarg($destination),
            '-f', escapeshellarg('@.sourceguardian-filelist.txt'),
        ];

        $parts[] = escapeshellarg('*');

        return implode(' ', $parts);
    }

    /**
     * @return array{0:int,1:string}
     */
    private function runCommand(string $command, string $workingDirectory): array
    {
        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $workingDirectory);

        if (! is_resource($process)) {
            throw new RuntimeException('Failed to start the SourceGuardian encoder process.');
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $output = trim(implode(PHP_EOL, array_filter([trim((string) $stdout), trim((string) $stderr)])));

        if ($output !== '') {
            $this->command->line($output);
        }

        return [$exitCode, $output];
    }

    private function restoreExecutablePermissions(string $inputRoot, string $destinationRoot): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($inputRoot, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relativePath = str_replace($inputRoot.DIRECTORY_SEPARATOR, '', $file->getPathname());
            $outputPath = $destinationRoot.DIRECTORY_SEPARATOR.$relativePath;

            if (! is_file($outputPath)) {
                continue;
            }

            $permissions = fileperms($file->getPathname());

            if ($permissions === false) {
                continue;
            }

            chmod($outputPath, $permissions & 0777);
        }
    }
}
