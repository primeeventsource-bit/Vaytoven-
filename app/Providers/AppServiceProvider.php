<?php

namespace App\Providers;

use App\Services\DocuSign\DocuSignClient;
use App\Services\DocuSign\WebhookVerifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DocuSignClient::class, function ($app) {
            return new DocuSignClient(
                config('services.docusign', []),
                $app->make(\Illuminate\Contracts\Cache\Repository::class),
            );
        });

        $this->app->singleton(WebhookVerifier::class, function ($app) {
            return new WebhookVerifier(
                (array) config('services.docusign.hmac_keys', []),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
