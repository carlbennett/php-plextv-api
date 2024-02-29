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

    public function __construct(array $data)
    {
        $this->allLibraries = $data['allLibraries'] ? true : false;
        $this->id = $data['id'];
        $this->lastSeenAt = $data['lastSeenAt'];
        $this->machineIdentifier = $data['machineIdentifier'];
        $this->name = $data['name'];
        $this->numLibraries = $data['numLibraries'];
        $this->owned = $data['owned'] ? true : false;
        $this->pending = $data['pending'] ? true : false;
        $this->serverId = $data['serverId'];
    }

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
