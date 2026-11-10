<?php

namespace Wernersoderstrom\FailedJobsSummary;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Console\ListFailedCommand as FrameworkListFailedCommand;
use Illuminate\Support\ServiceProvider;
use Wernersoderstrom\FailedJobsSummary\Console\ListFailedCommand;

class FailedJobsSummaryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Laravel resolves queue:failed from the container, so swapping the
        // framework's command for our subclass adds --summary to it while
        // keeping the command's name and all of its existing options.
        $this->app->extend(
            FrameworkListFailedCommand::class,
            fn (FrameworkListFailedCommand $command, Application $app) => $app->make(ListFailedCommand::class),
        );
    }
}
