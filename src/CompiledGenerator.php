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
 * Generates URLs from a precompiled route table (flat array from GroupMapper::dump()).
 *
 * @package Routes
 */
class CompiledGenerator
{
    /** @var array<int, array<string, mixed>> */
    private array $routes;

    /** @var array<string, int> */
    private array $routeNames;

    /** @var array<string, array<string, array{0: int[]}>> generation dict */
    private array $gendict = [];

    private bool $gensCreated = false;

    /** @var array<string, string> */
    private array $urlCache = [];

    /**
     * @param array{matchList: array, routeNames: array<string, int>} $compiled
     */
    public function __construct(array $compiled)
    {
        $this->routes = $compiled['matchList'];
        $this->routeNames = $compiled['routeNames'];
    }

    /**
     * Generate a URL by route name.
     *
     * @param string $routeName Named route identifier
     * @param array<string, string> $params Parameters to fill placeholders
     * @return string|null Generated URL or null
     */
    public function generateNamed(string $routeName, array $params = []): ?string
    {
        if (!isset($this->routeNames[$routeName])) {
            return null;
        }
        $route = $this->routes[$this->routeNames[$routeName]];
        return $this->generateFromRoute($route, $params);
    }

    /**
     * Generate a URL from parameters (finds best matching route).
     *
     * @param array<string, string> $kargs Parameters including controller/action
     * @return string|null Generated URL or null
     */
    public function generate(array $kargs): ?string
    {
        if (!$this->gensCreated) {
            $this->createGens();
        }

        $cacheKey = serialize($kargs);
        if (isset($this->urlCache[$cacheKey])) {
            return $this->urlCache[$cacheKey];
        }

        $controller = $kargs['controller'] ?? '*';
        $action = $kargs['action'] ?? '*';

        $actionList = $this->gendict[$controller] ?? $this->gendict['*'] ?? [];
        $indices = $actionList[$action][0] ?? $actionList['*'][0] ?? null;

        if ($indices === null) {
            return null;
        }

        $keys = array_keys($kargs);

        // Filter to routes whose minKeys are satisfied
        $candidates = [];
        foreach ($indices as $idx) {
            $route = $this->routes[$idx];
            if (count(array_diff($route['minKeys'], $keys)) === 0) {
                $candidates[] = $route;
            }
        }

        // Sort: prefer routes matching more of our keys, then fewer maxKeys
        usort($candidates, function (array $a, array $b) use ($keys) {
            $ac = count(array_intersect($a['maxKeys'], $keys));
            $bc = count(array_intersect($b['maxKeys'], $keys));
            if ($ac === $bc) {
                return count($a['maxKeys']) - count($b['maxKeys']);
            }
            return $bc - $ac;
        });

        foreach ($candidates as $route) {
            $fail = false;
            foreach ($route['hardCoded'] as $key) {
                $kval = $kargs[$key] ?? null;
                if ($kval === null) {
                    continue;
                }
                if ($kval != $route['defaults'][$key]) {
                    $fail = true;
                    break;
                }
            }
            if ($fail) {
                continue;
            }
            $path = $this->generateFromRoute($route, $kargs);
            if ($path !== null) {
                $this->urlCache[$cacheKey] = $path;
                return $path;
            }
        }
        return null;
    }

