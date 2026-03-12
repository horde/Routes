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
 * @author  Ralf Lang <lang@b1-systems.de>
 * @license http://www.horde.org/licenses/bsd BSD
 * @package Routes
 */

declare(strict_types=1);

namespace Horde\Routes;

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
     * Route path pattern
     */
    private string $path;

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
     * Create a new route builder
     *
     * @param string $path Route path pattern (e.g., 'users/:id')
     */
    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * Set route name for named routes
     *
     * Named routes can be referenced by name during URL generation.
     *
     * @param string $name Route name
     * @return self
     */
    public function name(string $name): self
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
     * Set controller default
     *
     * @param string $controller Controller name or class
     * @return self
     */
    public function controller(string $controller): self
    {
        $this->defaults['controller'] = $controller;
        return $this;
    }

    /**
     * Set action default
     *
     * @param string $action Action name
     * @return self
     */
    public function action(string $action): self
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
     * Restrict route to specific HTTP methods
     *
     * @param array<string> $methods Array of HTTP method names (e.g., ['GET', 'HEAD'])
     * @return self
     */
    public function methods(array $methods): self
    {
        $this->conditions['method'] = $methods;
        return $this;
    }

    /**
     * Restrict route to specific subdomain
     *
     * @param string $subdomain Subdomain name
     * @return self
     */
    public function subdomain(string $subdomain): self
    {
        $this->conditions['subdomain'] = $subdomain;
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
     * Set middleware stack for this route
     *
     * Middleware is executed in order for PSR-15 applications using
     * the Rampage framework. Not supported in legacy Horde_Controller.
     *
     * @param array<string> $middleware Array of middleware class names
     * @return self
     */
    public function middleware(array $middleware): self
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
     * Mark route as secondary/legacy (matches but doesn't generate)
     *
     * Secondary routes are useful for supporting alternative URLs (e.g., legacy
     * URLs during migration) without affecting URL generation. They participate
     * in matching but are excluded from the generation dictionary.
     *
     * Designed for modern PSR-7/PSR-15 applications using the Rampage middleware
     * framework. Legacy Horde_Controller applications may have limited support.
     *
     * @param bool $secondary True to mark as secondary, false to unmark
     * @return self
     */
    public function secondary(bool $secondary = true): self
    {
        if ($secondary) {
            $this->flags['_secondary'] = true;
        } else {
            unset($this->flags['_secondary']);
        }
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

        return $config;
    }

    /**
     * Build Route object from builder configuration
     *
     * Creates a Route object from the builder's configuration. Note that the
     * route name is not passed to the Route constructor - it's stored separately
     * and registered by Mapper when the route is added.
     *
     * @return Route Built route object
     */
    public function build(): Route
    {
        $config = $this->toArray();
        $route = new Route($this->path, $config);

        // Store the route name on the Route object for Mapper to register
        if ($this->name !== null) {
            $route->routeName = $this->name;
        }

        return $route;
    }
}
