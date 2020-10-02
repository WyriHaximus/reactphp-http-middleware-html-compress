<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use function Clue\React\Block\await;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Io\HttpBodyStream;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use function React\Promise\resolve;
use React\Stream\ThroughStream;
use function RingCentral\Psr7\stream_for;
use WyriHaximus\React\Http\Middleware\HtmlCompressMiddleware;

/**
 * @internal
 */
final class HtmlCompressMiddlewareTest extends TestCase
{
    public function provideContentTypes()
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
        $request = (new ServerRequest('GET', 'https://example.com/'))->withBody(stream_for('foo.bar'));
        $middleware = new HtmlCompressMiddleware();
        /** @var ServerRequestInterface $compressedResponse */
        $compressedResponse = await($middleware($request, function (ServerRequestInterface $request) use ($contentType) {
            return new Response(
                200,
                [
                    'Content-Type' => $contentType,
                ],
                '<head>
                    <title>woei</title>
                </head>'
            );
        }), Factory::create());
        self::assertTrue(\in_array(
            (int)$compressedResponse->getHeaderLine('Content-Length'),
            [
                25,
                32,
            ],
            true
        ));
        self::assertTrue(\in_array(
            (string)$compressedResponse->getBody(),
            [
                '<head><title>woei</title></head>',
                '<head><title>woei</title>',
            ],
            true
        ));
    }

    public function provideheadersForIgnoreResponse()
    {
        yield [
            [],
        ];

        yield [
            [
                'Content-Type' => 'text/pain',
            ],
        ];
    }

    /**
     * @dataProvider provideheadersForIgnoreResponse
     */
    public function testIgnoreNotSupportedAndMissingContentTypes(array $headers): void
    {
        $body = '<head>
                    <title>woei</title>
                </head>';
        $request = (new ServerRequest('GET', 'https://example.com/'))->withBody(stream_for('foo.bar'));
        $middleware = new HtmlCompressMiddleware();
        /** @var ServerRequestInterface $compressedResponse */
        $compressedResponse = await($middleware($request, function (ServerRequestInterface $request) use ($body, $headers) {
            return resolve(new Response(
                200,
                $headers,
                $body
            ));
        }), Factory::create());
        self::assertSame($body, (string)$compressedResponse->getBody());
    }

    public function testHttpBodyStream(): void
    {
        $response = new Response(
            200,
            [],
            new HttpBodyStream(new ThroughStream(), null)
        );
        $request = (new ServerRequest('GET', 'https://example.com/'))->withBody(stream_for('foo.bar'));
        $middleware = new HtmlCompressMiddleware();
        /** @var ServerRequestInterface $compressedResponse */
        $compressedResponse = await($middleware($request, function (ServerRequestInterface $request) use ($response) {
            return resolve($response);
        }), Factory::create());
        self::assertSame($response, $compressedResponse);
    }
}
