<?php

declare(strict_types=1);

namespace Drupal\Launcher;

use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Process\PhpProcess;

final class Browser
{
    public function __construct(
        private readonly StyleInterface $io,
    ) {}

    public function open(string $url): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Handle escaping ourselves.
            $command = 'start "web" "' . $url . '""';
        } else {
            $url = escapeshellarg($url);

            try {
                $command = match (PHP_OS_FAMILY) {
                    'Linux' => "xdg-open $url",
                    'Darwin' => "open $url",
                };
            } catch (\UnhandledMatchError) {
                $this->io->error("Could not figure out how to open a browser. Visit $url to get started with Drupal CMS.");
                return;
            }
        }

        if ($this->io->isVerbose()) {
            $this->io->writeln("<info>Browser command:</info> $command");
        }

        // Need to escape double quotes in the command so the PHP will work.
        $command = str_replace('"', '\"', $command);
        // Sleep for 2 seconds before opening the browser. This allows the command
        // to start up the PHP built-in web server in the meantime. We use a
        // PhpProcess so that Windows powershell users also get a browser opened
        // for them.
        $process = new PhpProcess("<?php sleep(2); passthru(\"$command\"); ?>");
        $process->start();
    }
}
