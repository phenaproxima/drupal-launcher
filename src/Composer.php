<?php

declare(strict_types=1);

namespace Drupal\Launcher;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class Composer
{
    private readonly string $path;

    public function __construct(
        array $path,
        private readonly string $version,
        private readonly Php $php,
        private readonly StyleInterface $io,
    ) {
        $this->path = implode(DIRECTORY_SEPARATOR, $path);
    }

    public function execute(array $command): Process
    {
        if (! is_executable($this->path)) {
            $this->download();
        }
        return $this->php->execute([$this->path, ...$command]);
    }

    private function download(): void
    {
        $fileSystem = new Filesystem();

        if (file_exists($this->path)) {
            $this->io->text("Already downloaded: {$this->path}.");
        } else {
            $fileSystem->mkdir(dirname($this->path));

            $this->io->text("Downloading Composer {$this->version}...");
            $url = "https://github.com/composer/composer/releases/download/{$this->version}/composer.phar";

            (new Client())->get($url, [
                RequestOptions::ALLOW_REDIRECTS => true,
                RequestOptions::SINK => $this->path,
            ]);
        }
        $fileSystem->chmod($this->path, 0755);
    }

}
