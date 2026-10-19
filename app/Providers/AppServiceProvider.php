<?php

namespace App\Providers;

use App\Listeners\SendWelcomeEmail;
use App\Mail\Transports\MailtrapApiTransport;
use App\Models\SystemSetting;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpClientFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Minishlink\WebPush\WebPush;
use NotificationChannels\WebPush\ReportHandler;
use NotificationChannels\WebPush\ReportHandlerInterface;
use NotificationChannels\WebPush\WebPushChannel;
use Psr\Http\Client\ClientInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReportHandlerInterface::class, ReportHandler::class);

        $this->app->when(WebPushChannel::class)
            ->needs(ReportHandlerInterface::class)
            ->give(ReportHandler::class);

        $this->app->when(WebPushChannel::class)
            ->needs(WebPush::class)
            ->give(function (): WebPush {
                $config = config('webpush');
                $auth = [];
                if (! empty($config['vapid']['public_key']) && ! empty($config['vapid']['private_key'])) {
                    $auth['VAPID'] = [
                        'publicKey' => $config['vapid']['public_key'],
                        'privateKey' => $config['vapid']['private_key'],
                        'subject' => $config['vapid']['subject'] ?: url('/'),
                    ];
                    if (! empty($config['vapid']['pem_file'])) {
                        $pem = $config['vapid']['pem_file'];
                        if (Str::startsWith($pem, 'storage')) {
                            $pem = base_path($pem);
                        }
                        $auth['VAPID']['pemFile'] = $pem;
                    }
                }
                $options = $config['client_options'] ?? [];
                $client = version_compare(Application::VERSION, '13.13.0', '<')
                    ? new Client(['timeout' => 30, ...$options])
                    : Http::timeout(30)->withOptions($options)->buildClient();

                /** @var ClientInterface $client */
                return (new WebPush($auth, [], $client, new HttpFactory, new HttpFactory))
                    ->setReuseVAPIDHeaders(true)
                    ->setAutomaticPadding($config['automatic_padding'] ?? true);
            });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        SystemSetting::applyMailDriver();

        Mail::extend('mailtrap', function (array $config): MailtrapApiTransport {
            $svc = config('services.mailtrap', []);

            return new MailtrapApiTransport(
                app(HttpClientFactory::class),
                $svc['endpoint'] ?? 'https://send.api.mailtrap.io/api/send',
                $svc['token'] ?? '',
                $svc['from'] ?? [
                    'address' => 'hello@demomailtrap.co',
                    'name' => config('app.name', 'Allsers'),
                ],
            );
        });

        Event::listen(
            \Illuminate\Auth\Events\Login::class,
            \App\Listeners\UpdateUserLocationOnLogin::class,
        );

        Event::listen(Verified::class, SendWelcomeEmail::class);

        \App\Models\Review::observe(\App\Observers\ReviewObserver::class);
    }
}
