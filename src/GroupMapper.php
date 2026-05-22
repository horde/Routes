<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 */

namespace Horde\Routes;

use BadMethodCallException;

/**
 * Group-aware route mapper with definition-time resolution.
 *
 * Independent of the legacy Mapper class. Supports group(), buildRoute(),
 * compile(), dump(). Does NOT support connect().
 *
 * Routes are fully resolved at definition time: group context (prefix, host,
 * scheme, port, defaults, stack) is baked into the RouteBuilder before build().
 * The resulting Route objects are self-contained and serializable.
 *
 * @package Routes
 */
class GroupMapper
{
    /** @var array<array<string, mixed>> */
    private array $groupStack = [];

    /** @var array<string> */
    private array $defaultStack = [];

    /** @var Route[] */
    private array $matchList = [];

    /** @var array<string, Route> */
    private array $routeNames = [];

    /** @var array<string, Route[]> keyed by serialized maxKeys */
    private array $maxKeys = [];

    private bool $compiled = false;

    private bool $gensCreated = false;

    /** @var array<string, array<string, array{Route[], mixed[]}>> */
    private array $gendict = [];

    /** @var array<string, string> */
    private array $urlCache = [];

    /** @var Route[] temp for _keysort */
    private array $keysortKeys = [];

    public array $environ = [];

    public function __construct() {}

    public function setDefaultStack(array $stack): void
    {
        $this->defaultStack = $stack;
    }

    /**
     * Define a group of routes sharing common configuration.
     *
     * @param array{prefix?: string, host?: string, scheme?: string, port?: int, defaults?: array, stack?: array} $config
     */
    public function group(array $config, callable $fn): void
    {
        $this->groupStack[] = $config;
        $fn($this);
        array_pop($this->groupStack);
    }

    /**
     * Start a fluent route definition. Group context is applied to the builder.
     */
    public function buildRoute(?string $uri = null, ?string $name = null): GroupFluentRouteBuilder
    {
        $merged = $this->mergedGroupConfig();

        $prefix = $merged['prefix'];
        $fullUri = $prefix . ($uri ?? '');

        $builder = new RouteBuilder($fullUri);
        if ($name !== null) {
            $builder->withName($name);
        }

        if ($merged['host'] !== null) {
            $builder->withHost($merged['host']);
        }
        if ($merged['scheme'] !== null) {
            $builder->withScheme($merged['scheme']);
        }
        if ($merged['port'] !== null) {
            $builder->withPort($merged['port']);
        }
        if (!empty($merged['defaults'])) {
            $builder->withDefaults($merged['defaults']);
        }

        $stack = $merged['stack'] ?? $this->defaultStack;
        if (!empty($stack)) {
            $builder->withMiddleware($stack);
        }

        return new GroupFluentRouteBuilder($this, $builder);
    }

    /**
     * Add a built route (or RouteBuilder) to this mapper.
     */
    public function addRoute(RouteBuilder|Route|array $routeOrBuilder): void
    {
        if ($routeOrBuilder instanceof RouteBuilder) {
            $merged = $this->mergedGroupConfig();
            $prefix = $merged['prefix'];
            if ($prefix !== '') {
                $routeOrBuilder->prefixSecondaryPaths($prefix);
            }
            $routes = $routeOrBuilder->build();
            $routes = is_array($routes) ? $routes : [$routes];
        } elseif (is_array($routeOrBuilder)) {
            $routes = $routeOrBuilder;
        } else {
            $routes = [$routeOrBuilder];
        }

        foreach ($routes as $index => $route) {
            $this->matchList[] = $route;
            if ($index === 0 && $route->routeName !== null) {
                $this->routeNames[$route->routeName] = $route;
            }
            if (!$route->secondary && !$route->static) {
                $key = serialize($route->maxKeys);
                if (isset($this->maxKeys[$key])) {
                    $this->maxKeys[$key][] = $route;
                } else {
                    $this->maxKeys[$key] = [$route];
                }
            }
        }
        $this->compiled = false;
        $this->gensCreated = false;
    }

    /**
     * Legacy connect() is not supported. Throws with migration guidance.
     */
    public function connect(mixed ...$args): never
    {
        $parts = [];
        foreach ($args as $arg) {
            $parts[] = var_export($arg, true);
        }
        throw new BadMethodCallException(
            'connect() is not supported by GroupMapper. Use buildRoute() instead. '
            . 'Attempted: connect(' . implode(', ', $parts) . ')'
        );
    }

    /**
     * Compile all route regexps. Must be called before match/generate.
     */
    public function compile(): void
    {
        foreach ($this->maxKeys as $routes) {
            foreach ($routes as $route) {
                $route->makeRegexp([]);
            }
        }
        foreach ($this->matchList as $route) {
            if ($route->secondary && !$route->static) {
                $route->makeRegexp([]);
            }
        }
        $this->compiled = true;
    }

    /**
     * Export the compiled route table as an opcacheable array.
     *
     * @return array{matchList: array, routeNames: array<string, int>}
     */
    public function dump(): array
    {
        if (!$this->compiled) {
            $this->compile();
        }
        $matchList = [];
        foreach ($this->matchList as $route) {
            $matchList[] = $route->toArray();
        }
        $routeNames = [];
        foreach ($this->routeNames as $name => $route) {
            $idx = array_search($route, $this->matchList, true);
            if ($idx !== false) {
                $routeNames[$name] = $idx;
            }
        }
        return ['matchList' => $matchList, 'routeNames' => $routeNames];
    }

