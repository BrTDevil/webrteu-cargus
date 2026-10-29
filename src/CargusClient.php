<?php

declare(strict_types=1);

namespace WeBRTeu\Cargus;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use WeBRTeu\Cargus\Exceptions\CargusException;
use WeBRTeu\Shipping\Exceptions\AuthenticationException;
use WeBRTeu\Shipping\Support\CachedToken;
use WeBRTeu\Shipping\Support\Redactor;

/**
 * HTTP brut spre Cargus: `Ocp-Apim-Subscription-Key` + `Authorization: Bearer` pe fiecare cerere,
 * token din LoginUser în cache, un singur re-login la 401. Fără alt retry.
 */
class CargusClient
{
    public function tokenKey(): string
    {
        return 'cargus:token:'.Config::string('cargus.username', '');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $path, array $query = []): Response
    {
        return $this->send('GET', $path, ['query' => $query]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     */
    public function post(string $path, array $body, array $query = []): Response
    {
        return $this->send('POST', $path, ['json' => $body, 'query' => $query]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function delete(string $path, array $query = []): Response
    {
        return $this->send('DELETE', $path, ['query' => $query]);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public function redact(array $payload): array
    {
        return Redactor::redact($payload, ['Ocp-Apim-Subscription-Key', 'UserName']);
    }

    /**
     * @param  array{query?: array<string, mixed>, json?: array<string, mixed>}  $options
     */
    private function send(string $method, string $path, array $options, bool $retried = false): Response
    {
        $response = $this->request()->withToken($this->token())->send($method, $this->url($path), $options);

        if ($response->status() === 401 && ! $retried) {
            CachedToken::forget($this->tokenKey());

            return $this->send($method, $path, $options, true);
        }

        if ($response->failed()) {
            $json = $response->json();

            Log::warning('cargus.request_failed', [
                'method' => $method,
                'path' => $path,
                'status' => $response->status(),
                'request' => $this->redact($options),
                'response' => is_array($json) ? $this->redact($json) : $response->body(),
            ]);

            if ($response->status() === 401) {
                throw new AuthenticationException('Cargus a respins token-ul și după re-autentificare.');
            }

            throw new CargusException(sprintf('Cargus %s %s a eșuat (HTTP %d): %s', $method, $path, $response->status(), self::message($response)));
        }

        return $response;
    }

    private function token(): string
    {
        return CachedToken::remember($this->tokenKey(), Config::integer('cargus.token_cache_ttl', 72000), function (): string {
            try {
                $response = $this->request()->post($this->url('/LoginUser'), [
                    'UserName' => Config::string('cargus.username', ''),
                    'Password' => Config::string('cargus.password', ''),
                ]);
            } catch (ConnectionException $exception) {
                throw new CargusException('Cargus nu a răspuns la LoginUser: '.$exception->getMessage(), 0, $exception);
            }

            $token = $response->json();

            if ($response->failed() || ! is_string($token) || $token === '') {
                Log::warning('cargus.login_failed', ['status' => $response->status()]);

                throw new AuthenticationException('Autentificarea Cargus (LoginUser) a eșuat: verifică CARGUS_USERNAME / CARGUS_PASSWORD.');
            }

            return $token;
        });
    }

    private function request(): PendingRequest
    {
        $key = Config::get('cargus.subscription_key');

        if (! is_string($key) || $key === '') {
            throw CargusException::missingSubscriptionKey();
        }

        return Http::acceptJson()
            ->withHeaders(['Ocp-Apim-Subscription-Key' => $key])
            ->timeout(Config::integer('cargus.timeout', 15));
    }

    private function url(string $path): string
    {
        return rtrim(Config::string('cargus.base_url', 'https://urgentcargus.azure-api.net/api'), '/').$path;
    }

    public static function message(Response $response): string
    {
        $json = $response->json();

        if (is_string($json) && $json !== '') {
            return $json;
        }

        $message = is_array($json) ? ($json['message'] ?? $json['Message'] ?? $json['error'] ?? null) : null;

        return is_scalar($message) ? (string) $message : ($response->body() !== '' ? $response->body() : 'eroare necunoscută');
    }
}
