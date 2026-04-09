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

use Illuminate\Support\ServiceProvider;

class EncryptServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/source-encryption.php', 'source-encryption');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $configPath = __DIR__.'/../config/source-encryption.php';

        $this->commands([
            EncryptCommand::class,
            GenerateEncryptionKeyCommand::class,
            InstallCommand::class,
        ]);

        if (function_exists('config_path')) {
            $publishPath = config_path('source-encryption.php');
        } else {
            $publishPath = base_path('config/source-encryption.php');
        }

        $this->publishes([
            $configPath => $publishPath,
        ], 'encryptionConfig');
    }
}