    /**
     * Generate URL from a single route's data.
     */
    private function generateFromRoute(array $route, array $kargs): ?string
    {
        $reqs = $route['reqs'] ?? [];

        // Verify requirements
        foreach ($reqs as $key => $pattern) {
            $value = $kargs[$key] ?? null;
            if ($value !== null && !preg_match('@^' . str_replace('@', '\@', $pattern) . '$@', $value)) {
                return null;
            }
        }

        // Check method condition
        if (isset($kargs['method']) && isset($route['conditions']['method'])) {
            if (!in_array(strtoupper($kargs['method']), $route['conditions']['method'])) {
                return null;
            }
            unset($kargs['method']);
        }

        $routeList = $route['routeList'] ?? [];
        $defaults = $route['defaults'] ?? [];
        $maxKeys = $route['maxKeys'] ?? [];
        $splitChars = ['/', ',', ';', '.', '#'];

        // Build URL from routeList in reverse (same algorithm as Route::generate)
        $routeBackwards = array_reverse($routeList);
        $urlList = [];
        $gaps = false;

        foreach ($routeBackwards as $part) {
            if (is_array($part) && $part['type'] === ':') {
                $arg = $part['name'];
                $hasArg = array_key_exists($arg, $kargs);
                $hasDefault = array_key_exists($arg, $defaults);

                if ($hasDefault && !$hasArg && !$gaps) {
                    continue;
                }
                if ($hasDefault && $hasArg && $kargs[$arg] == $defaults[$arg] && !$gaps) {
                    continue;
                }
                if ($hasArg && $kargs[$arg] === null && $hasDefault && !$gaps) {
                    continue;
                } elseif ($hasArg) {
                    $val = $kargs[$arg] === null ? 'null' : $kargs[$arg];
                } elseif ($hasDefault && $defaults[$arg] !== null) {
                    $val = $defaults[$arg];
                } else {
                    return null;
                }

                $urlList[] = Utils::urlQuote((string) $val);
                if ($hasArg) {
                    unset($kargs[$arg]);
                }
                $gaps = true;
            } elseif (is_array($part) && $part['type'] === '*') {
                $arg = $part['name'];
                $kar = $kargs[$arg] ?? null;
                if ($kar !== null) {
                    $urlList[] = Utils::urlQuote((string) $kar);
                    $gaps = true;
                }
            } elseif (!empty($part) && in_array(substr($part, -1), $splitChars)) {
                if (!$gaps && in_array($part, $splitChars)) {
                    continue;
                } elseif (!$gaps) {
                    $gaps = true;
                    $urlList[] = substr($part, 0, -1);
                } else {
                    $gaps = true;
                    $urlList[] = $part;
                }
            } else {
                $gaps = true;
                $urlList[] = $part;
            }
        }

        $urlList = array_reverse($urlList);
        $url = implode('', $urlList);
        if (!str_starts_with($url, '/')) {
            $url = '/' . $url;
        }

        // Append extra params as query string
        $extras = array_diff(array_keys($kargs), $maxKeys);
        if (!empty($extras)) {
            $queryParts = [];
            foreach ($extras as $key) {
                if ($key !== 'action' && $key !== 'controller') {
                    $queryParts[$key] = $kargs[$key];
                }
            }
            if (!empty($queryParts)) {
                $url .= '?' . http_build_query($queryParts);
            }
        }

        // Prepend path prefix
        $pathPrefix = $route['pathPrefix'] ?? null;
        if ($pathPrefix !== null) {
            $url = $pathPrefix . $url;
        }

        return $url;
    }

    private function createGens(): void
    {
        $actionList = ['*' => true];
        $controllerList = ['*' => true];

        foreach ($this->routes as $idx => $route) {
            if ($route['secondary'] ?? false) {
                continue;
            }
            if (isset($route['defaults']['controller'])) {
                $controllerList[$route['defaults']['controller']] = true;
            }
            if (isset($route['defaults']['action'])) {
                $actionList[$route['defaults']['action']] = true;
            }
        }

        $actionKeys = array_keys($actionList);
        $controllerKeys = array_keys($controllerList);

        $gendict = [];
        foreach ($this->routes as $idx => $route) {
            if ($route['secondary'] ?? false) {
                continue;
            }
            $clist = $controllerKeys;
            $alist = $actionKeys;
            $hardCoded = $route['hardCoded'] ?? [];
            $defaults = $route['defaults'] ?? [];

            if (in_array('controller', $hardCoded)) {
                $clist = [$defaults['controller']];
            }
            if (in_array('action', $hardCoded)) {
                $alist = [$defaults['action']];
            }
            foreach ($clist as $controller) {
                foreach ($alist as $action) {
                    if (!isset($gendict[$controller])) {
                        $gendict[$controller] = [];
                    }
                    if (!isset($gendict[$controller][$action])) {
                        $gendict[$controller][$action] = [[]];
                    }
                    $gendict[$controller][$action][0][] = $idx;
                }
            }
        }
        if (!isset($gendict['*'])) {
            $gendict['*'] = [];
        }

        $this->gendict = $gendict;
        $this->gensCreated = true;
    }
}
