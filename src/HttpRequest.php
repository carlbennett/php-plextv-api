<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

namespace CarlBennett\PlexTvAPI;

use \CarlBennett\PlexTvAPI\Exceptions\PlexTvAPIException;

/**
 * @phpstan-type Reply array{sent_headers: list<string>, reply: string|false, error: string, url: string, reply_http_code: int, reply_mime_type: ?string}
 * @phpstan-type SuccessReply array{sent_headers: list<string>, reply: non-empty-string, error: string, url: string, reply_http_code: int, reply_mime_type: string}
 */
class HttpRequest
{
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_GET = 'GET';
    public const METHOD_HEAD = 'HEAD';
    public const METHOD_OPTIONS = 'OPTIONS';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_UPLOAD = 'UPLOAD';

    public static int $connect_timeout = 3;
    public static int $max_redirects = 10;
    /** @var non-empty-string Comma-separated protocols that redirects may use, see CURLOPT_REDIR_PROTOCOLS_STR. */
    public static string $redirect_protocols = 'https';
    public static int $timeout = 30; // seconds for the whole transfer, including connecting and redirects
    public static string $user_agent = 'php-plextv-api (+https://github.com/carlbennett/php-plextv-api)';

    /**
     * Replaces the curl transport when set. It receives the same arguments as execute() and must return the same
     * array shape. Intended for tests; leave null in production.
     *
     * @var (\Closure(string, string, string, ?array<string, mixed>, list<string>): Reply)|null
     */
    public static ?\Closure $handler = null;

    private function __construct()
    {
        throw new \LogicException('static class cannot be instantiated');
    }

    /**
     * Executes an HTTP request and returns the reply from the server. Uses Curl.
     *
     * Initial headers include "Accept" and "User-Agent". The User-agent can be customized by changing HttpRequest::$user_agent.
     *
     * @param string $method The HTTP request method (e.g. HttpRequest::METHOD_GET).
     * @param string $url The URL to request.
     * @param string $form_mime_type The MIME-type of the $form parameter, sent in the "Content-Type" HTTP request header.
     * @param ?array<string, mixed> $form The key-value pairs of form fields as an array, can be empty or null to disable form.
     * @param list<string> $extra_headers Extra HTTP headers as "Name: value" strings, appended to the initial headers.
     * @return Reply An array containing the HTTP reply information.
     * @throws \InvalidArgumentException if the method or URL is empty, or a header value contains CR, LF, or NUL characters.
     */
    public static function execute(string $method, string $url, string $form_mime_type = '', ?array $form = null, array $extra_headers = []): array
    {
        if ($method === '' || $url === '')
            throw new \InvalidArgumentException('HTTP method and URL must not be empty');

        foreach (\array_merge([$form_mime_type, self::$user_agent], $extra_headers) as $value)
        {
            if (\strpbrk($value, "\r\n\0") !== false)
                throw new \InvalidArgumentException('HTTP header values must not contain CR, LF, or NUL characters');
        }

        if (self::$handler !== null)
            return (self::$handler)($method, $url, $form_mime_type, $form, $extra_headers);

        $curl = \curl_init();

        \curl_setopt($curl, \CURLOPT_CONNECTTIMEOUT, self::$connect_timeout);
        \curl_setopt($curl, \CURLOPT_TIMEOUT, self::$timeout);
        \curl_setopt($curl, \CURLOPT_FOLLOWLOCATION, true);
        \curl_setopt($curl, \CURLOPT_MAXREDIRS, self::$max_redirects);
        \curl_setopt($curl, \CURLOPT_REDIR_PROTOCOLS_STR, self::$redirect_protocols);
        \curl_setopt($curl, \CURLOPT_POSTREDIR, 7); // accepted form-field redirect methods: 7 = 301, 302, 303
        \curl_setopt($curl, \CURLOPT_URL, $url);

        if ($method === self::METHOD_HEAD)
            \curl_setopt($curl, \CURLOPT_NOBODY, true);
        else
            \curl_setopt($curl, \CURLOPT_CUSTOMREQUEST, $method);

        $init_headers = [
            'Accept: application/json,text/json;q=0.5,application/xml,application/xhtml+xml,text/xml;q=0.4,text/html,text/plain,*/*;q=0.1',
            'Cache-Control: no-cache',
            'Connection: close',
            'Pragma: no-cache',
            \sprintf('User-Agent: %s', self::$user_agent),
        ];
        $headers = \array_merge($init_headers, $extra_headers);

        if ($form)
        {
            \curl_setopt($curl, \CURLOPT_POST, true);

            if (\defined('\CURLOPT_SAFE_UPLOAD'))
            {
                // disable processing of @ symbol as a filename in CURLOPT_POSTFIELDS.
                \curl_setopt($curl, \CURLOPT_SAFE_UPLOAD, true);
            }

            $form_mime_type = empty($form_mime_type) ? 'application/x-www-form-urlencoded' : $form_mime_type;

            if (\stripos($form_mime_type, 'multipart/form-data') === 0)
            {
                // curl encodes an array as multipart and sets the Content-Type header with its boundary itself.
                \curl_setopt($curl, \CURLOPT_POSTFIELDS, $form);
            }
            else
            {
                \curl_setopt($curl, \CURLOPT_POSTFIELDS, \http_build_query($form, '', '&'));
                $headers[] = 'Content-Type: ' . $form_mime_type;
            }
        }

        \curl_setopt($curl, \CURLOPT_HTTPHEADER, $headers);
        \curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);

