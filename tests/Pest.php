<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Wernersoderstrom\FailedJobsSummary\Console\ListFailedCommand;
use Wernersoderstrom\FailedJobsSummary\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Record a failed job through the real failer, as the queue worker would.
 */
function failJob(string $class, string $failedAt, string $queue = 'default', string $connection = 'redis'): void
{
    Carbon::setTestNow($failedAt);

    app('queue.failer')->log($connection, $queue, json_encode([
        'uuid' => (string) Str::uuid(),
        'displayName' => $class,
        'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
        'data' => ['commandName' => $class, 'command' => 'O:'.strlen($class).':"'.$class.'":0:{}'],
    ]), 'Exception: boom');

    Carbon::setTestNow();
}

/**
 * Whether the framework's queue:failed command defines the given option.
 * --queue and --json only exist on newer Laravel versions.
 */
function frameworkHasOption(string $option): bool
{
    return (new ListFailedCommand)->getDefinition()->hasOption($option);
}
