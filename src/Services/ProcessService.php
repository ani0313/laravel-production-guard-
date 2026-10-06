<?php

namespace Anivesh\LaravelProductionGuard\Services;

/**
 * The ONLY place in this package allowed to shell out. Every call goes
 * through a hard binary whitelist and escapeshellarg() - nothing from
 * .env, the database, HTTP requests, or CLI arguments is ever passed to a
 * shell. No sudo/root is ever required or attempted. See package SECURITY.md.
 *
 * Not `final`: checkers that shell out (QueueWorkerCheck, SupervisorCheck,
 * etc.) take this as a constructor dependency and need it mockable in
 * tests.
 */
class ProcessService
{
    /**
     * Binaries this service is allowed to execute. Checkers cannot extend
     * this list at runtime - it is intentionally fixed here.
     *
     * @var string[]
     */
    private static $allowedBinaries = [
        'ps',
        'pgrep',
        'supervisorctl',
        'nginx',
        'php-fpm',
        'systemctl',
        'uname',
        'uptime',
    ];

    /**
     * @param string $binary
     * @return bool
     */
    public function isAllowed($binary)
    {
        return in_array($binary, self::$allowedBinaries, true);
    }

    /**
     * Run a whitelisted binary with the given arguments and a hard timeout.
     * Never throws - unexpected failures come back as a non-ok result so a
     * checker can report UNKNOWN instead of crashing the whole run.
     *
     * @param string $binary Must be in the whitelist.
     * @param string[] $args
     * @param float $timeoutSeconds
     * @return array{ok: bool, exitCode: int|null, output: string, error: string}
     */
    public function run($binary, array $args = [], $timeoutSeconds = 3.0)
    {
        if (!$this->isAllowed($binary)) {
            return ['ok' => false, 'exitCode' => null, 'output' => '', 'error' => 'Binary is not whitelisted.'];
        }

        if (!function_exists('proc_open')) {
            return ['ok' => false, 'exitCode' => null, 'output' => '', 'error' => 'proc_open is disabled on this system.'];
        }

        $command = escapeshellcmd($binary);
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg((string) $arg);
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            return ['ok' => false, 'exitCode' => null, 'output' => '', 'error' => 'Unable to start process.'];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $error = '';
        $start = microtime(true);
        $exitCode = null;

        while (true) {
            $status = proc_get_status($process);

            $output .= stream_get_contents($pipes[1]);
            $error .= stream_get_contents($pipes[2]);

            if (!$status['running']) {
                // proc_get_status() only reports the real exit code on the
                // call that first observes termination - any later call
                // (including proc_close()'s own internal check) returns -1.
                // Capture it here, not from proc_close()'s return value.
                $exitCode = $status['exitcode'];
                break;
            }

            if ((microtime(true) - $start) >= $timeoutSeconds) {
                proc_terminate($process);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                return ['ok' => false, 'exitCode' => null, 'output' => $output, 'error' => 'Timed out after ' . $timeoutSeconds . 's.'];
            }

            usleep(20000);
        }

        $output .= stream_get_contents($pipes[1]);
        $error .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return ['ok' => $exitCode === 0, 'exitCode' => $exitCode, 'output' => $output, 'error' => $error];
    }
}
