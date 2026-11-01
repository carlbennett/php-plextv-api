<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

namespace CarlBennett\PlexTvAPI\Tests\Support;

use \CarlBennett\PlexTvAPI\HttpRequest;

/**
 * Stands in for the curl transport: records each request and answers with queued replies.
 *
 * @phpstan-import-type Reply from HttpRequest
 * @phpstan-type Request array{method: string, url: string, form_mime_type: string, form: ?array<string, mixed>, headers: list<string>}
 */
class FakeTransport
{
    /** @var list<Request> */
    public array $requests = [];

    /** @var list<array{error: string, reply: string|false, reply_http_code: int, reply_mime_type: ?string}> */
    private array $replies = [];

    public static function install(): self
    {
        $transport = new self();
        HttpRequest::$handler = \Closure::fromCallable([$transport, 'handle']);
        return $transport;
    }

    public static function uninstall(): void
    {
        HttpRequest::$handler = null;
    }

    public function queue(int $code, ?string $mime_type = null, string|false $body = '', string $error = ''): self
    {
        $this->replies[] = [
            'error' => $error,
            'reply' => $body,
            'reply_http_code' => $code,
            'reply_mime_type' => $mime_type,
        ];
        return $this;
    }

    /**
     * @param ?array<string, mixed> $form
     * @param list<string> $extra_headers
     * @return Reply
     */
    public function handle(string $method, string $url, string $form_mime_type, ?array $form, array $extra_headers): array
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'form_mime_type' => $form_mime_type,
            'form' => $form,
            'headers' => $extra_headers,
        ];

        $reply = \array_shift($this->replies);
        if ($reply === null)
            throw new \LogicException(\sprintf('no reply queued for %s %s', $method, $url));

        return ['sent_headers' => $extra_headers, 'url' => $url] + $reply;
    }

    /**
     * @return Request
     */
    public function lastRequest(): array
    {
        $request = \end($this->requests);
        if ($request === false)
            throw new \LogicException('no request was made');

        return $request;
    }
}
