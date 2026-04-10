![](logo-img-white.svg)

This package encrypts your PHP source code with an encoder/loader workflow.

The default driver in this branch is [SourceGuardian](https://www.sourceguardian.com/), because it preserves normal PHP file semantics for files that are loaded with `require`/`include` and must return values such as `config/*.php` and `bootstrap/app.php`.

The legacy `bolt` driver is still available for projects that already depend on phpBolt, but it is no longer the recommended default.

Supports Laravel 12.x and 13.x.

Laravel 12 requires PHP 8.2+, while Laravel 13 requires PHP 8.3+.

* [Installation](#installation)
* [Usage](#usage)

## Installation

### Step 1
Install your encoder/loader.

Recommended:
- Install SourceGuardian Encoder and Loader.
- Make sure the CLI encoder binary is available in `PATH` as `sgencoder` or `sourceguardian`, or set `SOURCE_ENCRYPTION_BINARY`.

Legacy:
- Install [phpBolt](https://phpbolt.com/download-phpbolt/) only if you explicitly plan to use `driver=bolt`.

### Step 2
Require the package with composer using the following command:
```bash
composer require mohzubiri/laravel-12-source-encryption
```
### Step 3
#### For Laravel
The service provider will automatically get registered. Or you may manually add the service provider in your `config/app.php` file:
```php
'providers' => [
    // ...
    \thedepart3d\LaravelSourceEncryption\EncryptServiceProvider::class,
];
```
### Step 4 (Optional)
Run the installer to choose the files and directories you want to encrypt and write the package config:
```bash
php artisan source-encryption:install
```

For a non-interactive SourceGuardian setup you can also run:
```bash
php artisan source-encryption:install \
  --driver=sourceguardian \
  --binary=/usr/local/bin/sgencoder \
  --source=app \
  --source=config \
  --source=routes \
  --source=bootstrap/app.php \
  --source=artisan
```

You can still publish the config file manually with this following command:
```bash
php artisan vendor:publish --provider="thedepart3d\LaravelSourceEncryption\EncryptServiceProvider" --tag=encryptionConfig
```

## Usage
Open terminal in project root and run this command: 
```bash
php artisan encrypt-source
```
This command encrypts the files and directories configured in `config/source-encryption.php`. You can create that file interactively with `php artisan source-encryption:install`, or pass paths directly with `--source`.

### How To Choose Files And Directories To Encrypt

You have 3 ways to define what should be encrypted.

#### Option 1: Interactive setup
Run:
```bash
php artisan source-encryption:install
```

The command will ask:
- Which files or directories should be encrypted?
- What is the destination directory?
- Which driver should be used?
- Where the SourceGuardian binary is, if you want to pin it in config

When it asks for source paths, enter relative paths separated by commas.

Example:
```text
app, routes, config/app.php, public/index.php
```

#### Option 2: Non-interactive setup
You can pass the paths directly when running the installer.

Example:
```bash
php artisan source-encryption:install --source=app --source=routes/api.php --source=config/app.php
```

This writes the selected paths into `config/source-encryption.php`.

#### Option 3: Manual config
You can edit `config/source-encryption.php` manually and put the paths inside the `source` array.

Example:
```php
return [
    'source' => [
        'app',
        'config',
        'routes',
        'bootstrap/app.php',
        'public/index.php',
        'artisan',
    ],
    'destination' => 'encrypted-source',
    'driver' => env('SOURCE_ENCRYPTION_DRIVER', 'sourceguardian'),
    'binary' => env('SOURCE_ENCRYPTION_BINARY'),
    'key' => env('SOURCE_ENCRYPTION_KEY'),
    'key_length' => (int) env('SOURCE_ENCRYPTION_LENGTH', 16),
];
```

Important notes:
- Paths must be relative to the Laravel project root.
- You can mix directories and single files in the same `source` array.
- If a path does not exist, the installer will reject it.
- If no sources are configured, `php artisan encrypt-source` will stop and ask you to configure them first.
- If you edit `config/source-encryption.php` in an app that uses cached config, refresh Laravel's config cache before running `php artisan encrypt-source`.
- `sourceguardian` is the recommended driver for files that must return values when included, such as `config/*.php` and `bootstrap/app.php`.
- `make:encryptionKey` is only relevant for the legacy `bolt` driver.

The default destination directory is `encrypted-source`. You can change it in `config/source-encryption.php` file.

The default driver is `sourceguardian`. You can switch to `bolt` in `config/source-encryption.php` or with `--driver=bolt`.

If you use the legacy `bolt` driver, the default encryption key length is `16`. You can change it in `config/source-encryption.php` file.

To generate and persist a key in your `.env` file for the legacy `bolt` driver, run:
```bash
php artisan make:encryptionKey
```

This command has these optional options:

| Option      | Description                                                          | Example                 |
|-------------|----------------------------------------------------------------------|-------------------------|
| source      | Path(s) to encrypt                                                   | app,routes,public/a.php |
| destination | Destination directory                                                | encrypted-source        |
| driver      | Encoder driver                                                       | sourceguardian          |
| binary      | Path to external encoder binary                                      | /usr/local/bin/sgencoder |
| key         | Custom Encryption key for `bolt`                                     |                         |
| keylength   | Encryption key length for `bolt`                                     | 16                      |
| force       | Force the operation to run when destination directory already exists |                         |

### Usage Examples

| Command                                                       | Description                                                                                                       |
|---------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------|
| `php artisan encrypt-source` | Encrypts the configured source paths using the saved driver and destination. |
| `php artisan encrypt-source --force` | Encrypts the configured source paths and deletes the destination first if it already exists. |
| `php artisan encrypt-source --source=app --source=config --source=bootstrap/app.php --force` | Encrypts multiple sources passed via repeated `--source` options. |
| `php artisan encrypt-source --driver=sourceguardian --binary=/usr/local/bin/sgencoder --force` | Encrypts with SourceGuardian using an explicit encoder binary path. |
| `php artisan encrypt-source --driver=bolt --source=app --key="somecustomstrongstring"` | Encrypts with the legacy bolt driver and a custom key. |

Updated with ♥ by The Departed 

Support can be shared by staring this repository.
