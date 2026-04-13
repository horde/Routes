<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Routes;

use ArrayAccess;
use LogicException;

/**
 * Immutable value object representing the result of a route match.
 *
 * Holds both the match dictionary (key/value pairs extracted from the URL)
 * and a reference to the matched Route object, which carries metadata like
 * the route name, route path pattern, defaults, and middleware stack.
 *
 * @implements ArrayAccess<string, mixed>
 */
final class MatchResult implements ArrayAccess
{
    /**
     * @param array<string, mixed> $matchDict  The match dictionary from Route::match()
     * @param Route                $route      The Route object that matched
     * @param string|null          $routeName  Resolved route name (from Route or Mapper lookup)
     */
    public function __construct(
        private readonly array $matchDict,
        private readonly Route $route,
        private readonly ?string $routeName = null,
    ) {}

    /**
     * Return the resolved route name.
     *
     * For routes created via RouteBuilder this comes from Route->routeName.
     * For legacy connect() routes the Matcher resolves it from Mapper->routeNames.
     */
    public function getRouteName(): ?string
    {
        return $this->routeName ?? $this->route->routeName;
    }

    /**
     * Return the matched Route object.
     */
    public function getRoute(): Route
    {
        return $this->route;
    }

    /**
     * Return the route path pattern (e.g. '/ticket/:id').
     */
    public function getRoutePath(): string
    {
        return $this->route->routePath;
    }

    /**
     * Return the controller class name from the match dictionary.
     */
    public function getController(): ?string
    {
        return isset($this->matchDict['controller']) ? (string) $this->matchDict['controller'] : null;
    }

    /**
     * Return the action from the match dictionary.
     */
    public function getAction(): ?string
    {
        return isset($this->matchDict['action']) ? (string) $this->matchDict['action'] : null;
    }

    /**
     * Return a value from the match dictionary.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->matchDict[$key] ?? $default;
    }

    /**
     * Check whether a key exists in the match dictionary.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->matchDict);
    }

    /**
     * Whether the match was against a secondary (legacy) route.
     */
    public function isSecondary(): bool
    {
        return $this->route->secondary;
    }

    /**
     * Return the raw match dictionary as a plain array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->matchDict;
    }

    // --- ArrayAccess for backward compatibility ---

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->matchDict);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->matchDict[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('MatchResult is immutable');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('MatchResult is immutable');
    }
}
