<?php

/**
 * Horde Routes package
 *
 * This package is heavily inspired by the Python "Routes" library
 * by Ben Bangert (http://routes.groovie.org).  This PHP version
 * includes several enhancements and modifications:
 *  - Fluent RouteBuilder API for strongly-typed route definitions
 *  - Secondary/legacy route support
 *  - Integration with PSR-7/PSR-15 middleware stacks
 *
 * @author  Maintainable Software, LLC. (http://www.maintainable.com)
 * @author  Ralf Lang <ralf.lang@ralf-lang.de>
 * @license http://www.horde.org/licenses/bsd BSD
 * @package Routes
 */

declare(strict_types=1);

namespace Horde\Routes;

use InvalidArgumentException;

/**
 * Fluent route builder for creating routes with IDE-friendly autocomplete
 *
 * RouteBuilder provides a strongly-typed, chainable API for building routes
 * as an alternative to the array-based connect() method. All setter methods
 * return $this for fluent chaining.
 *
 * Basic usage:
 * <code>
 * $builder = new RouteBuilder('users/:id');
 * $builder->name('user_show')
 *         ->controller('User')
 *         ->action('show')
 *         ->requires('id', '\d+')
 *         ->get()
 *         ->middleware(['Auth']);
 *
 * $route = $builder->build();  // Creates Route object
 * </code>
 *
 * Integration with Mapper:
 * <code>
 * $mapper->addRoute($builder);  // Adds RouteBuilder or Route
 * </code>
 *
 * @package Routes
 */
class RouteBuilder
{
    /**
     * Primary route path pattern (used for URL generation)
     */
    private ?string $path = null;

    /**
     * Secondary route paths (match only, not generated)
     *
     * @var array<string>
     */
    private array $secondaryPaths = [];

    /**
     * Optional route name for named routes
     */
    private ?string $name = null;

    /**
     * Default values (controller, action, etc.)
     *
     * @var array<string, mixed>
     */
    private array $defaults = [];

    /**
     * Requirements (regex patterns for route variables)
     *
     * @var array<string, string>
     */
    private array $requirements = [];

    /**
     * Conditions (method, subdomain, function)
     *
     * @var array<string, mixed>
     */
    private array $conditions = [];

    /**
     * Middleware stack
     *
     * @var array<string>
     */
    private array $stack = [];

    /**
     * Flags (_secondary, _absolute, _static, _filter)
     *
     * @var array<string, bool>
     */
    private array $flags = [];

    /**
     * Per-route host for matching and generation
     */
    private ?string $host = null;

    /**
     * Per-route port for matching and generation
     */
    private ?int $port = null;

    /**
     * Per-route scheme for matching and generation
     */
    private ?string $scheme = null;

    /**
     * Per-route path prefix, stripped during match and prepended during generate
     */
    private ?string $pathPrefix = null;

    /**
     * Create a new route builder
     *
     * @param string|null $path Route path pattern (e.g., 'users/:id'), optional if set via withUri()
     */
    public function __construct(?string $path = null)
    {
        $this->path = $path;
    }

    /**
     * Set route URI/path (PSR-style with* method)
     *
     * @param string $uri Route path pattern (e.g., '/users/:id')
     * @return self
     */
    public function withUri(string $uri): self
    {
        $this->path = $uri;
        return $this;
    }

