<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Io\HttpBodyStream;
use React\Http\Io\ServerRequest;
use React\Http\Response;
use React\Stream\ThroughStream;
use WyriHaximus\React\Http\Middleware\HtmlCompressMiddleware;
use function Clue\React\Block\await;
use function React\Promise\resolve;
use function RingCentral\Psr7\stream_for;

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
    public function testCompressedResponse(string $contentType)
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
        self::assertSame(32, (int)$compressedResponse->getHeaderLine('Content-Length'));
        self::assertSame('<head><title>woei</title></head>', (string)$compressedResponse->getBody());
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
    public function testIgnoreNotSupportedAndMissingContentTypes(array $headers)
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

    public function testHttpBodyStream()
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
