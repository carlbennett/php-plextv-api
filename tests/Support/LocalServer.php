<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

namespace CarlBennett\PlexTvAPI\Tests\Support;

/**
 * Runs tests/fixtures/server.php on PHP's built-in web server, so the curl transport can be tested offline.
 */
class LocalServer
{
    private $process;
    public readonly string $base_url;

    public function __construct()
    {
        $router = \dirname(__DIR__) . '/fixtures/server.php';

        for ($attempt = 0; $attempt < 5; ++$attempt)
        {
            $port = \random_int(20000, 60000);
            $command = [\PHP_BINARY, '-S', \sprintf('127.0.0.1:%d', $port), $router];
            // several workers, so a deliberately slow request does not block the tests that follow it
            $env = \getenv() + ['PHP_CLI_SERVER_WORKERS' => '4'];
            $this->process = \proc_open($command, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, $env);

            for ($wait = 0; $wait < 50; ++$wait)
            {
                $socket = @\fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
                if ($socket !== false)
                {
                    \fclose($socket);
                    $this->base_url = \sprintf('http://127.0.0.1:%d', $port);
                    return;
                }
                if (!\proc_get_status($this->process)['running']) break;
                \usleep(100000);
            }

            $this->stop();
        }

        throw new \RuntimeException('could not start the local test server');
    }

    public function __destruct()
    {
        $this->stop();
    }

    public function stop(): void
    {
        if (\is_resource($this->process))
        {
            \proc_terminate($this->process);
            \proc_close($this->process);
        }
    }

    public function url(string $path): string
    {
        return $this->base_url . $path;
    }
}
