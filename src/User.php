<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

namespace CarlBennett\PlexTvAPI;

use \CarlBennett\PlexTvAPI\HttpRequest;
use \CarlBennett\PlexTvAPI\Server;
use \JsonSerializable;
use \RuntimeException;
use \XMLReader;

class User implements JsonSerializable
{
    const BASEURL_API_USERS = 'https://plex.tv/api/users';

    private bool $allowCameraUpload;
    private bool $allowChannels;
    private bool $allowSubtitleAdmin;
    private bool $allowSync;
    private bool $allowTuners;
    private ?string $email;
    private string $filterAll;
    private string $filterMovies;
    private string $filterMusic;
    private string $filterPhotos;
    private string $filterTelevision;
    private bool $home;
    private int $id;
    private bool $protected;
    private ?int $recommendationsPlaylistId;
    private bool $restricted;
    private array $servers;
    private string $thumb;
    private string $title;
    private ?string $username;

    public function __construct(array $data)
    {
        $this->allowCameraUpload = $data['allowCameraUpload'] ? true : false;
        $this->allowChannels = $data['allowChannels'] ? true : false;
        $this->allowSubtitleAdmin = $data['allowSubtitleAdmin'] ? true : false;
        $this->allowSync = $data['allowSync'] ? true : false;
        $this->allowTuners = $data['allowTuners'] ? true : false;
        $this->email = empty($data['email']) ? null : $data['email'];
        $this->filterAll = $data['filterAll'];
        $this->filterMovies = $data['filterMovies'];
        $this->filterMusic = $data['filterMusic'];
        $this->filterPhotos = $data['filterPhotos'];
        $this->filterTelevision = $data['filterTelevision'];
        $this->home = $data['home'] ? true : false;
        $this->id = $data['id'];
        $this->protected = $data['protected'] ? true : false;
        $this->recommendationsPlaylistId = empty($data['recommendationsPlaylistId']) ? null : $data['recommendationsPlaylistId'];
        $this->restricted = $data['restricted'] ? true : false;
        $this->servers = $data['servers'];
        $this->thumb = $data['thumb'];
        $this->title = $data['title'];
        $this->username = empty($data['username']) ? null : $data['username'];
    }

    public function jsonSerialize() : array
    {
        return array(
            'allowCameraUpload' => $this->allowCameraUpload,
            'allowChannels' => $this->allowChannels,
            'allowSubtitleAdmin' => $this->allowSubtitleAdmin,
            'allowSync' => $this->allowSync,
            'allowTuners' => $this->allowTuners,
            'email' => $this->email,
            'filterAll' => $this->filterAll,
            'filterMovies' => $this->filterMovies,
            'filterMusic' => $this->filterMusic,
            'filterPhotos' => $this->filterPhotos,
            'filterTelevision' => $this->filterTelevision,
            'home' => $this->home,
            'id' => $this->id,
            'protected' => $this->protected,
            'recommendationsPlaylistId' => $this->recommendationsPlaylistId,
            'restricted' => $this->restricted,
            'servers' => $this->servers,
            'thumb' => $this->thumb,
            'title' => $this->title,
            'username' => $this->username,
        );
    }

    /**
     * Call this to retrieve users and sharing information from the Plex API.
     *
     * @param string $plex_token The Plex authentication token.
     * @return array An array of User objects.
     */
    public static function getUsers(string $plex_token) : array
    {
        $args = array();
        $args['X-Plex-Token'] = $plex_token;
        $args['X-Plex-Language'] = 'en';
        $url = sprintf('%s?%s', self::BASEURL_API_USERS, http_build_query($args, '', '&', \PHP_QUERY_RFC3986));
        $reply = HttpRequest::execute(HttpRequest::METHOD_GET, $url);

        if ($reply['reply_http_code'] == 401)
            throw new RuntimeException('access unauthorized, check plex token');

        if ($reply['reply_http_code'] != 200)
            throw new RuntimeException(sprintf('unexpected HTTP response code: %d', $reply['reply_http_code']));

        if (empty($reply['reply']))
            throw new RuntimeException('empty HTTP response');

        $mime_type_fields = array();
        if (preg_match('/^(\b[A-Za-z0-9\+\-\/]+\b)(?:;\s*charset=(\b[A-Za-z0-9\-]+\b))?$/i', $reply['reply_mime_type'], $mime_type_fields) !== 1)
            throw new RuntimeException(sprintf('cannot parse Content-Type header returned: %s', $reply['reply_mime_type']));

        $mime_type = $mime_type_fields[1];
        $charset = $mime_type_fields[2] ?? '';

        if (strtolower($mime_type) !== 'application/xml')
            throw new RuntimeException(sprintf('unexpected MIME-type returned: %s', $mime_type));

        try
        {
            $xml = XMLReader::XML($reply['reply'], $charset, \LIBXML_BIGLINES | \LIBXML_COMPACT | \LIBXML_HTML_NOIMPLIED);
            $users = array();
            $current_user = null;
            $servers = null;
            while ($xml->read())
            {
                if ($xml->nodeType === XMLReader::ELEMENT && $xml->name == 'User')
                {
                    $servers = array();
                    $current_user = array(
                        'allowCameraUpload' => $xml->getAttribute('allowCameraUpload'),
                        'allowChannels' => $xml->getAttribute('allowChannels'),
                        'allowSubtitleAdmin' => $xml->getAttribute('allowSubtitleAdmin'),
                        'allowSync' => $xml->getAttribute('allowSync'),
                        'allowTuners' => $xml->getAttribute('allowTuners'),
                        'email' => $xml->getAttribute('email'),
                        'filterAll' => $xml->getAttribute('filterAll'),
                        'filterMovies' => $xml->getAttribute('filterMovies'),
                        'filterMusic' => $xml->getAttribute('filterMusic'),
                        'filterPhotos' => $xml->getAttribute('filterPhotos'),
                        'filterTelevision' => $xml->getAttribute('filterTelevision'),
                        'home' => $xml->getAttribute('home'),
                        'id' => $xml->getAttribute('id'),
                        'protected' => $xml->getAttribute('protected'),
                        'recommendationsPlaylistId' => $xml->getAttribute('recommendationsPlaylistId'),
                        'restricted' => $xml->getAttribute('restricted'),
                        'servers' => $servers,
                        'thumb' => $xml->getAttribute('thumb'),
                        'title' => $xml->getAttribute('title'),
                        'username' => $xml->getAttribute('username'),
                    );
                }
                else if ($xml->nodeType === XMLReader::ELEMENT && $xml->name == 'Server' && $current_user)
                {
                    $servers[] = new Server(array(
                        'allLibraries' => $xml->getAttribute('allLibraries'),
                        'id' => $xml->getAttribute('id'),
                        'lastSeenAt' => $xml->getAttribute('lastSeenAt'),
                        'machineIdentifier' => $xml->getAttribute('machineIdentifier'),
                        'name' => $xml->getAttribute('name'),
                        'numLibraries' => $xml->getAttribute('numLibraries'),
                        'owned' => $xml->getAttribute('owned'),
                        'pending' => $xml->getAttribute('pending'),
                        'serverId' => $xml->getAttribute('serverId'),
                    ));
                }
                else if ($xml->nodeType === XMLReader::END_ELEMENT && $xml->name == 'User')
                {
                    $current_user['servers'] = $servers;
                    $users[] = new self($current_user);
                }
            }
        }
        finally
        {
            if ($xml instanceof XMLReader) $xml->close();

            return $users;
        }
    }
}
