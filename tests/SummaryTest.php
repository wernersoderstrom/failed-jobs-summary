<?php

use Illuminate\Queue\Console\ListFailedCommand as FrameworkListFailedCommand;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Artisan;
use Wernersoderstrom\FailedJobsSummary\Console\ListFailedCommand;

it('replaces the framework queue:failed command with the summary-aware subclass', function () {
    expect(app(FrameworkListFailedCommand::class))->toBeInstanceOf(ListFailedCommand::class)
        ->and(Artisan::all()['queue:failed'])->toBeInstanceOf(ListFailedCommand::class)
        ->and(Artisan::all()['queue:failed']->getDefinition()->hasOption('summary'))->toBeTrue();
});

it('summarizes failed jobs grouped by connection, queue and job name', function () {
    failJob('App\\Jobs\\SendEmail', '2026-05-18 12:00:00');
    failJob('App\\Jobs\\SendEmail', '2026-05-17 08:30:00');
    failJob('App\\Jobs\\SendEmail', '2026-05-19 18:45:00');
    failJob('App\\Jobs\\ProcessPodcast', '2026-05-18 09:00:00');
    failJob('App\\Jobs\\SendEmail', '2026-05-18 10:00:00', 'emails');
    failJob('App\\Jobs\\SendEmail', '2026-05-18 11:00:00', 'default', 'sqs');

    Artisan::call('queue:failed', ['--summary' => true]);
    $output = Artisan::output();

    expect($output)
        ->toContain('First Occurrence')
        ->toContain('Last Occurrence')
        ->toContain('Connection@Queue')
        ->toMatch('/2026-05-17 08:30:00 \| 2026-05-19 18:45:00 \| redis@default\s*\| App\\\\Jobs\\\\SendEmail\s*\|\s+3 \|/')
        ->toMatch('/2026-05-18 09:00:00 \| 2026-05-18 09:00:00 \| redis@default\s*\| App\\\\Jobs\\\\ProcessPodcast\s*\|\s+1 \|/')
        ->toMatch('/redis@emails\s*\| App\\\\Jobs\\\\SendEmail\s*\|\s+1 \|/')
        ->toMatch('/sqs@default\s*\| App\\\\Jobs\\\\SendEmail\s*\|\s+1 \|/');

    // Highest count first; ties are broken by the most recent failure.
    $rows = array_values(array_filter(
        explode("\n", $output),
        fn ($line) => preg_match('/^\| \d{4}-/', $line),
    ));

    expect($rows)->toHaveCount(4)
        ->and($rows[0])->toContain('redis@default')->toContain('SendEmail')
        ->and($rows[1])->toContain('sqs@default')
        ->and($rows[2])->toContain('redis@emails')
        ->and($rows[3])->toContain('ProcessPodcast');
});

it('shows a message when there are no failed jobs', function () {
    Artisan::call('queue:failed', ['--summary' => true]);

    expect(Artisan::output())->toContain('No failed jobs found.');
});

it('normalizes ISO 8601 dates such as those returned by the DynamoDB failer', function () {
    $failer = Mockery::mock(FailedJobProviderInterface::class);
    $failer->shouldReceive('all')->andReturn([
        (object) [
            'id' => 'b',
            'connection' => 'sqs',
            'queue' => 'imports',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\ImportFeed', 'data' => ['command' => 'x']]),
            'exception' => 'Exception: boom',
            'failed_at' => '2026-05-19T18:45:00+0000',
        ],
        (object) [
            'id' => 'a',
            'connection' => 'sqs',
            'queue' => 'imports',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\ImportFeed', 'data' => ['command' => 'x']]),
            'exception' => 'Exception: boom',
            'failed_at' => '2026-05-17T08:30:00+0000',
        ],
    ]);
    app()->instance('queue.failer', $failer);

    Artisan::call('queue:failed', ['--summary' => true]);

    expect(Artisan::output())
        ->toMatch('/2026-05-17 08:30:00 \| 2026-05-19 18:45:00 \| sqs@imports\s*\| App\\\\Jobs\\\\ImportFeed\s*\|\s+2 \|/');
});

it('outputs the summary as JSON', function () {
    failJob('App\\Jobs\\SendEmail', '2026-05-18 12:00:00');
    failJob('App\\Jobs\\SendEmail', '2026-05-17 08:30:00');
    failJob('App\\Jobs\\ProcessPodcast', '2026-05-18 09:00:00', 'podcasts', 'database');

    Artisan::call('queue:failed', ['--summary' => true, '--json' => true]);

    expect(json_decode(Artisan::output(), true))->toBe([
        [
            'first_failed_at' => '2026-05-17 08:30:00',
            'last_failed_at' => '2026-05-18 12:00:00',
            'connection' => 'redis',
            'queue' => 'default',
            'class' => 'App\\Jobs\\SendEmail',
            'count' => 2,
        ],
        [
            'first_failed_at' => '2026-05-18 09:00:00',
            'last_failed_at' => '2026-05-18 09:00:00',
            'connection' => 'database',
            'queue' => 'podcasts',
            'class' => 'App\\Jobs\\ProcessPodcast',
            'count' => 1,
        ],
    ]);
})->skip(fn () => ! frameworkHasOption('json'), 'queue:failed has no --json option on this Laravel version');

it('outputs an empty JSON array when there are no failed jobs', function () {
    Artisan::call('queue:failed', ['--summary' => true, '--json' => true]);

    expect(json_decode(Artisan::output(), true))->toBe([]);
})->skip(fn () => ! frameworkHasOption('json'), 'queue:failed has no --json option on this Laravel version');

it('only summarizes the queue given with --queue', function () {
    failJob('App\\Jobs\\SendEmail', '2026-05-18 12:00:00', 'default');
    failJob('App\\Jobs\\SendEmail', '2026-05-18 13:00:00', 'emails');

    Artisan::call('queue:failed', ['--summary' => true, '--queue' => 'emails']);

    expect(Artisan::output())
        ->toContain('redis@emails')
        ->not->toContain('redis@default');
})->skip(fn () => ! frameworkHasOption('queue'), 'queue:failed has no --queue option on this Laravel version');

it('leaves the normal queue:failed listing unchanged', function () {
    failJob('App\\Jobs\\SendEmail', '2026-05-18 12:00:00');

    Artisan::call('queue:failed');

    expect(Artisan::output())
        ->toContain('redis@default')
        ->toContain('App\\Jobs\\SendEmail')
        ->not->toContain('First Occurrence');
});
