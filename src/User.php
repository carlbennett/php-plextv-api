<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

namespace CarlBennett\PlexTvAPI;

use \CarlBennett\PlexTvAPI\Exceptions\PlexTvAPIException;
use \CarlBennett\PlexTvAPI\HttpRequest;
use \CarlBennett\PlexTvAPI\IMutable;
use \CarlBennett\PlexTvAPI\Server;
use \XMLReader;

class User implements IMutable, \JsonSerializable
{
    public const BASEURL_API_USERS = 'https://plex.tv/api/users';

    /** @var array<string, mixed> */
    private array $_internaldata;

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
    /** @var list<Server> */
    private array $servers;
    private string $thumb;
    private string $title;
    private ?string $username;

    /**
     * Constructs a Plex user object from serialized Plex user information.
     *
     * @param array<string, mixed> $data The serialized data containing the Plex user object properties.
     * @throws PlexTvAPIException if $data is empty, or a required attribute is missing or invalid.
     */
    public function __construct(array $data)
    {
        $this->_internaldata = $data;
        $this->allocate();
    }

    /**
     * Call this method to allocate the object from storage using its object properties. This is part of the IMutable interface.
     *
     * @return void
     */
    public function allocate(): void
    {
        $data = $this->_internaldata;
        if (empty($data))
            throw new PlexTvAPIException('empty array cannot be deserialized into object');

        $attributes = new Attributes($data, 'User');
        $this->allowCameraUpload = $attributes->bool('allowCameraUpload');
        $this->allowChannels = $attributes->bool('allowChannels');
        $this->allowSubtitleAdmin = $attributes->bool('allowSubtitleAdmin');
        $this->allowSync = $attributes->bool('allowSync');
        $this->allowTuners = $attributes->bool('allowTuners');
        $this->email = $attributes->nullableString('email');
        $this->filterAll = $attributes->string('filterAll');
        $this->filterMovies = $attributes->string('filterMovies');
        $this->filterMusic = $attributes->string('filterMusic');
        $this->filterPhotos = $attributes->string('filterPhotos');
        $this->filterTelevision = $attributes->string('filterTelevision');
        $this->home = $attributes->bool('home');
        $this->id = $attributes->int('id');
        $this->protected = $attributes->bool('protected');
        $this->recommendationsPlaylistId = $attributes->nullableInt('recommendationsPlaylistId');
        $this->restricted = $attributes->bool('restricted');
        $this->servers = \array_values(\array_filter((array) ($data['servers'] ?? []), fn ($server) => $server instanceof Server));
        $this->thumb = $attributes->string('thumb');
        $this->title = $attributes->string('title');
        $this->username = $attributes->nullableString('username');
    }

    /**
     * Call this method to commit the object to storage using its object properties. This is part of the IMutable interface.
     *
     * @return void
     */
    public function commit(): void
    {
        throw new PlexTvAPIException('this method is not yet implemented');
    }

    /**
     * Gets whether this Plex user is allowed to upload content from their camera.
     *
     * @return bool True if allowed, false otherwise.
     */
    public function getAllowCameraUpload(): bool
    {
        return $this->allowCameraUpload;
    }

    /**
     * Gets whether this Plex user is allowed access to television channels.
     *
     * @return bool True if allowed, false otherwise.
     */
    public function getAllowChannels(): bool
    {
        return $this->allowChannels;
    }

    /**
     * Gets whether this Plex user is allowed to manage subtitles for library content.
     *
     * @return bool True if allowed, false otherwise.
     */
    public function getAllowSubtitleAdmin(): bool
    {
        return $this->allowSubtitleAdmin;
    }

    /**
     * Gets whether this Plex user is allowed to download content for offline viewing.
     *
     * @return bool True if allowed, false otherwise.
     */
    public function getAllowSync(): bool
    {
        return $this->allowSync;
    }

    /**
     * Gets whether this Plex user is allowed access to television tuner cards.
     *
     * @return bool True if allowed, false otherwise.
     */
    public function getAllowTuners(): bool
    {
        return $this->allowTuners;
    }

    /**
     * Gets the email address for this Plex user.
     *
     * @return string|null The email address of this Plex user, or null if not set.
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * The filter for all library media content which this Plex user is allowed access. If empty, there is no restriction.
     *
     * @return string The filter setting.
     */
    public function getFilterAll(): string
    {
        return $this->filterAll;
    }

    /**
     * The filter for movie library media content which this Plex user is allowed access. If empty, there is no restriction.
     *
     * @return string The filter setting.
     */
    public function getFilterMovies(): string
    {
        return $this->filterMovies;
    }

    /**
     * The filter for music library media content which this Plex user is allowed access. If empty, there is no restriction.
     *
     * @return string The filter setting.
     */
    public function getFilterMusic(): string
    {
        return $this->filterMusic;
    }

    /**
     * The filter for photos library media content which this Plex user is allowed access. If empty, there is no restriction.
     *
     * @return string The filter setting.
     */
    public function getFilterPhotos(): string
    {
        return $this->filterPhotos;
    }

    /**
     * The filter for television library media content which this Plex user is allowed access. If empty, there is no restriction.
     *
     * @return string The filter setting.
     */
    public function getFilterTelevision(): string
    {
        return $this->filterTelevision;
    }