        $return = [];
        $return['sent_headers'] = $headers;
        $reply = \curl_exec($curl);
        $return['reply'] = \is_string($reply) ? $reply : false;
        $return['error'] = $return['reply'] === false ? \curl_error($curl) : '';
        $return['url'] = \curl_getinfo($curl, \CURLINFO_EFFECTIVE_URL);
        $return['reply_http_code'] = \curl_getinfo($curl, \CURLINFO_RESPONSE_CODE);
        $mime_type = \curl_getinfo($curl, \CURLINFO_CONTENT_TYPE); // false, not null, when the transfer failed
        $return['reply_mime_type'] = \is_string($mime_type) ? $mime_type : null;

        return $return;
    }

    /**
     * Checks that a reply from execute() succeeded with a non-empty body of the expected MIME-type.
     *
     * @param Reply $reply The array returned by execute().
     * @param string $mime_type The expected MIME-type, such as "application/json".
     * @param list<int> $http_codes The HTTP response codes that count as success.
     * @return string The charset given in the Content-Type header, or an empty string if none.
     * @throws PlexTvAPIException if the request failed or the reply is not what was expected.
     * @phpstan-assert SuccessReply $reply
     */
    public static function expectReply(array $reply, string $mime_type, array $http_codes = [200]): string
    {
        if ($reply['reply'] === false)
            throw new PlexTvAPIException(\sprintf('HTTP request failed: %s', $reply['error'] !== '' ? $reply['error'] : 'unknown error'));

        if (!\in_array($reply['reply_http_code'], $http_codes, true))
            throw new PlexTvAPIException(\sprintf('unexpected HTTP response code: %d', $reply['reply_http_code']));

        if (empty($reply['reply']))
            throw new PlexTvAPIException('empty HTTP response');

        $params = \explode(';', (string) $reply['reply_mime_type']);
        $reply_mime_type = \strtolower(\trim(\array_shift($params)));
        if ($reply_mime_type === '')
            throw new PlexTvAPIException('HTTP response has no Content-Type');

        if ($reply_mime_type !== \strtolower($mime_type))
            throw new PlexTvAPIException(\sprintf('unexpected MIME-type returned: %s', $reply_mime_type));

        foreach ($params as $param)
        {
            [$key, $value] = \array_pad(\explode('=', $param, 2), 2, '');
            if (\strtolower(\trim($key)) === 'charset')
                return \trim(\trim($value), '"');
        }

        return '';
    }
}
