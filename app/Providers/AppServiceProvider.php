<?php

namespace App\Providers;

use App\Services\DocuSign\DocuSignClient;
use App\Services\DocuSign\WebhookVerifier;
use App\Services\GeoIp\CachedGeoIpService;
use App\Services\GeoIp\CloudflareHeaderGeoIpService;
use App\Services\GeoIp\GeoIpService;
use App\Services\GeoIp\MaxMindGeoIpService;
use App\Services\GeoIp\NoOpGeoIpService;
use App\Services\Help\DatabaseHelpArticleSearch;
use App\Services\Help\HelpArticleSearch;
use App\Services\Notifications\HttpSlackNotifier;
use App\Services\Notifications\NoOpSlackNotifier;
use App\Services\Notifications\SlackNotifier;
use App\Services\Payments\Nmi\NmiClient;
use App\Services\Payments\Nmi\NmiService;
use App\Services\Payments\Nmi\NmiWebhookSignatureVerifier;
use App\Services\Payments\WebhookSignatureVerifier;
use App\Services\SupportChat\AnthropicClaudeClient;
use App\Services\SupportChat\ClaudeClient;
use App\Services\SupportChat\SupportChatService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
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

        // NMI payment gateway. The 'demo_dummy' fallback mirrors the old
        // Stripe demo-mode pattern: with no NMI_SECURITY_KEY configured the
        // client is instantiated (so bindings resolve) but the controllers'
        // paymentsConfigured() check keeps the app in demo mode and no real
        // API call is ever made.
        $this->app->singleton(NmiClient::class, function ($app) {
            return new NmiClient(
                $app->make(HttpFactory::class),
                (string) (config('services.nmi.security_key') ?: 'demo_dummy'),
                (string) (config('services.nmi.endpoint') ?: 'https://secure.nmi.com/api/transact.php'),
            );
        });

        $this->app->singleton(NmiService::class, function ($app) {
            return new NmiService($app->make(NmiClient::class));
        });

        $this->app->singleton(WebhookSignatureVerifier::class, function () {
            return new NmiWebhookSignatureVerifier(
                (string) (config('services.nmi.webhook_signing_key') ?: 'whsec_demo_dummy')
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

            // Precedence: MaxMind (richest data, when configured) →
            //             Cloudflare headers (free, when behind CF) →
            //             NoOp (tests, or when explicitly disabled).
            //
            // MaxMind is cached (lookups against a binary DB are expensive
            // and IP→geo is stable per IP). Cloudflare is NOT cached: its
            // result derives from per-request headers, and the cache key
            // (the IP) doesn't capture that — caching could pin an empty
            // result from a CLI/queue context for an hour.
            if ($cityPath && is_readable($cityPath)) {
                return new CachedGeoIpService(
                    new MaxMindGeoIpService($cityPath, $anonPath),
                    $app->make(CacheRepository::class),
                );
            }

            if (env('GEOIP_DISABLE_CLOUDFLARE', false)) {
                return new NoOpGeoIpService();
            }

            return new CloudflareHeaderGeoIpService();
        });
    }

    public function boot(): void
    {
        // TrackAuthEvents subscriber is picked up automatically by Laravel 11's
        // event auto-discovery (any class in app/Listeners/ with a subscribe()
        // method gets registered). Don't call Event::subscribe() here — that
        // causes the listener to fire twice for each auth event.

        // Vacation Club Exchange Detection — fires on every new member
        // enquiry, and on managed-listing property creates. Observer is
        // wrapped in try/catch so detection failures never block creates.
        \App\Models\MemberEnquiry::observe(\App\Observers\ExchangeDetectionObserver::class);
        \App\Models\Property::observe(\App\Observers\ExchangeDetectionObserver::class);
    }
}
