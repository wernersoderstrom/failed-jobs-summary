<?php

namespace Wernersoderstrom\FailedJobsSummary\Console;

use Illuminate\Queue\Console\ListFailedCommand as FrameworkListFailedCommand;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputOption;

class ListFailedCommand extends FrameworkListFailedCommand
{
    public function __construct()
    {
        parent::__construct();

        // Added here rather than in a signature, because depending on the Laravel
        // version the framework defines queue:failed with $name or with $signature.
        $this->addOption(
            'summary',
            null,
            InputOption::VALUE_NONE,
            'Summarize the failed jobs grouped by connection, queue and job name',
        );
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (! $this->option('summary')) {
            parent::handle();

            return self::SUCCESS;
        }

        // getFailedJobs() already applies --queue on Laravel versions that have it.
        $summary = $this->summarizeFailedJobs($this->getFailedJobs());

        if ($this->getDefinition()->hasOption('json') && $this->option('json')) {
            $this->output->writeln($summary->toJson());

            return self::SUCCESS;
        }

        if ($summary->isEmpty()) {
            $this->components->info('No failed jobs found.');

            return self::SUCCESS;
        }

        $this->newLine();

        $this->table(
            ['First Occurrence', 'Last Occurrence', 'Connection@Queue', 'Job', 'Count'],
            $summary->map(fn (array $row) => [
                $row['first_failed_at'],
                $row['last_failed_at'],
                $row['connection'].'@'.$row['queue'],
                $row['class'],
                $row['count'],
            ])->all(),
            'default',
            [4 => (new TableStyle)->setPadType(STR_PAD_LEFT)],
        );

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Summarize the failed jobs grouped by connection, queue and job name.
     *
     * @param  array<int|string, array<int, mixed>>  $jobs  Rows from getFailedJobs(): [id, connection, queue, class, failed_at, ...]
     * @return Collection<int, array{first_failed_at: string, last_failed_at: string, connection: string, queue: string, class: string, count: int}>
     */
    protected function summarizeFailedJobs(array $jobs): Collection
    {
        return (new Collection($jobs))
            ->map(fn (array $job) => [
                'connection' => (string) $job[1],
                'queue' => (string) $job[2],
                'class' => (string) $job[3],
                // Failed job stores use different date formats (the DynamoDB
                // store returns ISO 8601), so normalize them before comparing.
                'failed_at' => Carbon::parse((string) $job[4]),
            ])
            ->groupBy(fn (array $job) => "{$job['connection']}@{$job['queue']}|{$job['class']}")
            ->map(function (Collection $group) {
                $dates = $group
                    ->map(fn (array $job): string => $job['failed_at']->toDateTimeString())
                    ->sort()
                    ->values();

                return [
                    'first_failed_at' => (string) $dates->first(),
                    'last_failed_at' => (string) $dates->last(),
                    'connection' => $group->first()['connection'],
                    'queue' => $group->first()['queue'],
                    'class' => $group->first()['class'],
                    'count' => $group->count(),
                ];
            })
            ->sortBy([['count', 'desc'], ['last_failed_at', 'desc']])
            ->values();
    }
}
