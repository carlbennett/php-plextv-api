<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

namespace CarlBennett\PlexTvAPI;

use \CarlBennett\PlexTvAPI\Exceptions\PlexTvAPIException;

/**
 * Reads typed values from the attributes of a Plex.tv API element.
 *
 * @internal
 */
final class Attributes
{
    /**
     * @param array<string, mixed> $data The element's attributes, as returned by XMLReader::getAttribute().
     * @param string $element The element name, for error messages.
     */
    public function __construct(private array $data, private string $element) {}

    /**
     * Gets a flag, where a missing or empty attribute, or "0", means false.
     */
    public function bool(string $name): bool
    {
        return (bool) ($this->data[$name] ?? false);
    }

    /**
     * @throws PlexTvAPIException if the attribute is missing or not a whole number.
     */
    public function int(string $name): int
    {
        $value = $this->nullableInt($name);
        if ($value === null)
            throw new PlexTvAPIException(\sprintf('%s is missing its %s attribute', $this->element, $name));

        return $value;
    }

    /**
     * Gets a whole number, where a missing or empty attribute means null.
     *
     * @throws PlexTvAPIException if the attribute is not a whole number.
     */
    public function nullableInt(string $name): ?int
    {
        $value = $this->data[$name] ?? null;
        if ($value === null || $value === '') return null;
        if (\is_int($value)) return $value;

        if (!\is_string($value) || \preg_match('/^-?\d+$/', $value) !== 1)
            throw new PlexTvAPIException(\sprintf('%s has a non-numeric %s attribute', $this->element, $name));

        return (int) $value;
    }

    /**
     * Gets text, where an empty attribute is an empty string.
     *
     * @throws PlexTvAPIException if the attribute is missing.
     */
    public function string(string $name): string
    {
        $value = $this->data[$name] ?? null;
        if (!\is_string($value))
            throw new PlexTvAPIException(\sprintf('%s is missing its %s attribute', $this->element, $name));

        return $value;
    }

    /**
     * Gets text, where a missing or empty attribute means null.
     */
    public function nullableString(string $name): ?string
    {
        $value = $this->data[$name] ?? null;
        return \is_string($value) && $value !== '' ? $value : null;
    }
}
