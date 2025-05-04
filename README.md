# Laravel AI Jobs

[![Latest Version on Packagist](https://img.shields.io/packagist/v/lenorix/laravel-ai-jobs.svg?style=flat-square)](https://packagist.org/packages/lenorix/laravel-ai-jobs)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/lenorix/laravel-ai-jobs/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/lenorix/laravel-ai-jobs/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/lenorix/laravel-ai-jobs/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/lenorix/laravel-ai-jobs/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/lenorix/laravel-ai-jobs.svg?style=flat-square)](https://packagist.org/packages/lenorix/laravel-ai-jobs)

Queue AI to process in background.

## Support us

Support [this work in GitHub](https://github.com/lenorix/laravel-job-status) or [get in contact](https://lenorix.com/).

## Installation

You can install the package via composer:

```bash
composer require lenorix/laravel-ai-jobs
```

This uses `lenorix/laravel-job-status` package, so you need to use its migrations.

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="job-status-migrations"
php artisan migrate
```

## Usage

After create your `maltekuhr/laravel-gpt` class extending `GPTChat` create a job and extend
 `Lenorix\LaravelAiJobs\GptChatJob` class.

```php
class MyGptJob extends GptChatJob implements ShouldQueue
{
    protected function getGptChatInstance(): GPTChat
    {
        return MyChatGPTChat::make(); // This helps the job to get an instance of your GPTChat.
    }
}
```

And now queue it instead of use `send` method, and save the tracker ID:

```php
$trackerId = MyGptJob::dispatchWithTrack($myGptChat)
    ->getJob()
    ->tracker()
    ->id;
```

When the tracker `isSuccessful` method returns true, get ready an instance of
 your `GPTChat` and use `GptChatFuture` to update that with the result.

```php
GptChatFuture::find($trackerId)
    ->getResultIn($myGptChat);
```

Now you can use that as usually, using `latestMessage` method.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jesus Hernandez](https://github.com/jhg)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
