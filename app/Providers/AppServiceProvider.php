<?php

namespace App\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Neon PostgreSQL endpoint injection
        $this->app->bind('db.connector.pgsql', function () {
            return new class extends \Illuminate\Database\Connectors\PostgresConnector {
                protected function getDsn(array $config)
                {
                    $dsn = parent::getDsn($config);
                    if ($endpoint = $config['neon_endpoint'] ?? null) {
                        $dsn .= ";options='endpoint={$endpoint}'";
                    }
                    return $dsn;
                }
            };
        });
    }

    public function boot(): void
    {
        // Register Brevo mail transport
        Mail::extend('brevo', function () {
            $apiKey = config('services.brevo.key');
            $factory = new BrevoTransportFactory();
            return $factory->create(new Dsn('brevo+api', 'default', $apiKey));
        });

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
