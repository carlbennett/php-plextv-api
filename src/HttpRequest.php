<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

namespace CarlBennett\PlexTvAPI;

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
    public static string $user_agent = 'Mozilla/5.0 (X11; Linux; rv:98.0) Gecko/20100101 Firefox/98.0';

    /**
     * Replaces the curl transport when set. It receives the same arguments as execute() and must return the same
     * array shape. Intended for tests; leave null in production.
     */
    public static ?\Closure $handler = null;

    private function __construct()
    {
        throw new \LogicException('static class cannot be instantiated');
    }

    /**
     * Executes an HTTP request and returns the reply from the server. Uses Curl.
     *
     * Initial headers include "Accept", "Range", and "User-Agent". The User-agent can be customized by changing HttpRequest::$user_agent.
     *
     * @param string $method The HTTP request method (e.g. HttpRequest::METHOD_GET).
     * @param string $url The URL to request.
     * @param string $form_mime_type The MIME-type of the $form parameter, sent in the "Content-Type" HTTP request header.
     * @param ?array $form The key-value pairs of form fields as an array, can be empty or null to disable form.
     * @param ?array $extra_headers The key-value pairs of extra HTTP headers, to be merged with initial headers.
     * @return array An array containing the HTTP reply information.
     */
    public static function execute(string $method, string $url, string $form_mime_type = '', ?array $form = null, array $extra_headers = []): array
    {
        if (self::$handler !== null)
            return (self::$handler)($method, $url, $form_mime_type, $form, $extra_headers);

        $curl = \curl_init();

        \curl_setopt($curl, \CURLOPT_CONNECTTIMEOUT, self::$connect_timeout);
        \curl_setopt($curl, \CURLOPT_AUTOREFERER, true);
        \curl_setopt($curl, \CURLOPT_FOLLOWLOCATION, true);
        \curl_setopt($curl, \CURLOPT_MAXREDIRS, self::$max_redirects);
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
            'Range: bytes=0-104857600', // 0-100 MiB (1024 * 1024 * 100 bytes)
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
        $return['reply'] = \curl_exec($curl);
        $return['error'] = $return['reply'] === false ? \curl_error($curl) : '';
        $return['url'] = \curl_getinfo($curl, \CURLINFO_EFFECTIVE_URL);
        $return['reply_http_code'] = \curl_getinfo($curl, \CURLINFO_RESPONSE_CODE);
        $return['reply_mime_type'] = \curl_getinfo($curl, \CURLINFO_CONTENT_TYPE);

        return $return;
    }
}
