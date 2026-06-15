<?php

namespace App\Tests\Unit\Service;

use App\Service\StripeClient;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class StripeClientTest extends TestCase
{
    public function testCreateCustomerSendsStripeRequest(): void
    {
        $client = new StripeClient($this->httpClientReturning(['id' => 'cus_123']), 'sk_test', 'whsec_test');

        self::assertSame(['id' => 'cus_123'], $client->createCustomer('ada@example.com', 'Ada'));
    }

    public function testPaymentIntentRefundAndRetrieveRequests(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->response(200, ['id' => 'ok']);
        $httpClient
            ->expects(self::exactly(3))
            ->method('request')
            ->willReturn($response);

        $client = new StripeClient($httpClient, 'sk_test', 'whsec_test');

        self::assertSame(['id' => 'ok'], $client->createPaymentIntent(['amount' => 1200], 'idem-key'));
        self::assertSame(['id' => 'ok'], $client->retrievePaymentIntent('pi_123'));
        self::assertSame(['id' => 'ok'], $client->createRefund('pi_123', 500));
    }

    public function testRequestRequiresSecretKey(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stripe secret key is not configured.');

        (new StripeClient($this->createMock(HttpClientInterface::class), '', 'whsec_test'))->createPaymentIntent([]);
    }

    public function testRequestRejectsInvalidJsonAndErrorResponses(): void
    {
        $client = new StripeClient($this->httpClientWithResponse($this->rawResponse(200, 'not-json')), 'sk_test', 'whsec_test');

        try {
            $client->retrievePaymentIntent('pi_123');
            self::fail('Expected invalid JSON exception.');
        } catch (\RuntimeException $e) {
            self::assertSame('Unexpected Stripe API response.', $e->getMessage());
        }

        $client = new StripeClient($this->httpClientWithResponse($this->response(402, ['error' => ['message' => 'card declined']])), 'sk_test', 'whsec_test');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('card declined');
        $client->createRefund('pi_123');
    }

    public function testVerifyAndDecodeWebhook(): void
    {
        $payload = json_encode(['type' => 'payment_intent.succeeded'], JSON_THROW_ON_ERROR);
        $timestamp = '1234567890';
        $secret = 'whsec_test';
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        $client = new StripeClient($this->createMock(HttpClientInterface::class), 'sk_test', $secret);

        self::assertSame(
            ['type' => 'payment_intent.succeeded'],
            $client->verifyAndDecodeWebhook($payload, 't='.$timestamp.',v1='.$signature)
        );
    }

    public function testWebhookValidationFailures(): void
    {
        $client = new StripeClient($this->createMock(HttpClientInterface::class), 'sk_test', '');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stripe webhook secret is not configured.');
        $client->verifyAndDecodeWebhook('{}', 't=1,v1=x');
    }

    public function testWebhookRejectsMissingMalformedInvalidSignatureAndJson(): void
    {
        $client = new StripeClient($this->createMock(HttpClientInterface::class), 'sk_test', 'whsec_test');

        foreach ([null, '', 'bad-header'] as $header) {
            try {
                $client->verifyAndDecodeWebhook('{}', $header);
                self::fail('Expected webhook header exception.');
            } catch (\RuntimeException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }

        try {
            $client->verifyAndDecodeWebhook('{}', 't=1,v1=bad');
            self::fail('Expected invalid signature exception.');
        } catch (\RuntimeException $e) {
            self::assertSame('Invalid Stripe webhook signature.', $e->getMessage());
        }

        $signature = hash_hmac('sha256', '1.not-json', 'whsec_test');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid webhook JSON payload.');
        $client->verifyAndDecodeWebhook('not-json', 't=1,v1='.$signature);
    }

    private function httpClientReturning(array $payload): HttpClientInterface
    {
        return $this->httpClientWithResponse($this->response(200, $payload));
    }

    private function httpClientWithResponse(ResponseInterface $response): HttpClientInterface
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        return $httpClient;
    }

    private function response(int $statusCode, array $payload): ResponseInterface
    {
        return $this->rawResponse($statusCode, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function rawResponse(int $statusCode, string $content): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getContent')->with(false)->willReturn($content);

        return $response;
    }
}
