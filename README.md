# Failed jobs summary for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/wernersoderstrom/failed-jobs-summary.svg?style=flat-square)](https://packagist.org/packages/wernersoderstrom/failed-jobs-summary)
[![GitHub Tests Action Status](https://github.com/wernersoderstrom/failed-jobs-summary/actions/workflows/run-tests.yml/badge.svg)](https://github.com/wernersoderstrom/failed-jobs-summary/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/wernersoderstrom/failed-jobs-summary/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/wernersoderstrom/failed-jobs-summary/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/wernersoderstrom/failed-jobs-summary.svg?style=flat-square)](https://packagist.org/packages/wernersoderstrom/failed-jobs-summary)

When a queue starts failing, `php artisan queue:failed` can list hundreds of rows for the same job, which makes it hard to see which jobs are failing, how often, and whether the problem is still happening.

This package adds a `--summary` option to the `queue:failed` command. It groups failed jobs by connection, queue and job name, and shows the first failure, the last failure and the total count for each group:

```
$ php artisan queue:failed --summary

+---------------------+---------------------+------------------+--------------------------------+-------+
| First Occurrence    | Last Occurrence     | Connection@Queue | Job                            | Count |
+---------------------+---------------------+------------------+--------------------------------+-------+
| 2026-10-10 00:19:10 | 2026-11-09 05:13:35 | redis@default    | App\Jobs\SendOrderConfirmation |   347 |
| 2026-10-10 10:21:19 | 2026-11-09 02:55:27 | sqs@imports      | App\Jobs\ImportOnixFeed        |   212 |
| 2026-10-10 00:53:10 | 2026-11-09 06:10:51 | redis@emails     | App\Jobs\SendNewsletter        |   158 |
| 2026-10-10 00:29:50 | 2026-11-09 07:31:12 | redis@default    | App\Listeners\SyncStockLevels  |    96 |
| 2026-10-10 03:28:59 | 2026-11-09 01:25:39 | database@reports | App\Jobs\GenerateSalesReport   |    41 |
| 2026-10-10 23:17:45 | 2026-11-08 05:13:14 | redis@imports    | App\Jobs\ImportOnixFeed        |    27 |
| 2026-10-10 11:17:19 | 2026-11-08 22:29:55 | sqs@webhooks     | App\Jobs\DeliverWebhook        |    13 |
+---------------------+---------------------+------------------+--------------------------------+-------+
```

Rows are sorted by count, highest first. When counts are equal, the group that failed most recently comes first.

## Installation

You can install the package via composer:

```bash
composer require wernersoderstrom/failed-jobs-summary
```

The service provider is registered automatically. There is nothing to configure.

## Usage

```bash
php artisan queue:failed --summary
```

On Laravel versions where `queue:failed` has the `--queue` and `--json` options, the summary supports them too:

```bash
# Only summarize one queue
php artisan queue:failed --summary --queue=imports

# Output the summary as JSON, for scripts and monitoring
php artisan queue:failed --summary --json
```

```json
[
  {
    "first_failed_at": "2026-10-10 00:19:10",
    "last_failed_at": "2026-11-09 05:13:35",
    "connection": "redis",
    "queue": "default",
    "class": "App\\Jobs\\SendOrderConfirmation",
    "count": 347
  }
]
```

Laravel 12 and early Laravel 13 releases don't have these options on `queue:failed`, so there `--summary` always summarizes every queue as a table. Run `php artisan queue:failed --help` to see which options your version has.

Without `--summary`, `queue:failed` works exactly as before.

## How it works

Laravel resolves the `queue:failed` command from the service container. The package swaps the framework's `ListFailedCommand` for a subclass that adds the `--summary` option, so the command keeps its name and all of its existing options.

Job names come from the framework's own job-name logic, so queued listeners and encrypted jobs are grouped under their real class rather than a wrapper class. The summary works with every failed job store Laravel ships with (database, file and DynamoDB), including their different date formats.

Like the framework's own listing, the summary loads all failed jobs into memory.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Lasse Lehtinen](https://github.com/lasselehtinen)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
