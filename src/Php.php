<?php

declare(strict_types=1);

namespace Drupal\Launcher;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class Php
{
    public function __construct(
        private readonly array $path,
        private readonly string $version,
        private readonly StyleInterface $io,
    ) {}

    private static function background(string $command): bool
    {
        return PHP_OS_FAMILY === 'Windows'
            ? str_starts_with($command, 'start /b ')
            : str_ends_with($command, ' &');
    }

    public function execute(string|array $command): Process
    {
        $path = $this->getPath();

        if (is_string($command) && self::background($command)) {
            return Process::fromShellCommandline("{$path} {$command}", timeout: null);
        } else {
            return new Process([$path, ...(array) $command]);
        }
    }

    private function getPath(): string
    {
        $path = $this->path;
        if (PHP_OS_FAMILY === 'Windows') {
            $path[] = 'php.exe';
        }
        $path = implode(DIRECTORY_SEPARATOR, $path);

        if (! is_executable($path)) {
            $this->downloadAndExtract($path);
        }
        return $path;
    }

    private function downloadAndExtract(string $path): void
    {
        $url = $this->getUrl();
        $fileName = getcwd() . '/' . basename(parse_url($url, PHP_URL_PATH));

        if (file_exists($fileName)) {
            $this->io->text("Already downloaded: $fileName.");
        } else {
            $this->io->text("Downloading PHP {$this->version}...");

            (new Client())->get($url, [
                RequestOptions::ALLOW_REDIRECTS => true,
                RequestOptions::SINK => $fileName,
            ]);
        }

        $archive = new \PharData($fileName);
        $archive->extractTo(dirname($path));

        $fileSystem = new Filesystem();
        $fileSystem->chmod($path, 0755);
        $fileSystem->remove($fileName);
    }

    private function getUrl(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return "https://windows.php.net/downloads/releases/php-{$this->version}-nts-Win32-vs16-x64.zip";
        } else {
            $arch = $this->getArch();

            return match (PHP_OS_FAMILY) {
                'Darwin' => "https://dl.static-php.dev/static-php-cli/common/php-{$this->version}-cli-macos-{$arch}.tar.gz",
                'Linux' => "https://dl.static-php.dev/static-php-cli/common/php-{$this->version}-cli-linux-{$arch}.tar.gz",
            };
        }
    }

    private function getArch(): string
    {
        $arch = php_uname('m');
        return $arch === 'arm64' ? 'aarch64' : $arch;
    }
}
