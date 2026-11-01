<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

namespace CarlBennett\PlexTvAPI;

use \CarlBennett\PlexTvAPI\Exceptions\PlexTvAPIException;

class Auth
{
    const PLEXTV_API_INTERACTIVE_AUTH = 'https://app.plex.tv/auth#';
    const PLEXTV_API_TOKEN_VALIDITY = 'https://plex.tv/api/v2/user';
    const PLEXTV_API_GENERATE_PIN = 'https://plex.tv/api/v2/pins';
    const PLEXTV_API_CHECK_PIN = 'https://plex.tv/api/v2/pins/%d';

    public static ?string $client_id = null;
    public static string $app_name = 'php-plextv-api';
    public static string $forward_url_endpoint = '/plex/auth';

    /**
     * The scheme and host to return users to after Plex.tv authentication, such as "https://example.com". When null,
     * getForwardUrl() uses the current request, which trusts the client-supplied Host header; set this in production.
     */
    public static ?string $forward_url_base = null;

    private function __construct() {}

    /**
     * Gets the client identifier, which must be set before any request is made to Plex.tv.
     *
     * @return string The value of Auth::$client_id.
     * @throws PlexTvAPIException if Auth::$client_id is not set.
     */
    private static function clientId(): string
    {
        if (self::$client_id === null || self::$client_id === '')
            throw new PlexTvAPIException('Auth::$client_id must be set before making requests, see Auth::generateClientId()');

        return self::$client_id;
    }

    /**
     * Generates a random v4 UUID string for client identification.
     * From Plex:
     * The Client Identifier identifies the specific instance of your app. A random string or UUID is
     * sufficient here. There are no hard requirements for Client Identifier length or format, but once one
     * is generated the client should store and re-use this identifier for subsequent requests.
     *
     * @return string The v4 UUID.
     */
    public static function generateClientId(): string
    {
        return \Ramsey\Uuid\Uuid::uuid4()->toString();
    }

    /**
     * Requests a PIN code from the Plex.tv API. This connects to the Plex.tv API.
     *
     * @return array The PIN id and code from Plex.tv.
     * @throws PlexTvAPIException if the request fails or the reply is not a PIN.
     */
    public static function generatePINCode(): array
    {
        $args = [];
        $args['strong'] = 'true';
        $args['X-Plex-Product'] = self::$app_name;
        $args['X-Plex-Client-Identifier'] = self::clientId();
        $url = \sprintf('%s?%s', self::PLEXTV_API_GENERATE_PIN, \http_build_query($args, '', '&', \PHP_QUERY_RFC3986));
        $reply = HttpRequest::execute(HttpRequest::METHOD_POST, $url);

        HttpRequest::expectReply($reply, 'application/json', [200, 201]); // Plex replies 201 Created

        $json = \json_decode($reply['reply'], true);
        $json_errno = \json_last_error();

        if ($json_errno !== \JSON_ERROR_NONE)
            throw new PlexTvAPIException(\sprintf('unexpected JSON error %d: %s', $json_errno, \json_last_error_msg()));

        if (!isset($json['id'], $json['code']))
            throw new PlexTvAPIException('PIN reply is missing its id or code');

        return [$json['id'], $json['code']];
    }

    /**
     * Generates the url to send the user back to us after Plex.tv authentication.
     *
     * Uses Auth::$forward_url_base if set, otherwise the scheme and host of the current request.
     *
     * @return string The fully qualified url at our host.
     * @throws PlexTvAPIException if no host is configured and the current request has none.
     */
    public static function getForwardUrl(): string
    {
        $endpoint = \str_starts_with(self::$forward_url_endpoint, '/') ? self::$forward_url_endpoint : '/' . self::$forward_url_endpoint;

        if (self::$forward_url_base !== null)
            return \rtrim(self::$forward_url_base, '/') . $endpoint;

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') $host = $_SERVER['SERVER_NAME'] ?? '';
        if ($host === '')
            throw new PlexTvAPIException('cannot determine our host, set Auth::$forward_url_base');

        $https = \strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        $scheme = ($https !== '' && $https !== 'off') ? 'https' : 'http';

        return \sprintf('%s://%s%s', $scheme, $host, $endpoint);
    }

    /**
     * Generate an interactive authentication url to send to an end user. Initiates the first step to getting a user access token.
     *
     * @param string $client_id The client identification generated and stored by us.
     * @param string $pin_code The PIN code from Auth::generatePINCode().
     * @param string $forward_url The fully qualified url endpoint to return the user to.
     * @param string $app_name The name of our application.
     * @return string The fully qualified url to send the user to for authenticating with Plex.tv.
     */
    public static function getInteractiveAuthUrl(string $client_id, string $pin_code, string $forward_url, string $app_name): string
    {
        $args = [
            'clientID' => $client_id,
            'code' => $pin_code,
            'forwardUrl' => $forward_url,
            'context' => ['device' => ['product' => $app_name]],
        ];
        return \sprintf('%s?%s', self::PLEXTV_API_INTERACTIVE_AUTH, \http_build_query($args, '', '&', \PHP_QUERY_RFC3986));
    }

    /**
     * Verifies a PIN code returned from the user and gets the user access token. This connects to the Plex.tv API.
     *
     * @param string $pin_id The pin identification from storage.
     * @param string $pin_code The PIN code from Auth::generatePINCode().
     * @return ?string The user's access token, or null if the user has not signed in yet.
     * @throws PlexTvAPIException if the PIN id is not numeric, or the request fails.
     */
    public static function verifyPINCode(string $pin_id, string $pin_code): ?string
    {
        if (!\ctype_digit($pin_id))
            throw new PlexTvAPIException(\sprintf('PIN id must be numeric, got: %s', $pin_id));

        $args = [];
        $args['code'] = $pin_code;
        $args['X-Plex-Client-Identifier'] = self::clientId();
        $url = \sprintf('%s?%s', \sprintf(self::PLEXTV_API_CHECK_PIN, $pin_id), \http_build_query($args, '', '&', \PHP_QUERY_RFC3986));
        $reply = HttpRequest::execute(HttpRequest::METHOD_GET, $url);

        HttpRequest::expectReply($reply, 'application/json');

        $json = \json_decode($reply['reply'], true);
        $json_errno = \json_last_error();

        if ($json_errno !== \JSON_ERROR_NONE)
            throw new PlexTvAPIException(\sprintf('unexpected JSON error %d: %s', $json_errno, \json_last_error_msg()));

        return $json['authToken'] ?? null;
    }

    /**
     * Verifies a stored user access token for validity. This connects to the Plex.tv API.
     *
     * @param string $user_access_token The stored access token.
     * @return bool True if access token is valid, false otherwise.
     */
    public static function verifyUserAccessToken(string $user_access_token): bool
    {
        $args = [];
        $args['X-Plex-Product'] = self::$app_name;
        $args['X-Plex-Client-Identifier'] = self::clientId();
        $url = \sprintf('%s?%s', self::PLEXTV_API_TOKEN_VALIDITY, \http_build_query($args, '', '&', \PHP_QUERY_RFC3986));
        $reply = HttpRequest::execute(HttpRequest::METHOD_GET, $url, '', null, ['X-Plex-Token: ' . $user_access_token]);

        if ($reply['reply'] === false)
            throw new PlexTvAPIException(\sprintf('HTTP request failed: %s', $reply['error'] ?? 'unknown error'));

        if ($reply['reply_http_code'] == 401)
            return false;

        if ($reply['reply_http_code'] != 200)
            throw new PlexTvAPIException(\sprintf('unexpected HTTP response code: %d', $reply['reply_http_code']));

        return true;
    }
}