    /**
     * Gets whether the Plex user is a Plex Home user.
     *
     * @return bool True if home user, false otherwise.
     */
    public function getHome(): bool
    {
        return $this->home;
    }

    /**
     * Gets Plex.tv's unique identifier code for this Plex user.
     *
     * @return int The id of the Plex user.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Gets whether the Plex user is protected via PIN code.
     *
     * @return bool True if protected, false otherwise.
     */
    public function getProtected(): bool
    {
        return $this->protected;
    }

    /**
     * Gets the recommendations playlist for this Plex user.
     *
     * @return int|null The playlist id number, or null if none set.
     */
    public function getRecommendationsPlaylistId(): ?int
    {
        return $this->recommendationsPlaylistId;
    }

    /**
     * Gets whether the Plex user is restricted via parental controls.
     *
     * @return bool True if restricted, false otherwise.
     */
    public function getRestricted(): bool
    {
        return $this->restricted;
    }

    /**
     * Gets the servers that this user has been added to.
     *
     * @return list<Server> The array of Server objects.
     */
    public function getServers(): array
    {
        return $this->servers;
    }

    /**
     * Gets the avatar thumbnail url for this Plex user.
     *
     * @return string The url to the user's avatar thumbnail on Plex.
     */
    public function getThumb(): string
    {
        return $this->thumb;
    }

    /**
     * Gets the title of this Plex user, used for printing the name of the user in user interfaces.
     *
     * @return string The title of this Plex user.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Gets the username of this Plex user. See also getTitle().
     *
     * @return string|null The username of this Plex user, or null if not set.
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * Call this static method to retrieve users and sharing information from the Plex API.
     *
     * @param string $plex_token The Plex authentication token.
     * @return list<User> An array of User objects.
     * @throws PlexTvAPIException if the HTTP request fails or the response cannot be parsed.
     */
    public static function getUsers(string $plex_token): array
    {
        $args = [];
        $args['X-Plex-Language'] = 'en';
        $url = \sprintf('%s?%s', self::BASEURL_API_USERS, \http_build_query($args, '', '&', \PHP_QUERY_RFC3986));
        $reply = HttpRequest::execute(HttpRequest::METHOD_GET, $url, '', null, ['X-Plex-Token: ' . $plex_token]);

        if ($reply['reply_http_code'] == 401)
            throw new PlexTvAPIException('access unauthorized, check plex token');

        $charset = HttpRequest::expectReply($reply, 'application/xml');

        $xml = new XMLReader();
        $use_errors = \libxml_use_internal_errors(true);
        \libxml_clear_errors();

        try
        {
            // an empty encoding is invalid; null lets libxml use the XML declaration or its default
            if (!$xml->XML($reply['reply'], $charset !== '' ? $charset : null, \LIBXML_BIGLINES | \LIBXML_COMPACT))
                throw new PlexTvAPIException('cannot parse XML response');

            $users = [];
            $current_user = null;
            $servers = null;

            while ($xml->read())
            {
                if ($xml->nodeType === XMLReader::ELEMENT && $xml->name == 'User')
                {
                    $servers = [];
                    $current_user = [
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
                    ];

                    if ($xml->isEmptyElement)
                    {
                        // <User .../> has no servers, and XMLReader emits no END_ELEMENT for it
                        $users[] = new self($current_user);
                        $current_user = null;
                    }
                }
                else if ($xml->nodeType === XMLReader::ELEMENT && $xml->name == 'Server' && $current_user)
                {
                    $servers[] = new Server([
                        'allLibraries' => $xml->getAttribute('allLibraries'),
                        'id' => $xml->getAttribute('id'),
                        'lastSeenAt' => $xml->getAttribute('lastSeenAt'),
                        'machineIdentifier' => $xml->getAttribute('machineIdentifier'),
                        'name' => $xml->getAttribute('name'),
                        'numLibraries' => $xml->getAttribute('numLibraries'),
                        'owned' => $xml->getAttribute('owned'),
                        'pending' => $xml->getAttribute('pending'),
                        'serverId' => $xml->getAttribute('serverId'),
                    ]);
                }
                else if ($xml->nodeType === XMLReader::END_ELEMENT && $xml->name == 'User')
                {
                    $current_user['servers'] = $servers;
                    $users[] = new self($current_user);
                    $current_user = null;
                }
            }

            $error = \libxml_get_last_error();
            if ($error !== false)
                throw new PlexTvAPIException(\sprintf('malformed XML response: %s', \trim($error->message)));
        }
        finally
        {
            $xml->close();
            \libxml_clear_errors();
            \libxml_use_internal_errors($use_errors);
        }

        return $users;
    }

    /**
     * Serializes this object into JSON format. This is part of the PHP-native JsonSerializable interface.
     *
     * @return array<string, mixed> The JSON object which represents this Plex user.
     */
    public function jsonSerialize(): array
    {
        return [
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
            'servers' => $this->servers, // Server objects implement JsonSerializable
            'thumb' => $this->thumb,
            'title' => $this->title,
            'username' => $this->username,
        ];
    }
}
