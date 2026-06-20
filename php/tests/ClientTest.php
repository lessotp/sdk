<?php

declare(strict_types=1);

namespace LessOTP\Sdk\Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use LessOTP\Sdk\AuthRequestResult;
use LessOTP\Sdk\Client;
use LessOTP\Sdk\LessOTPException;
use LessOTP\Sdk\VerificationMode;

final class ClientTest extends TestCase
{
    public function testStrictAuthRequestBuildsHttpRequestAndParsesResponse(): void
    {
        $transactions = array();
        $mock = new MockHandler(array(
            new Response(200, array(), $this->successJson('req_abc', 'A7X92', 'strict')),
        ));
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($transactions));

        $client = new Client('key_test', 'https://api.lessotp.example', new HttpClient(array('handler' => $stack)));
        $result = $client->authRequest('6281234567890');

        self::assertInstanceOf(AuthRequestResult::class, $result);
        self::assertSame('req_abc', $result->getRequestId());
        self::assertSame('A7X92', $result->getUniqueCode());
        self::assertSame(180, $result->getExpiresIn());
        self::assertSame(VerificationMode::STRICT, $result->getMode()->value());
        self::assertSame('https://wa.me/628999999999?text=%2FLOGIN%20A7X92', $result->getWaLink());

        self::assertCount(1, $transactions);
        /** @var Request $sent */
        $sent = $transactions[0]['request'];
        self::assertSame('POST', $sent->getMethod());
        self::assertSame('https://api.lessotp.example/api/v1/auth/request', (string) $sent->getUri());
        self::assertSame('Bearer key_test', $sent->getHeaderLine('Authorization'));
        self::assertSame(array('phone_number' => '6281234567890'), json_decode((string) $sent->getBody(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testDefaultEnvironmentIsProduction(): void
    {
        $transactions = array();
        $mock = new MockHandler(array(
            new Response(200, array(), $this->successJson('req_abc', 'A7X92', 'strict')),
        ));
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($transactions));

        $client = new Client('key_test', 'https://api.lessotp.example', new HttpClient(array('handler' => $stack)));
        $client->authRequest();

        self::assertSame('/api/v1/auth/request', $transactions[0]['request']->getUri()->getPath());
    }

    public function testConstructorEnvironmentStagingRoutesToStagingEndpoint(): void
    {
        $transactions = array();
        $mock = new MockHandler(array(
            new Response(200, array(), $this->successJson('req_stage', 'S1T2G', 'strict')),
        ));
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($transactions));

        $client = new Client(
            'key_test',
            'https://api.lessotp.example',
            new HttpClient(array('handler' => $stack)),
            10,
            'staging'
        );
        $result = $client->authRequest('6281234567890');

        self::assertSame('req_stage', $result->getRequestId());
        self::assertSame('/api/v1/staging/auth/request', $transactions[0]['request']->getUri()->getPath());
    }

    public function testPerCallEnvironmentOverrideBeatsConstructor(): void
    {
        $transactions = array();
        $mock = new MockHandler(array(
            new Response(200, array(), $this->successJson('req_stage', 'S1T2G', 'strict')),
        ));
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($transactions));

        $client = new Client(
            'key_test',
            'https://api.lessotp.example',
            new HttpClient(array('handler' => $stack)),
            10,
            'production'
        );
        $client->authRequest('6281234567890', 'staging');

        self::assertSame('/api/v1/staging/auth/request', $transactions[0]['request']->getUri()->getPath());
    }

    public function testFrictionlessAuthRequestOmitsPhoneNumber(): void
    {
        $transactions = array();
        $mock = new MockHandler(array(
            new Response(200, array(), $this->successJson('req_fric', 'B1C34', 'frictionless')),
        ));
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($transactions));

        $client = new Client('key_test', 'https://api.lessotp.example', new HttpClient(array('handler' => $stack)));
        $result = $client->authRequest();

        self::assertSame(VerificationMode::FRICTIONLESS, $result->getMode()->value());
        self::assertSame(array(), json_decode((string) $transactions[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testThrowsOnNonSuccessHttpStatus(): void
    {
        $mock = new MockHandler(array(new Response(401, array(), json_encode(array('error' => 'invalid_api_key')))));
        $stack = HandlerStack::create($mock);
        $client = new Client('bad_key', 'https://api.lessotp.example', new HttpClient(array('handler' => $stack)));

        $this->expectException(LessOTPException::class);
        $this->expectExceptionMessageMatches('/401/');
        $client->authRequest();
    }

    public function testThrowsOnInvalidPayloadShape(): void
    {
        $mock = new MockHandler(array(new Response(200, array(), json_encode(array('status' => 'success', 'data' => array())))));
        $stack = HandlerStack::create($mock);
        $client = new Client('k', 'https://api.lessotp.example', new HttpClient(array('handler' => $stack)));

        $this->expectException(LessOTPException::class);
        $this->expectExceptionMessageMatches('/request_id/');
        $client->authRequest();
    }

    public function testRejectsUnknownConstructorEnvironment(): void
    {
        $this->expectException(LessOTPException::class);
        new Client('k', 'https://api.lessotp.example', null, 10, 'qa');
    }

    /**
     * @return string
     */
    private function successJson($requestId, $uniqueCode, $mode)
    {
        return json_encode(array(
            'status' => 'success',
            'data' => array(
                'request_id' => $requestId,
                'unique_code' => $uniqueCode,
                'wa_link' => 'https://wa.me/628999999999?text=%2FLOGIN%20' . $uniqueCode,
                'expires_in' => 180,
                'mode' => $mode,
            ),
        ), JSON_THROW_ON_ERROR);
    }
}