    /**
     * Set route name (PSR-style with* method)
     *
     * Named routes can be referenced by name during URL generation.
     *
     * @param string $name Route name
     * @return self
     */
    public function withName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the route name
     *
     * @return string|null Route name or null if not named
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Add secondary route path (matches but doesn't generate)
     *
     * Secondary paths are alternative URLs that route to the same controller
     * but are not used for URL generation. Useful for legacy URL support.
     *
     * Example:
     * <code>
     * $builder->withSecondaryRoute('/old-url')
     *         ->withSecondaryRoute('/legacy.php');
     * </code>
     *
     * @param string $path Secondary path pattern
     * @return self
     */
    public function withSecondaryRoute(string $path): self
    {
        $this->secondaryPaths[] = $path;
        return $this;
    }

    /**
     * Prefix all secondary route paths (used when loading app routes under a webroot)
     *
     * @param string $prefix Path prefix without trailing slash (e.g. "/horde")
     * @return self
     */
    public function prefixSecondaryPaths(string $prefix): self
    {
        if ($prefix === '') {
            return $this;
        }

        $prefix = rtrim($prefix, '/');
        $this->secondaryPaths = array_map(
            fn (string $path) => $prefix . '/' . ltrim($path, '/'),
            $this->secondaryPaths
        );

        return $this;
    }

    /**
     * Set controller (PSR-style with* method)
     *
     * @param string $controller Controller name or class
     * @return self
     */
    public function withController(string $controller): self
    {
        $this->defaults['controller'] = $controller;
        return $this;
    }

    /**
     * Set action (PSR-style with* method)
     *
     * @param string $action Action name
     * @return self
     */
    public function withAction(string $action): self
    {
        $this->defaults['action'] = $action;
        return $this;
    }

    /**
     * Set arbitrary default value
     *
     * @param string $key Default key
     * @param mixed $value Default value
     * @return self
     */
    public function defaults(string $key, mixed $value): self
    {
        $this->defaults[$key] = $value;
        return $this;
    }

    /**
     * Set multiple defaults at once
     *
     * Merges with existing defaults.
     *
     * @param array<string, mixed> $defaults Associative array of defaults
     * @return self
     */
    public function withDefaults(array $defaults): self
    {
        $this->defaults = array_merge($this->defaults, $defaults);
        return $this;
    }

    /**
     * Add requirement (regex pattern) for route variable
     *
     * @param string $key Variable name
     * @param string $pattern Regex pattern (without delimiters)
     * @return self
     */
    public function requires(string $key, string $pattern): self
    {
        $this->requirements[$key] = $pattern;
        return $this;
    }

    /**
     * Add multiple requirements at once
     *
     * @param array<string, string> $requirements Associative array of variable => pattern
     * @return self
     */
    public function withRequirements(array $requirements): self
    {
        $this->requirements = array_merge($this->requirements, $requirements);
        return $this;
    }

    /**
     * Restrict route to GET requests
     *
     * @return self
     */
    public function get(): self
    {
        $this->conditions['method'] = ['GET'];
        return $this;
    }

    /**
     * Restrict route to POST requests
     *
     * @return self
     */
    public function post(): self
    {
        $this->conditions['method'] = ['POST'];
        return $this;
    }

    /**
     * Restrict route to PUT requests
     *
     * @return self
     */
    public function put(): self
    {
        $this->conditions['method'] = ['PUT'];
        return $this;
    }

    /**
     * Restrict route to DELETE requests
     *
     * @return self
     */
    public function delete(): self
    {
        $this->conditions['method'] = ['DELETE'];
        return $this;
    }

    /**
     * Restrict route to PATCH requests
     *
     * @return self
     */
    public function patch(): self
    {
        $this->conditions['method'] = ['PATCH'];
        return $this;
    }

    /**
     * Restrict route to specific HTTP methods (PSR-style with* method)
     *
     * @param array<string> $methods Array of HTTP method names (e.g., ['GET', 'HEAD'])
     * @return self
     */
    public function withMethods(array $methods): self
    {
        $this->conditions['method'] = $methods;
        return $this;
    }

    /**
     * Restrict route to specific HTTP methods (alias for withMethods)
     *
     * Convenience alias that's more concise than withMethods().
     *
     * @param array<string> $methods Array of HTTP method names (e.g., ['GET', 'HEAD'])
     * @return self
     */
    public function methods(array $methods): self
    {
        return $this->withMethods($methods);
    }

    /**
     * Restrict route to specific subdomain (PSR-style with* method)
     *
     * @param string $subdomain Subdomain name
     * @return self
     */
    public function withSubdomain(string $subdomain): self
    {
        $this->conditions['subDomain'] = $subdomain;
        return $this;
    }

    /**
     * Set per-route host for matching and generation
     *
     * @param string $host Hostname (e.g. "wiki.example.com")
     * @return self
     */
    public function withHost(string $host): self
    {
        $this->host = $host;
        return $this;
    }

    /**
     * Set per-route port for matching and generation
     *
     * @param int $port Port number (e.g. 8080)
     * @return self
     */
    public function withPort(int $port): self
    {
        $this->port = $port;
        return $this;
    }

    /**
     * Set per-route scheme for matching and generation
     *
     * @param string $scheme "http" or "https"
     * @return self
     */
    public function withScheme(string $scheme): self
    {
        $this->scheme = $scheme;
        return $this;
    }

    /**
     * Set per-route path prefix, stripped during match and prepended during generate
     *
     * @param string $prefix Path prefix (e.g. "/wicked")
     * @return self
     */
    public function withPathPrefix(string $prefix): self
    {
        $this->pathPrefix = rtrim($prefix, '/');
        return $this;
    }

    /**
     * Convenience: parse a base URI and set scheme, host, port, and pathPrefix
     *
     * @param string $uri Full base URI (e.g. "https://wiki.example.com:8443/wicked")
     * @return self
     */
    public function withBaseUri(string $uri): self
    {
        $parsed = parse_url($uri);
        if (isset($parsed['scheme'])) {
            $this->scheme = $parsed['scheme'];
        }
        if (isset($parsed['host'])) {
            $this->host = $parsed['host'];
        }
        if (isset($parsed['port'])) {
            $this->port = $parsed['port'];
        }
        if (isset($parsed['path']) && $parsed['path'] !== '/') {
            $this->pathPrefix = rtrim($parsed['path'], '/');
        }
        return $this;
    }

    /**
     * Add custom condition function
     *
     * Function receives environ array and returns bool.
     *
     * @param callable $callable Condition function
     * @return self
     */
    public function where(callable $callable): self
    {
        $this->conditions['function'] = $callable;
        return $this;
    }

    /**
     * Set middleware stack (PSR-style with* method)
     *
     * Middleware is executed in order for PSR-15 applications using
     * the Rampage framework. Not supported in legacy Horde_Controller.
     *
     * @param array<string> $middleware Array of middleware class names
     * @return self
     */
    public function withMiddleware(array $middleware): self
    {
        $this->stack = $middleware;
        return $this;
    }

    /**
     * Explicitly set empty middleware stack
     *
     * Useful for public routes that should skip default middleware.
     *
     * @return self
     */
    public function noMiddleware(): self
    {
        $this->stack = [];
        return $this;
    }

    /**
     * Mark route as absolute
     *
     * @param bool $absolute True to mark as absolute
     * @return self
     */
    public function absolute(bool $absolute = true): self
    {
        if ($absolute) {
            $this->flags['_absolute'] = true;
        } else {
            unset($this->flags['_absolute']);
        }
        return $this;
    }

    /**
     * Mark route as static
     *
     * @param bool $static True to mark as static
     * @return self
     */
    public function static(bool $static = true): self
    {
        if ($static) {
            $this->flags['_static'] = true;
        } else {
            unset($this->flags['_static']);
        }
        return $this;
    }

    /**
     * Mark route as filter
     *
     * @param bool $filter True to mark as filter
     * @return self
     */
    public function filter(bool $filter = true): self
    {
        if ($filter) {
            $this->flags['_filter'] = true;
        } else {
            unset($this->flags['_filter']);
        }
        return $this;
    }

    /**
     * Convert builder configuration to array format
     *
     * Returns array suitable for passing to Mapper->connect().
     *
     * @return array<string, mixed> Route configuration
     */
    public function toArray(): array
    {
        $config = $this->defaults;

        if (!empty($this->requirements)) {
            $config['requirements'] = $this->requirements;
        }

        if (!empty($this->conditions)) {
            $config['conditions'] = $this->conditions;
        }

        // Always include stack, even if empty (explicitly set via noMiddleware())
        $config['stack'] = $this->stack;

        // Merge flags
        $config = array_merge($config, $this->flags);

        // Per-route base URI components
        if ($this->host !== null) {
            $config['_host'] = $this->host;
        }
        if ($this->port !== null) {
            $config['_port'] = $this->port;
        }
        if ($this->scheme !== null) {
            $config['_scheme'] = $this->scheme;
        }
        if ($this->pathPrefix !== null) {
            $config['_pathPrefix'] = $this->pathPrefix;
        }

        return $config;
    }

    /**
     * Build Route object(s) from builder configuration
     *
     * Creates primary Route and optional secondary Routes. If no explicit name
     * is set, generates one from HTTP verbs + path components in CamelCase.
     *
     * Returns single Route if no secondary paths, array of Routes otherwise.
     *
     * @return Route|array<Route> Built route(s)
     * @throws InvalidArgumentException If path is not set
     */
    public function build(): Route|array
    {
        if ($this->path === null) {
            throw new InvalidArgumentException(
                'Route path must be set via constructor or withUri() before building'
            );
        }

        $config = $this->toArray();

        // Generate route name if not explicitly set
        $routeName = $this->name ?? $this->generateRouteName($this->path);

        // Create primary route
        $primaryRoute = new Route($this->path, $config);
        $primaryRoute->routeName = $routeName;

        // No secondary paths? Return single Route
        if (empty($this->secondaryPaths)) {
            return $primaryRoute;
        }

        // Create secondary routes with same config but marked as secondary
        $config['_secondary'] = true;
        $routes = [$primaryRoute];

        foreach ($this->secondaryPaths as $secondaryPath) {
            $secondaryRoute = new Route($secondaryPath, $config);
            // Secondary routes don't get registered by name
            $routes[] = $secondaryRoute;
        }

        return $routes;
    }

    /**
     * Generate route name from HTTP verbs and path components
     *
     * Converts path pattern to CamelCase name, optionally prefixed with HTTP verbs.
     * Controller and middleware are NOT included as they're implementation details.
     *
     * Examples:
     *   - /users/:id → UsersId
     *   - /api/v2/posts/:slug → ApiV2PostsSlug
     *   - /users/:id (GET) → GetUsersId
     *   - /users (POST) → PostUsers
     *
     * @param string $path Route path pattern
     * @return string Generated route name
     */
    private function generateRouteName(string $path): string
    {
        $parts = [];

        // Add HTTP method prefix if specified
        if (!empty($this->conditions['method'])) {
            $methods = $this->conditions['method'];
            if (count($methods) === 1) {
                // Single method: GetUsersId, PostUsers
                $parts[] = ucfirst(strtolower($methods[0]));
            } elseif (count($methods) <= 3) {
                // Few methods: GetPostUsersId
                foreach ($methods as $method) {
                    $parts[] = ucfirst(strtolower($method));
                }
            }
            // Many methods: omit prefix
        }

        // Parse path components
        $pathParts = explode('/', trim($path, '/'));
        foreach ($pathParts as $part) {
            if (empty($part)) {
                continue;
            }

            // Remove parameter markers (:id, :slug, etc.) but keep the name
            $cleaned = str_replace(':', '', $part);

            // Convert to CamelCase
            $camelPart = str_replace(['-', '_', '.'], ' ', $cleaned);
            $camelPart = ucwords($camelPart);
            $camelPart = str_replace(' ', '', $camelPart);

            $parts[] = $camelPart;
        }

        // Handle root path
        if (empty($parts)) {
            return 'Root';
        }

        return implode('', $parts);
    }
}
