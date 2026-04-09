![](logo-img-white.svg)

This package encrypts your php code with [phpBolt](https://phpbolt.com) 

Supports Laravel 12.x and 13.x.

Laravel 12 requires PHP 8.2+, while Laravel 13 requires PHP 8.3+.

* [Installation](#installation)
* [Usage](#usage)

## Installation

### Step 1
You have to [install phpBolt](https://phpbolt.com/download-phpbolt/). The package can be installed without the extension, but the encryption command will refuse to run until `bolt` is available.

### Step 2
Require the package with composer using the following command:
```bash
composer require thedepart3d/laravel-source-encryption
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

The default destination directory is `encrypted-source`. You can change it in `config/source-encryption.php` file.

Also the default encryption key length is `16`. You can change it in `config/source-encryption.php` file. `6` is the recommended key length.

To generate and persist a key in your `.env` file, run:
```bash
php artisan make:encryptionKey
```

This command has these optional options:

| Option      | Description                                                          | Example                 |
|-------------|----------------------------------------------------------------------|-------------------------|
| source      | Path(s) to encrypt                                                   | app,routes,public/a.php |
| destination | Destination directory                                                | encrypted-source               |
| key         | Custom Encryption key                                                |                        |
| keylength   | Encryption key length                                                | 16                       |
| force       | Force the operation to run when destination directory already exists |                         |

### Usage Examples

| Command                                                       | Description                                                                                                       |
|---------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------|
| `php artisan encrypt-source`                                  | Encrypts the configured source paths using the saved destination and key length. If the destination directory exists, asks for delete it. |
| `php artisan encrypt-source --force`                          | Encrypts the configured source paths using the saved destination and key length. If the destination directory exists, deletes it.         |
| `php artisan encrypt-source --source=app`                     | Encrypts `app` directory to the default destination with default keylength.                                       |
| `php artisan encrypt-source --source=app,routes`              | Encrypts only the `app` and `routes` paths without changing the saved config.                                     |
| `php artisan encrypt-source --destination=encrypted-source`               | Encrypts the configured source paths to `encrypted-source` directory.                                                  |
| `php artisan encrypt-source --destination=encrypted-source --keylength=8` | Encrypts the configured source paths to `encrypted-source` directory and the encryption key length is `8`.                                 |
| `php artisan encrypt-source --destination=encrypted-source --key="somecustomstrongstring"` | Encrypts the configured source paths to `encrypted-source` directory and the encryption key is `somecustomstrongstring` |               

Updated with ♥ by The Departed 

Support can be shared by staring this repository.
