<?php declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Io\HttpBodyStream;
use React\Promise\PromiseInterface;
use WyriHaximus\HtmlCompress\Factory;
use WyriHaximus\HtmlCompress\Parser;
use function React\Promise\resolve;
use function RingCentral\Psr7\stream_for;

final class HtmlCompressMiddleware
{
    const MIME_TYPES = [
        'text/html',
        'text/xhtml',
    ];

    /**
     * @var Parser
     */
    private $compressor;

    /**
     * @param Parser $compressor
     */
    public function __construct(Parser $compressor = null)
    {
        if ($compressor === null) {
            $compressor = Factory::constructFastest();
        }

        $this->compressor = $compressor;
    }

    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        $response = $next($request);

        if (!($response instanceof PromiseInterface)) {
            return resolve($this->handleResponse($response));
        }

        return $response->then(function (ResponseInterface $response) {
            return $this->handleResponse($response);
        });
    }

    private function handleResponse(ResponseInterface $response)
    {
        if ($response->getBody() instanceof HttpBodyStream) {
            return $response;
        }

        if (!$response->hasHeader('content-type')) {
            return $response;
        }

        list($contentType) = \explode(';', $response->getHeaderLine('content-type'));
        if (!\in_array($contentType, self::MIME_TYPES, true)) {
            return $response;
        }

        $body = (string)$response->getBody();
        $compressedBody = $this->compressor->compress($body);

        return $response->withBody(stream_for($compressedBody))->withHeader('Content-Length', \strlen($compressedBody));
    }
}
