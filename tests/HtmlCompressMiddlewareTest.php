<?php

declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Io\HttpBodyStream;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Promise\PromiseInterface;
use React\Stream\ThroughStream;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Http\Middleware\HtmlCompressMiddleware;

use function in_array;
use function React\Promise\resolve;
use function RingCentral\Psr7\stream_for;

final class HtmlCompressMiddlewareTest extends AsyncTestCase
{
    /**
     * @return iterable<array<string>>
     */
    public function provideContentTypes(): iterable
    {
        foreach (HtmlCompressMiddleware::MIME_TYPES as $contentType) {
            yield [$contentType];
        }
    }

    /**
     * @dataProvider provideContentTypes
     */
    public function testCompressedResponse(string $contentType): void
    {
        $request            = (new ServerRequest('GET', 'https://example.com/'))->withBody(stream_for('foo.bar'));
        $middleware         = new HtmlCompressMiddleware();
        $compressedResponse = $this->await($middleware($request, static function (ServerRequestInterface $request) use ($contentType): ResponseInterface {
            return new Response(
                200,
                ['Content-Type' => $contentType],
                '<head>
                    <title>woei</title>
                </head>'
            );
        }));

        self::assertTrue(in_array(
            (int) $compressedResponse->getHeaderLine('Content-Length'),
            [
                25,
                32,
            ],
            true
        ));
        self::assertTrue(in_array(
            (string) $compressedResponse->getBody(),
            [
                '<head><title>woei</title></head>',
                '<head><title>woei</title>',
            ],
            true
        ));
    }

    /**
     * @return iterable<array<mixed>>
     */
    public function provideheadersForIgnoreResponse(): iterable
    {
        yield [
            [],
        ];

        yield [
            ['Content-Type' => 'text/pain'],
        ];
    }

    /**
     * @param array<mixed> $headers
     *
     * @dataProvider provideheadersForIgnoreResponse
     */
    public function testIgnoreNotSupportedAndMissingContentTypes(array $headers): void
    {
        $body               = '<head>
                    <title>woei</title>
                </head>';
        $request            = (new ServerRequest('GET', 'https://example.com/'))->withBody(stream_for('foo.bar'));
        $middleware         = new HtmlCompressMiddleware();
        $compressedResponse = $this->await($middleware($request, static function (ServerRequestInterface $request) use ($body, $headers): PromiseInterface {
            return resolve(new Response(
                200,
                $headers,
                $body
            ));
        }));

        self::assertSame($body, (string) $compressedResponse->getBody());
    }

    public function testHttpBodyStream(): void
    {
        $bodyContents       = 'foo.bar';
        $response           = new Response(
            200,
            [],
            new HttpBodyStream(new ThroughStream(), null)
        );
        $request            = (new ServerRequest('GET', 'https://example.com/'))->withBody(stream_for($bodyContents));
        $middleware         = new HtmlCompressMiddleware();
        $compressedResponse = $this->await($middleware($request, static function (ServerRequestInterface $request) use ($response): PromiseInterface {
            return resolve($response);
        }));

        self::assertInstanceOf(HttpBodyStream::class, $compressedResponse->getBody());
    }
}
