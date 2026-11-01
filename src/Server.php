<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

namespace CarlBennett\PlexTvAPI;

class Server implements \JsonSerializable
{
    private bool $allLibraries;
    private int $id;
    private int $lastSeenAt;
    private string $machineIdentifier;
    private string $name;
    private int $numLibraries;
    private bool $owned;
    private bool $pending;
    private int $serverId;

    /**
     * @param array<string, ?string> $data The attributes of a Server element from the Plex.tv users API.
     * @throws Exceptions\PlexTvAPIException if a required attribute is missing or invalid.
     */
    public function __construct(array $data)
    {
        $attributes = new Attributes($data, 'Server');
        $this->allLibraries = $attributes->bool('allLibraries');
        $this->id = $attributes->int('id');
        $this->lastSeenAt = $attributes->int('lastSeenAt');
        $this->machineIdentifier = $attributes->string('machineIdentifier');
        $this->name = $attributes->string('name');
        $this->numLibraries = $attributes->int('numLibraries');
        $this->owned = $attributes->bool('owned');
        $this->pending = $attributes->bool('pending');
        $this->serverId = $attributes->int('serverId');
    }

    /**
     * @return array<string, bool|int|string>
     */
    public function jsonSerialize(): array
    {
        return [
            'allLibraries' => $this->allLibraries,
            'id' => $this->id,
            'lastSeenAt' => $this->lastSeenAt,
            'machineIdentifier' => $this->machineIdentifier,
            'name' => $this->name,
            'numLibraries' => $this->numLibraries,
            'owned' => $this->owned,
            'pending' => $this->pending,
            'serverId' => $this->serverId,
        ];
    }
}
