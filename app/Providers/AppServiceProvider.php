<?php

namespace App\Providers;

use App\Services\DocuSign\DocuSignClient;
use App\Services\DocuSign\WebhookVerifier;
use App\Services\GeoIp\CachedGeoIpService;
use App\Services\GeoIp\GeoIpService;
use App\Services\GeoIp\MaxMindGeoIpService;
use App\Services\GeoIp\NoOpGeoIpService;
use App\Services\Help\DatabaseHelpArticleSearch;
use App\Services\Help\HelpArticleSearch;
use App\Services\Notifications\HttpSlackNotifier;
use App\Services\Notifications\NoOpSlackNotifier;
use App\Services\Notifications\SlackNotifier;
use App\Services\Payments\Stripe\StripeService;
use App\Services\Payments\Stripe\StripeWebhookSignatureVerifier;
use App\Services\Payments\Stripe\WebhookSignatureVerifier;
use App\Services\SupportChat\AnthropicClaudeClient;
use App\Services\SupportChat\ClaudeClient;
use App\Services\SupportChat\SupportChatService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
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

        // GeoIP — MaxMind in production (when MAXMIND_MMDB_PATH points to a
        // readable .mmdb file), NoOp otherwise (CI, fresh local dev, etc).
        // Always wrapped in CachedGeoIpService so repeat lookups hit Redis.
        $this->app->singleton(ClaudeClient::class, function () {
            return new AnthropicClaudeClient(
                apiKey: (string) (config('services.anthropic.api_key') ?? ''),
            );
        });

        $this->app->singleton(SupportChatService::class, function ($app) {
            return new SupportChatService($app->make(ClaudeClient::class));
        });

        // HelpArticleSearch — DB-backed by default. The interface is the swap
        // point for Meilisearch when the article catalogue grows beyond what
        // a few LIKE queries comfortably serve. Singleton so request-scoped
        // sessions reuse a single search instance.
        $this->app->singleton(HelpArticleSearch::class, function () {
            return new DatabaseHelpArticleSearch();
        });

        // SlackNotifier — wires HttpSlackNotifier when SLACK_OPS_WEBHOOK_URL is
        // set, NoOp otherwise. Bound as singleton so jobs share the underlying
        // Http client instance across a worker's lifetime.
        $this->app->singleton(SlackNotifier::class, function ($app) {
            $url = (string) (config('services.slack.ops_webhook_url') ?? '');
            if ($url === '') {
                return new NoOpSlackNotifier();
            }
            return new HttpSlackNotifier($app->make(HttpFactory::class), $url);
        });

        $this->app->singleton(GeoIpService::class, function ($app) {
            $cityPath = config('services.maxmind.mmdb_path');
            $anonPath = config('services.maxmind.anonymous_mmdb_path');

            $upstream = ($cityPath && is_readable($cityPath))
                ? new MaxMindGeoIpService($cityPath, $anonPath)
                : new NoOpGeoIpService();

            return new CachedGeoIpService(
                $upstream,
                $app->make(CacheRepository::class),
            );
        });
    }

    public function boot(): void
    {
        // TrackAuthEvents subscriber is picked up automatically by Laravel 11's
        // event auto-discovery (any class in app/Listeners/ with a subscribe()
        // method gets registered). Don't call Event::subscribe() here — that
        // causes the listener to fire twice for each auth event.
    }
}
