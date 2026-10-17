<?php

declare(strict_types=1);

namespace Src\Fintech\OpenBanking;

use Src\Fintech\OpenBanking\Exceptions\OpenBankingException;
use Src\Fintech\OpenBanking\Exceptions\TokenExpiredException;

/**
 * TrueLayerClient — Universal Open Banking AIS (Account Information Services) Client.
 *
 * Provides high-speed, defensively typed connectivity to TrueLayer's Data API:
 * 1. OAuth2 consent flow and provider selection
 * 2. Token exchange and refresh token rotation
 * 3. Verified bank accounts, balances, and 90-365 day transaction retrieval
 */
class TrueLayerClient
{
    public const DEFAULT_SCOPES = 'info accounts balance transactions offline_access';

    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private bool $isSandbox;
    private string $authHost;
    private string $apiHost;
    private int $connectTimeout;
    private int $timeout;

    /**
     * @param array{
     *     client_id?: string,
     *     client_secret?: string,
     *     redirect_uri?: string,
     *     sandbox?: bool,
     *     connect_timeout?: int,
     *     timeout?: int
     * } $config
     */
    public function __construct(array $config = [])
    {
        $this->clientId = (string)($config['client_id'] ?? $_ENV['TRUELAYER_CLIENT_ID'] ?? getenv('TRUELAYER_CLIENT_ID') ?: '');
        $this->clientSecret = (string)($config['client_secret'] ?? $_ENV['TRUELAYER_CLIENT_SECRET'] ?? getenv('TRUELAYER_CLIENT_SECRET') ?: '');
        $this->redirectUri = (string)($config['redirect_uri'] ?? $_ENV['TRUELAYER_REDIRECT_URI'] ?? getenv('TRUELAYER_REDIRECT_URI') ?: '');
        
        $envMode = (string)($config['sandbox'] ?? $_ENV['TRUELAYER_ENV'] ?? getenv('TRUELAYER_ENV') ?: 'sandbox');
        $this->isSandbox = strtolower((string)$envMode) !== 'live' && $envMode !== false;

        $this->authHost = $this->isSandbox ? 'https://auth.truelayer-sandbox.com' : 'https://auth.truelayer.com';
        $this->apiHost = $this->isSandbox ? 'https://api.truelayer-sandbox.com' : 'https://api.truelayer.com';

        $this->connectTimeout = (int)($config['connect_timeout'] ?? 5);
        $this->timeout = (int)($config['timeout'] ?? 10);
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '' && $this->redirectUri !== '';
    }

    public function isSandbox(): bool
    {
        return $this->isSandbox;
    }

    /**
     * Generates the OAuth 2.0 banking consent URL.
     *
     * @param string $state Cryptographically secure state string (CSRF guard)
     * @param string|null $providers Specific bank providers (defaults to UK Open Banking)
     */
    public function getAuthUrl(string $state, ?string $providers = null): string
    {
        $selectedProviders = $providers ?? ($this->isSandbox ? 'uk-cs-mock uk-ob-all uk-oauth-all' : 'uk-ob-all uk-oauth-all');

        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'scope' => self::DEFAULT_SCOPES,
            'redirect_uri' => $this->redirectUri,
            'providers' => $selectedProviders,
            'state' => $state,
        ];

