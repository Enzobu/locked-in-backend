<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class StripeClient
{
    private const API_BASE_URL = 'https://api.stripe.com/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $secretKey,
        private readonly string $webhookSecret,
    ) {
    }

    public function createCustomer(string $email, string $name): array
    {
        return $this->request('POST', '/customers', [
            'email' => $email,
            'name' => $name,
        ]);
    }

    public function createPaymentIntent(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->request('POST', '/payment_intents', $payload, $idempotencyKey);
    }

    public function retrievePaymentIntent(string $paymentIntentId): array
    {
        return $this->request('GET', '/payment_intents/'.$paymentIntentId);
    }

    /**
     * Refunds a payment intent (full refund unless an amount in cents is given).
     */
    public function createRefund(string $paymentIntentId, ?int $amountCents = null): array
    {
        $payload = ['payment_intent' => $paymentIntentId];
        if ($amountCents !== null) {
            $payload['amount'] = $amountCents;
        }

        return $this->request('POST', '/refunds', $payload);
    }

    public function verifyAndDecodeWebhook(string $payload, ?string $signatureHeader): array
    {
        if ($this->webhookSecret === '') {
            throw new \RuntimeException('Stripe webhook secret is not configured.');
        }

        if (!is_string($signatureHeader) || $signatureHeader === '') {
            throw new \RuntimeException('Missing Stripe-Signature header.');
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $segment) {
            $pair = explode('=', trim($segment), 2);
            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }

        $timestamp = $parts['t'] ?? null;
        $signature = $parts['v1'] ?? null;

        if (!is_string($timestamp) || !is_string($signature)) {
            throw new \RuntimeException('Invalid Stripe-Signature header format.');
        }

        $signedPayload = $timestamp.'.'.$payload;
        $expected = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        if (!hash_equals($expected, $signature)) {
            throw new \RuntimeException('Invalid Stripe webhook signature.');
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid webhook JSON payload.');
        }

        return $decoded;
    }

    private function request(string $method, string $path, array $payload = [], ?string $idempotencyKey = null): array
    {
        if ($this->secretKey === '') {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }

        $options = [
            'headers' => [
                'Authorization' => 'Bearer '.$this->secretKey,
            ],
        ];

        // Stripe deduplicates retried POSTs sharing an Idempotency-Key, preventing
        // duplicate charges when a request is replayed (e.g. double submit).
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $options['headers']['Idempotency-Key'] = $idempotencyKey;
        }

        if ($payload !== []) {
            $options['body'] = $payload;
        }

        try {
            $response = $this->httpClient->request($method, self::API_BASE_URL.$path, $options);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Stripe API network error: '.$e->getMessage(), 0, $e);
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Unexpected Stripe API response.');
        }

        if ($status >= 400) {
            $message = $data['error']['message'] ?? 'Stripe API request failed.';
            throw new \RuntimeException($message);
        }

        return $data;
    }
}
