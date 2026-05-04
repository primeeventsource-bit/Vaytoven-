<?php

namespace App\Services\DocuSign;

use DocuSign\eSign\Client\ApiClient;
use DocuSign\eSign\Configuration;
use Illuminate\Contracts\Cache\Repository as CacheContract;
use RuntimeException;

/**
 * Authenticates against DocuSign using the JWT Grant flow and hands back
 * a configured ApiClient. Caches the access token so we're not hitting
 * the OAuth endpoint on every API call (DocuSign tokens are valid for
 * one hour by default).
 *
 * Setup prerequisites (one-time, by an operator):
 *   1. Create a DocuSign Integration Key in the DocuSign admin console.
 *   2. Generate an RSA keypair and register the public key on the
 *      Integration Key.
 *   3. Grant impersonation consent the first time by visiting:
 *        {oauth_base}/oauth/auth?response_type=code
 *          &scope=signature%20impersonation
 *          &client_id={integration_key}
 *          &redirect_uri={any_registered_uri}
 *      and signing in as the user the integration will impersonate.
 *   4. Populate the DOCUSIGN_* env vars (see config/services.php and
 *      docs/docusign-setup.md).
 */
class DocuSignClient
{
    private const TOKEN_CACHE_KEY = 'docusign:access_token';

    public function __construct(
        private readonly array $config,
        private readonly CacheContract $cache,
    ) {}

    public function api(): ApiClient
    {
        if (! class_exists(ApiClient::class)) {
            throw new RuntimeException(
                'docusign/esign-client package is not installed. Run "composer require docusign/esign-client" before using this service.'
            );
        }

        $token = $this->accessToken();

        $configuration = new Configuration();
        $configuration->setHost(rtrim($this->config['api_base'], '/') . '/restapi');
        $configuration->addDefaultHeader('Authorization', 'Bearer ' . $token);

        return new ApiClient($configuration);
    }

    public function accountId(): string
    {
        $accountId = $this->config['account_id'] ?? null;
        if (! $accountId) {
            throw new RuntimeException('DOCUSIGN_ACCOUNT_ID is not configured.');
        }
        return $accountId;
    }

    public function accessToken(): string
    {
        return $this->cache->remember(
            self::TOKEN_CACHE_KEY,
            now()->addMinutes(50), // tokens last 60min; refresh slightly early
            fn () => $this->requestNewToken(),
        );
    }

    /**
     * Forget any cached token. Call after a 401 in case the token was revoked
     * server-side (e.g. integration key rotated).
     */
    public function invalidateToken(): void
    {
        $this->cache->forget(self::TOKEN_CACHE_KEY);
    }

    private function requestNewToken(): string
    {
        $integrationKey = $this->config['integration_key'] ?? null;
        $userId         = $this->config['user_id'] ?? null;
        $oauthBase      = $this->config['oauth_base'] ?? null;
        $privateKey     = $this->loadPrivateKey();

        if (! $integrationKey || ! $userId || ! $oauthBase || ! $privateKey) {
            throw new RuntimeException(
                'DocuSign JWT credentials are incomplete. Required env: '
                .'DOCUSIGN_INTEGRATION_KEY, DOCUSIGN_USER_ID, DOCUSIGN_OAUTH_BASE, '
                .'DOCUSIGN_PRIVATE_KEY (or DOCUSIGN_PRIVATE_KEY_PATH).'
            );
        }

        $now = time();
        $payload = [
            'iss'   => $integrationKey,
            'sub'   => $userId,
            'aud'   => parse_url($oauthBase, PHP_URL_HOST),
            'iat'   => $now,
            'exp'   => $now + 3600,
            'scope' => 'signature impersonation',
        ];

        $jwt = $this->encodeJwt($payload, $privateKey);

        $response = \Illuminate\Support\Facades\Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post(rtrim($oauthBase, '/') . '/oauth/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

        if (! $response->ok()) {
            throw new RuntimeException(
                'DocuSign token exchange failed: '.$response->status().' '.$response->body()
            );
        }

        $token = $response->json('access_token');
        if (! $token) {
            throw new RuntimeException('DocuSign token exchange returned no access_token.');
        }

        return $token;
    }

    private function loadPrivateKey(): ?string
    {
        if ($key = $this->config['private_key'] ?? null) {
            return $key;
        }
        if ($path = $this->config['private_key_path'] ?? null) {
            return is_readable($path) ? file_get_contents($path) : null;
        }
        return null;
    }

    private function encodeJwt(array $payload, string $privateKey): string
    {
        if (! class_exists(\Firebase\JWT\JWT::class)) {
            throw new RuntimeException(
                'firebase/php-jwt is required for DocuSign JWT auth. Run "composer require firebase/php-jwt".'
            );
        }
        return \Firebase\JWT\JWT::encode($payload, $privateKey, 'RS256');
    }
}
