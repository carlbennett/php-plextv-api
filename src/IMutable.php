<?php

namespace CarlBennett\PlexTvAPI;

/**
 * Exposes standard methods for allocating and committing an object from and to storage.
 */
interface IMutable
{
    /**
     * Call this method to allocate the object from storage using its object properties.
     *
     * @return void
     */
    public function allocate(): void;

    /**
     * Call this method to commit the object to storage using its object properties.
     *
     * @return void
     */
    public function commit(): void;
}
