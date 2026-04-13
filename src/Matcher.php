<?php

/**
 * Helper class to generate the match dictionary for the incoming request.
 *
 * PHP version 7
 *
 * @category Horde
 * @package  Horde_Routes
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Routes;

use Horde_Controller_Request;
use Horde_Support_Array;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * Generates the match dictionary for the incoming request.
 *
 * Copyright 2011-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you did not
 * receive this file, see
 * http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Horde_Routes
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class Matcher
{
    /**
     * The routes mapper.
     *
     * @var Mapper
     */
    protected Mapper $mapper;

    /**
     * The incoming request.
     *
     * @var Horde_Controller_Request|ServerRequestInterface
     */
    protected $request;

    /**
     * The match dictionary (cached).
     *
     * @var array|Horde_Support_Array|null
     */
    protected $match_dict;

    /**
     * The typed match result (cached).
     */
    protected ?MatchResult $matchResult = null;

    /**
     * Whether getMatchResult() has been called (to distinguish null result from uncalled).
     */
    private bool $matchResultResolved = false;

    /**
     * Constructor
     *
     * @param Mapper $mapper        The mapper
     * @param Horde_Controller_Request|ServerRequestInterface $request  A request object that implements a ::getPath()
     *                         method similar to Horde_Controller_Request::
     */
    public function __construct(
        Mapper $mapper,
        $request
    ) {
        $this->mapper = $mapper;
        $this->request = $request;
    }

    /**
     * Extract the request path, populating mapper environ as a side effect.
     */
    private function extractPath(): string
    {
        // Auto-populate environ from request
        if (method_exists($this->request, 'getMethod')) {
            $this->mapper->environ = ['REQUEST_METHOD' => $this->request->getMethod()];
        }

        // Extract path from request - handle both PSR-7 and Horde_Controller_Request
        if (method_exists($this->request, 'getPath')) {
            $path = $this->request->getPath();
        } elseif (method_exists($this->request, 'getUri')) {
            $path = $this->request->getUri()->getPath();
        } else {
            throw new RuntimeException('Request must implement getPath() or getUri()');
        }

        // Strip query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }
        if (!$path) {
            $path = '/';
        }
        return $path;
    }

    /**
     * Return the match dictionary for the incoming request.
     *
     * @return array The match dictionary.
     */
    public function getMatchDict()
    {
        if ($this->match_dict === null) {
            $path = $this->extractPath();
            $this->match_dict = new Horde_Support_Array($this->mapper->match($path));
        }
        return $this->match_dict;
    }

    /**
     * Return a typed MatchResult for the incoming request.
     *
     * Uses Mapper::routematch() to preserve the matched Route object,
     * then resolves the route name from the Route or by reverse lookup
     * in the Mapper's named routes.
     *
     * @return MatchResult|null The match result, or null if no route matched.
     */
    public function getMatchResult(): ?MatchResult
    {
        if (!$this->matchResultResolved) {
            $path = $this->extractPath();
            $result = $this->mapper->routematch($path);

            if ($result !== null) {
                [$matchDict, $route] = $result;

                // Resolve route name: prefer Route->routeName (RouteBuilder routes),
                // fall back to reverse lookup in Mapper->routeNames (legacy connect() routes).
                $routeName = $route->routeName;
                if ($routeName === null) {
                    foreach ($this->mapper->routeNames as $name => $namedRoute) {
                        if ($namedRoute === $route) {
                            $routeName = $name;
                            break;
                        }
                    }
                }

                $this->matchResult = new MatchResult($matchDict, $route, $routeName);
            }

            $this->matchResultResolved = true;
        }
        return $this->matchResult;
    }
}
