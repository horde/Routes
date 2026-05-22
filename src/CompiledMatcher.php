<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 */

namespace Horde\Routes;

/**
 * Matches URLs against a precompiled route table (flat array from GroupMapper::dump()).
 *
 * No Route objects are instantiated at runtime. All matching happens against
 * the plain array structure loaded from an opcached file.
 *
 * @package Routes
 */
class CompiledMatcher
{
    /** @var array<int, array<string, mixed>> */
    private array $routes;

    /**
     * @param array{matchList: array, routeNames: array<string, int>} $compiled
     */
    public function __construct(array $compiled)
    {
        $this->routes = $compiled['matchList'];
    }

    /**
     * Match a URL and return the match dict, or null.
     *
     * @param string $url URL path to match
     * @param array<string, mixed> $environ Server environment (REQUEST_METHOD, HTTP_HOST, HTTPS, etc.)
     */
    public function match(string $url, array $environ = []): ?array
    {
        $result = $this->doMatch($url, $environ);
        return $result !== null ? $result[0] : null;
    }

    /**
     * Match a URL and return [matchDict, routeData] or null.
     *
     * @param string $url URL path to match
     * @param array<string, mixed> $environ Server environment
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}|null
     */
    public function routematch(string $url, array $environ = []): ?array
    {
        return $this->doMatch($url, $environ);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}|null
     */
    private function doMatch(string $url, array $environ): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['secondary'] ?? false) {
                // secondary routes participate in matching
            }

            $matchUrl = $url;

            // Strip trailing slash
            if (strlen($matchUrl) > 1 && str_ends_with($matchUrl, '/')) {
                $matchUrl = substr($matchUrl, 0, -1);
            }

            // Path prefix check
            $pathPrefix = $route['pathPrefix'] ?? null;
            if ($pathPrefix !== null) {
                if (!str_starts_with($matchUrl, $pathPrefix)) {
                    continue;
                }
                $matchUrl = substr($matchUrl, strlen($pathPrefix)) ?: '/';
                if (strlen($matchUrl) > 1 && str_ends_with($matchUrl, '/')) {
                    $matchUrl = substr($matchUrl, 0, -1);
                }
            }

            // Regexp match
            $regexp = $route['regexp'] ?? '';
            if ($regexp === '') {
                continue;
            }
            $match = @preg_match('@' . str_replace('@', '\@', $regexp) . '@', $matchUrl, $matches);
            if ($match === false || $match === 0) {
                continue;
            }

            // Method condition
            $conditions = $route['conditions'] ?? null;
            if ($conditions !== null && isset($conditions['method'])) {
                $requestMethod = $environ['REQUEST_METHOD'] ?? '';
                if ($requestMethod === '' || !in_array($requestMethod, $conditions['method'])) {
                    continue;
                }
            }

            // Scheme check
            $scheme = $route['scheme'] ?? null;
            if ($scheme !== null) {
                $isHttps = !empty($environ['HTTPS']) && $environ['HTTPS'] !== 'off';
                $currentScheme = $isHttps ? 'https' : 'http';
                if ($currentScheme !== $scheme) {
                    continue;
                }
            }

            // Host check
            $host = $route['host'] ?? null;
            if ($host !== null) {
                $httpHost = $environ['HTTP_HOST'] ?? $environ['SERVER_NAME'] ?? '';
                $hostOnly = explode(':', $httpHost)[0];
                if (strcasecmp($hostOnly, $host) !== 0) {
                    continue;
                }
            }

            // Port check
            $port = $route['port'] ?? null;
            if ($port !== null) {
                $httpHost = $environ['HTTP_HOST'] ?? '';
                $parts = explode(':', $httpHost);
                $currentPort = isset($parts[1]) ? (int) $parts[1] : (
                    (!empty($environ['HTTPS']) && $environ['HTTPS'] !== 'off') ? 443 : 80
                );
                if ($currentPort !== $port) {
                    continue;
                }
            }

            // Build result from named captures + defaults
            $defaults = $route['defaults'] ?? [];
            $result = [];

            // Extract named captures only
            foreach ($matches as $key => $val) {
                if (is_int($key)) {
                    continue;
                }
                if ($val === '' && isset($defaults[$key]) && $defaults[$key] !== '') {
                    $result[$key] = $defaults[$key];
                } else {
                    $result[$key] = urldecode($val);
                }
            }

            // Fill in defaults not in matched URL
            foreach ($defaults as $key => $val) {
                if (!isset($result[$key])) {
                    $result[$key] = $val;
                }
            }

            // Include stack (always present in compiled routes from GroupMapper)
            $stack = $route['stack'] ?? null;
            if ($stack !== null) {
                $result['stack'] = $stack;
            }

            // Function condition (callable stored in compiled — unlikely but support it)
            if ($conditions !== null && isset($conditions['function'])) {
                if (!call_user_func($conditions['function'], $environ, $result)) {
                    continue;
                }
            }

            return [$result, $route];
        }

        return null;
    }
}
