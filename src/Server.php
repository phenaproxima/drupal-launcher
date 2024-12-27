<?php

declare(strict_types=1);

namespace Drupal\Launcher;

use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Process\Process;

final class Server
{
    public function __construct(
        private readonly string $webRoot,
        private readonly string $php,
        private readonly string $host,
        private readonly StyleInterface $io,
    ) {}

    public function start(Browser $browser): int
    {
        $port = $this->findAvailablePort();

        if ($port === false) {
            $this->io->error('Could not start the web server because there were no open ports.');
            return 1;
        }

        $hostWithPort = $this->host . ':' . $port;
        $browser->open("http://$hostWithPort");

        $command = [
            $this->php,
            '-S',
            $hostWithPort,
            '.ht.router.php',
        ];
        return new Process($command)
            ->setWorkingDirectory($this->webRoot)
            ->setTimeout(null)
            ->run();
    }

    private function findAvailablePort(): int|false
    {
        $port = 8888;
        while ($port >= 8888 && $port <= 9999) {
            $connection = @fsockopen($this->host, $port);
            if (is_resource($connection)) {
                // Port is being used.
                fclose($connection);
            }
            else {
                // Port is available.
                return $port;
            }
            $port++;
        }
        return false;
    }
}