        return $this->authHost . '/?' . http_build_query($params);
    }

    /**
     * Exchanges an authorization code for an access token and refresh token.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string}
     * @throws OpenBankingException
     */
    public function exchangeCode(string $code): array
    {
        $payload = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
        ];

        /** @var array{access_token: string, refresh_token: string, expires_in: int, token_type: string} */
        return $this->executeTokenRequest($payload);
    }

    /**
     * Rotates an expired access token using a refresh token.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string}
     * @throws OpenBankingException
     */
    public function refreshToken(string $refreshToken): array
    {
        $payload = [
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
        ];

        /** @var array{access_token: string, refresh_token: string, expires_in: int, token_type: string} */
        return $this->executeTokenRequest($payload);
    }

    /**
     * Retrieves applicant metadata and connected bank institution identity.
     *
     * @return array<string, mixed>
     * @throws OpenBankingException
     */
    public function getMe(string $accessToken): array
    {
        $results = $this->executeApiGet('/data/v1/me', $accessToken);
        return is_array($results[0] ?? null) ? $results[0] : [];
    }

    /**
     * Fetches all accounts linked under the verified bank connection.
     *
     * @return list<array<string, mixed>>
     * @throws OpenBankingException
     */
    public function getAccounts(string $accessToken): array
    {
        return $this->executeApiGet('/data/v1/accounts', $accessToken);
    }

    /**
     * Fetches current real-time balances for an account.
     *
     * @return array<string, mixed>
     * @throws OpenBankingException
     */
    public function getBalance(string $accessToken, string $accountId): array
    {
        $results = $this->executeApiGet('/data/v1/accounts/' . rawurlencode($accountId) . '/balance', $accessToken);
        return is_array($results[0] ?? null) ? $results[0] : [];
    }

    /**
     * Fetches historical transactions for an account with optional ISO 8601 date filters.
     *
     * @param string|null $from ISO 8601 string (e.g. 2026-07-01T00:00:00Z)
     * @param string|null $to ISO 8601 string (e.g. 2026-10-17T23:59:59Z)
     * @return list<array<string, mixed>>
     * @throws OpenBankingException
     */
    public function getTransactions(string $accessToken, string $accountId, ?string $from = null, ?string $to = null): array
    {
        $path = '/data/v1/accounts/' . rawurlencode($accountId) . '/transactions';
        $query = [];
        if ($from !== null && $from !== '') {
            $query['from'] = $from;
        }
        if ($to !== null && $to !== '') {
            $query['to'] = $to;
        }
        if (!empty($query)) {
            $path .= '?' . http_build_query($query);
        }

        return $this->executeApiGet($path, $accessToken);
    }

    /**
     * Executes HTTP POST token requests against TrueLayer Auth Server.
     *
     * @param array<string, string> $fields
     * @return array<string, mixed>
     * @throws OpenBankingException
     */
    protected function executeTokenRequest(array $fields): array
    {
        $url = $this->authHost . '/connect/token';

        $ch = curl_init($url);
        if ($ch === false) {
            throw new OpenBankingException('Failed to initialise cURL for TrueLayer token request.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            throw new OpenBankingException('TrueLayer token network failure: ' . ($curlError ?: 'unknown'));
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            throw new OpenBankingException('TrueLayer token response malformed (HTTP ' . $httpCode . ').');
        }

        if ($httpCode >= 400 || isset($decoded['error'])) {
            $msg = (string)($decoded['error_description'] ?? $decoded['error'] ?? 'Token error');
            if ($httpCode === 401 || stripos($msg, 'invalid_grant') !== false) {
                throw new TokenExpiredException('TrueLayer token expired or revoked: ' . $msg, $httpCode);
            }
            throw new OpenBankingException('TrueLayer token rejected (' . $httpCode . '): ' . $msg, $httpCode);
        }

        return $decoded;
    }

    /**
     * Executes authenticated HTTP GET requests against TrueLayer Data API.
     *
     * @return list<array<string, mixed>>
     * @throws OpenBankingException
     */
    protected function executeApiGet(string $path, string $accessToken): array
    {
        $url = $this->apiHost . $path;

        $ch = curl_init($url);
        if ($ch === false) {
            throw new OpenBankingException('Failed to initialise cURL for TrueLayer API request.');
        }

        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . trim($accessToken),
                'Accept: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            throw new OpenBankingException('TrueLayer API network error: ' . ($curlError ?: 'unknown'));
        }

        if ($httpCode === 401) {
            throw new TokenExpiredException('TrueLayer API token expired or unauthorized (HTTP 401).', 401);
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            throw new OpenBankingException('TrueLayer API response malformed (HTTP ' . $httpCode . ').');
        }

        if ($httpCode >= 400) {
            $err = (string)($decoded['error_description'] ?? $decoded['message'] ?? 'API error');
            throw new OpenBankingException('TrueLayer API returned error (' . $httpCode . '): ' . $err, $httpCode);
        }

        $results = $decoded['results'] ?? [];
        if (!is_array($results)) {
            return [];
        }

        /** @var list<array<string, mixed>> */
        return array_values($results);
    }
}