    /**
     * Match a URL. Returns the match dict or null.
     */
    public function match(string $url): ?array
    {
        if (!$this->compiled) {
            $this->compile();
        }

        foreach ($this->matchList as $route) {
            if ($route->static) {
                continue;
            }
            $match = $route->match($url, [
                'environ' => $this->environ,
                'subDomains' => false,
                'subDomainsIgnore' => [],
                'domainMatch' => '[^\.\/]+?\.[^\.\/]+',
            ]);
            if ($match) {
                return $match;
            }
        }
        return null;
    }

    /**
     * Match a URL, returning [matchDict, Route] or null.
     */
    public function routematch(string $url): ?array
    {
        if (!$this->compiled) {
            $this->compile();
        }

        foreach ($this->matchList as $route) {
            if ($route->static) {
                continue;
            }
            $match = $route->match($url, [
                'environ' => $this->environ,
                'subDomains' => false,
                'subDomainsIgnore' => [],
                'domainMatch' => '[^\.\/]+?\.[^\.\/]+',
            ]);
            if ($match) {
                return [$match, $route];
            }
        }
        return null;
    }

    /**
     * Generate a URL from parameters.
     *
     * @param array<string, string> $kargs Route parameters
     * @return string|null Generated URL or null
     */
    public function generate(array $kargs): ?string
    {
        if (!$this->gensCreated) {
            $this->createGens();
        }

        $controller = $kargs['controller'] ?? '*';
        $action = $kargs['action'] ?? '*';

        $cacheKey = serialize($kargs);
        if (isset($this->urlCache[$cacheKey])) {
            return $this->urlCache[$cacheKey];
        }

        $actionList = $this->gendict[$controller] ?? $this->gendict['*'] ?? [];
        $keyList = $actionList[$action][0] ?? $actionList['*'][0] ?? null;

        if ($keyList === null) {
            return null;
        }

        $keys = array_keys($kargs);
        $this->keysortKeys = $keys;

        $newList = [];
        foreach ($keyList as $route) {
            if (count(Utils::arraySubtract($route->minKeys, $keys)) === 0) {
                $newList[] = $route;
            }
        }

        usort($newList, function (Route $a, Route $b) {
            $ac = count(array_intersect($a->maxKeys, $this->keysortKeys));
            $bc = count(array_intersect($b->maxKeys, $this->keysortKeys));
            if ($ac === $bc) {
                return count($a->maxKeys) - count($b->maxKeys);
            }
            return $bc - $ac;
        });

        foreach ($newList as $route) {
            $fail = false;
            foreach ($route->hardCoded as $key) {
                $kval = $kargs[$key] ?? null;
                if ($kval === null) {
                    continue;
                }
                if ($kval != $route->defaults[$key]) {
                    $fail = true;
                    break;
                }
            }
            if ($fail) {
                continue;
            }
            $path = $route->generate($kargs);
            if ($path) {
                $this->urlCache[$cacheKey] = $path;
                return $path;
            }
        }
        return null;
    }

    /**
     * Get all registered routes (for debugging/inspection).
     *
     * @return Route[]
     */
    public function getMatchList(): array
    {
        return $this->matchList;
    }

    /**
     * Get named routes.
     *
     * @return array<string, Route>
     */
    public function getRouteNames(): array
    {
        return $this->routeNames;
    }

    private function createGens(): void
    {
        $actionList = ['*' => true];
        $controllerList = ['*' => true];

        foreach ($this->matchList as $route) {
            if ($route->static || $route->secondary) {
                continue;
            }
            if (isset($route->defaults['controller'])) {
                $controllerList[$route->defaults['controller']] = true;
            }
            if (isset($route->defaults['action'])) {
                $actionList[$route->defaults['action']] = true;
            }
        }

        $actionList = array_keys($actionList);
        $controllerList = array_keys($controllerList);

        $gendict = [];
        foreach ($this->matchList as $route) {
            if ($route->static || $route->secondary) {
                continue;
            }
            $clist = $controllerList;
            $alist = $actionList;
            if (in_array('controller', $route->hardCoded)) {
                $clist = [$route->defaults['controller']];
            }
            if (in_array('action', $route->hardCoded)) {
                $alist = [$route->defaults['action']];
            }
            foreach ($clist as $controller) {
                foreach ($alist as $action) {
                    if (!isset($gendict[$controller])) {
                        $gendict[$controller] = [];
                    }
                    if (!isset($gendict[$controller][$action])) {
                        $gendict[$controller][$action] = [[], []];
                    }
                    $gendict[$controller][$action][0][] = $route;
                }
            }
        }
        if (!isset($gendict['*'])) {
            $gendict['*'] = [];
        }

        $this->gendict = $gendict;
        $this->gensCreated = true;
    }

    /**
     * Merge all active group configs. Prefix concatenates, others: inner wins.
     *
     * @return array{prefix: string, host: ?string, scheme: ?string, port: ?int, defaults: array, stack: ?array}
     */
    private function mergedGroupConfig(): array
    {
        $merged = [
            'prefix' => '',
            'host' => null,
            'scheme' => null,
            'port' => null,
            'defaults' => [],
            'stack' => null,
        ];
        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $merged['prefix'] .= $group['prefix'];
            }
            if (isset($group['host'])) {
                $merged['host'] = $group['host'];
            }
            if (isset($group['scheme'])) {
                $merged['scheme'] = $group['scheme'];
            }
            if (isset($group['port'])) {
                $merged['port'] = $group['port'];
            }
            if (isset($group['defaults'])) {
                $merged['defaults'] = array_merge($merged['defaults'], $group['defaults']);
            }
            if (isset($group['stack'])) {
                $merged['stack'] = $group['stack'];
            }
        }
        return $merged;
    }
}
