<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

namespace CarlBennett\PlexTvAPI\Tests\Support;

use \CarlBennett\PlexTvAPI\HttpRequest;

/**
 * Stands in for the curl transport: records each request and answers with queued replies.
 */
class FakeTransport
{
    /** @var array<int, array{method: string, url: string, form_mime_type: string, form: ?array, headers: array}> */
    public array $requests = [];

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

    public function handle(string $method, string $url, string $form_mime_type, ?array $form, array $extra_headers): array
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'form_mime_type' => $form_mime_type,
            'form' => $form,
            'headers' => $extra_headers,
        ];

        if (empty($this->replies))
            throw new \LogicException(\sprintf('no reply queued for %s %s', $method, $url));

        return ['sent_headers' => $extra_headers, 'url' => $url] + \array_shift($this->replies);
    }

    public function lastRequest(): array
    {
        return \end($this->requests);
    }
}
