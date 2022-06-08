<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

namespace CarlBennett\PlexTvAPI;

use \CarlBennett\PlexTvAPI\Exceptions\PlexTvAPIException;
use \LogicException;
use \Ramsey\Uuid\Uuid;

class Auth
{
    const PLEXTV_API_INTERACTIVE_AUTH = 'https://app.plex.tv/auth#';
    const PLEXTV_API_TOKEN_VALIDITY = 'https://plex.tv/api/v2/user';
    const PLEXTV_API_GENERATE_PIN = 'https://plex.tv/api/v2/pins';
    const PLEXTV_API_CHECK_PIN = 'https://plex.tv/api/v2/pins/%d';

    public static string $client_id;
    public static string $app_name = 'php-plextv-api';
    public static string $forward_url_endpoint = '/plex/auth';

    private function __construct() {}

    /**
     * Generates a random v4 UUID string for client identification.
     * From Plex:
     * The Client Identifier identifies the specific instance of your app. A random string or UUID is
     * sufficient here. There are no hard requirements for Client Identifier length or format, but once one
     * is generated the client should store and re-use this identifier for subsequent requests.
     *
     * @return string The v4 UUID.
     */
    public static function generateClientId() : string
    {
        return Uuid::uuid4()->toString();
    }

    /**
     * Requests a PIN code from the Plex.tv API. This connects to the Plex.tv API.
     *
     * @return array The PIN id and code from Plex.tv.
     */
    public static function generatePINCode() : array
    {
        $args = array();
        $args['strong'] = 'true';
        $args['X-Plex-Product'] = self::$app_name;
        $args['X-Plex-Client-Identifier'] = self::$client_id;
        $url = sprintf('%s?%s', self::PLEXTV_API_GENERATE_PIN, http_build_query($args, '', '&', \PHP_QUERY_RFC3986));
        $reply = HttpRequest::execute(HttpRequest::METHOD_POST, $url);

        if ($reply['reply_http_code'] != 200)
            throw new PlexTvAPIException(sprintf('unexpected HTTP response code: %d', $reply['reply_http_code']));

        if (empty($reply['reply']))
            throw new PlexTvAPIException('empty HTTP response');

        $mime_type_fields = array();
        if (preg_match('/^(\b[A-Za-z0-9\+\-\/]+\b)(?:;\s*charset=(\b[A-Za-z0-9\-]+\b))?$/i', $reply['reply_mime_type'], $mime_type_fields) !== 1)
            throw new PlexTvAPIException(sprintf('cannot parse Content-Type header returned: %s', $reply['reply_mime_type']));

        $mime_type = $mime_type_fields[1];
        $charset = $mime_type_fields[2] ?? '';

        if (strtolower($mime_type) !== 'application/json')
            throw new PlexTvAPIException(sprintf('unexpected MIME-type returned: %s', $mime_type));

        $json = json_decode($reply['reply'], true);
        $json_errno = json_last_error();

        if ($json_errno !== JSON_ERROR_NONE)
            throw new PlexTvAPIException(sprintf('unexpected JSON error %d: %s', $json_errno, json_last_error_msg()));

        return [$json['id'] ?? null, $json['code'] ?? null];
    }

    /**
     * Generates the url to send the user back to us after Plex.tv authentication.
     *
     * @return string The fully qualified url at our host.
     */
    public static function getForwardUrl() : string
    {
        return \sprintf(
            '%s://%s%s',
            getenv('HTTPS') ? 'https' : 'http',
            getenv('HTTP_HOST') ?? getenv('SERVER_NAME'),
            (
                substr(self::$forward_url_endpoint, 0, 1) === '/' ?
                self::$forward_url_endpoint : '/' . self::$forward_url_endpoint
            )
        );
    }

    /**
     * Generate an interactive authentication url to send to an end user. Initiates the first step to getting a user access token.
     *
     * @param string $client_id The client identification generated and stored by us.
     * @param string $pin_code The pin identification.
     * @param string $forward_url The fully qualified url endpoint to return the user to.
     * @param string $app_name The name of our application.
     * @return string The fully qualified url to send the user to for authenticating with Plex.tv.
     */
    public static function getInteractiveAuthUrl(string $client_id, string $pin_code, string $forward_url, string $app_name) : string
    {
        return \sprintf(
            '%s?clientID=%s&code=%s&forwardUrl=%s&context%5Bdevice%5D%5Bproduct%5D=%s',
            self::PLEXTV_API_INTERACTIVE_AUTH,
            \rawurlencode($client_id),
            \rawurlencode($pin_code),
            \rawurlencode($forward_url),
            \rawurlencode($app_name)
        );
    }

    /**
     * Verifies a PIN code returned from the user and gets the user access token. This connects to the Plex.tv API.
     *
     * @param string $pin_id The pin identification from storage.
     * @param string $pin_code The pin identification from the user.
     * @return ?string The user's access token.
     */
    public static function verifyPINCode(string $pin_id, string $pin_code) : ?string
    {
        $args = array();
        $args['code'] = $pin_code;
        $args['X-Plex-Client-Identifier'] = self::$client_id;
        $url = sprintf('%s?%s', sprintf(self::PLEXTV_API_CHECK_PIN, $pin_id), http_build_query($args, '', '&', \PHP_QUERY_RFC3986));
        $reply = HttpRequest::execute(HttpRequest::METHOD_GET, $url);

        if ($reply['reply_http_code'] != 200)
            throw new PlexTvAPIException(sprintf('unexpected HTTP response code: %d', $reply['reply_http_code']));

        if (empty($reply['reply']))
            throw new PlexTvAPIException('empty HTTP response');

        $mime_type_fields = array();
        if (preg_match('/^(\b[A-Za-z0-9\+\-\/]+\b)(?:;\s*charset=(\b[A-Za-z0-9\-]+\b))?$/i', $reply['reply_mime_type'], $mime_type_fields) !== 1)
            throw new PlexTvAPIException(sprintf('cannot parse Content-Type header returned: %s', $reply['reply_mime_type']));

        $mime_type = $mime_type_fields[1];
        $charset = $mime_type_fields[2] ?? '';

        if (strtolower($mime_type) !== 'application/json')
            throw new PlexTvAPIException(sprintf('unexpected MIME-type returned: %s', $mime_type));

        $json = json_decode($reply['reply'], true);
        $json_errno = json_last_error();

        if ($json_errno !== JSON_ERROR_NONE)
            throw new PlexTvAPIException(sprintf('unexpected JSON error %d: %s', $json_errno, json_last_error_msg()));

        return $json['authToken'] ?? null;
    }

    /**
     * Verifies a stored user access token for validity. This connects to the Plex.tv API.
     *
     * @param string $user_access_token The stored access token.
     * @return bool True if access token is valid, false otherwise.
     */
    public static function verifyUserAccessToken(string $user_access_token) : bool
    {
        $args = array();
        $args['X-Plex-Product'] = self::$app_name;
        $args['X-Plex-Client-Identifier'] = self::$client_id;
        $args['X-Plex-Token'] = $user_access_token;
        $url = sprintf('%s?%s', self::PLEXTV_API_TOKEN_VALIDITY, http_build_query($args, '', '&', \PHP_QUERY_RFC3986));
        $reply = HttpRequest::execute(HttpRequest::METHOD_GET, $url);

        if ($reply['reply_http_code'] == 401)
            return false;

        if ($reply['reply_http_code'] != 200)
            throw new PlexTvAPIException(sprintf('unexpected HTTP response code: %d', $reply['reply_http_code']));

        return true;
    }
}