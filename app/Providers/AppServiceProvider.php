<?php

namespace App\Providers;

use App\Services\DocuSign\DocuSignClient;
use App\Services\DocuSign\WebhookVerifier;
use App\Services\Payments\Stripe\StripeService;
use App\Services\Payments\Stripe\StripeWebhookSignatureVerifier;
use App\Services\Payments\Stripe\WebhookSignatureVerifier;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

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

        $this->app->singleton(StripeClient::class, function () {
            return new StripeClient(config('services.stripe.secret') ?: 'sk_test_dummy');
        });

        $this->app->singleton(StripeService::class, function ($app) {
            return new StripeService($app->make(StripeClient::class));
        });

        $this->app->singleton(WebhookSignatureVerifier::class, function () {
            return new StripeWebhookSignatureVerifier(
                config('services.stripe.webhook_secret') ?: 'whsec_test_dummy'
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
