<?php

declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Io\HttpBodyStream;
use React\Promise\PromiseInterface;
use WyriHaximus\HtmlCompress\Factory;
use WyriHaximus\HtmlCompress\HtmlCompressorInterface;

use function explode;
use function in_array;
use function React\Promise\resolve;
use function RingCentral\Psr7\stream_for;
use function strlen;

final class HtmlCompressMiddleware
{
    public const MIME_TYPES = [
        'text/html',
        'text/xhtml',
    ];

    private HtmlCompressorInterface $compressor;

    /**
     * @phpstan-ignore-next-line
     */
    public function __construct(?HtmlCompressorInterface $compressor = null)
    {
        if ($compressor === null) {
            $compressor = Factory::constructFastest();
        }

        $this->compressor = $compressor;
    }

    public function __invoke(ServerRequestInterface $request, callable $next): PromiseInterface
    {
        $response = $next($request);

        if (! ($response instanceof PromiseInterface)) {
            return resolve($this->handleResponse($response));
        }

        return $response->then(function (ResponseInterface $response): ResponseInterface {
            return $this->handleResponse($response);
        });
    }

    private function handleResponse(ResponseInterface $response): ResponseInterface
    {
        if ($response->getBody() instanceof HttpBodyStream) {
            return $response;
        }

        if (! $response->hasHeader('content-type')) {
            return $response;
        }

        [$contentType] = explode(';', $response->getHeaderLine('content-type'));
        if (! in_array($contentType, self::MIME_TYPES, true)) {
            return $response;
        }

        $body           = (string) $response->getBody();
        $compressedBody = $this->compressor->compress($body);

        return $response->withBody(stream_for($compressedBody))->withHeader('Content-Length', (string) strlen($compressedBody));
    }
}
