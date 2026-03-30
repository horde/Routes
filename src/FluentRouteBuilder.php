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

/**
 * Fluent wrapper for RouteBuilder that enables ->add() chaining
 *
 * FluentRouteBuilder wraps a RouteBuilder instance and provides method
 * proxying via __call() to forward all RouteBuilder methods. It adds a
 * single method ->add() that builds the route, adds it to the Mapper,
 * and returns the Mapper for further chaining.
 *
 * This enables clean fluent API:
 * <code>
 * $mapper->route('users/:id')
 *        ->controller('User')
 *        ->action('show')
 *        ->add()
 *        ->route('users')
 *        ->controller('User')
 *        ->action('index')
 *        ->add();
 * </code>
 *
 * @package Routes
 */
class FluentRouteBuilder
{
    /**
     * The underlying RouteBuilder instance
     */
    private RouteBuilder $builder;

    /**
     * The Mapper instance to add routes to
     */
    private Mapper $mapper;

    /**
     * Create a new fluent route builder
     *
     * @param Mapper $mapper Mapper instance
     * @param string|null $path Route path pattern (optional if set via withUri)
     */
    public function __construct(Mapper $mapper, ?string $path = null)
    {
        $this->mapper = $mapper;
        $this->builder = new RouteBuilder($path);
    }

    /**
     * Get the underlying RouteBuilder instance
     *
     * @return RouteBuilder The builder instance
     */
    public function getBuilder(): RouteBuilder
    {
        return $this->builder;
    }

    /**
     * Build and add the route to the mapper, returning the mapper
     *
     * This method completes the fluent chain by building the route,
     * adding it to the mapper, and returning the mapper for further
     * route definitions.
     *
     * @return Mapper The mapper instance for chaining
     */
    public function add(): Mapper
    {
        $this->mapper->addRoute($this->builder);
        return $this->mapper;
    }

    /**
     * Proxy all other method calls to the underlying RouteBuilder
     *
     * All RouteBuilder methods (name, controller, action, requires, withSecondaryRoute, etc.)
     * are forwarded to the builder. The builder returns itself, which is
     * then wrapped back into this FluentRouteBuilder for continued chaining.
     *
     * @param string $method Method name
     * @param array<mixed> $args Method arguments
     * @return self This fluent builder for chaining
     * @throws \Error If method doesn't exist on RouteBuilder
     */
    public function __call(string $method, array $args): self
    {
        $result = $this->builder->$method(...$args);

        // If RouteBuilder method returned $this (fluent chaining),
        // return this FluentRouteBuilder instead
        if ($result === $this->builder) {
            return $this;
        }

        // Otherwise return the actual result (shouldn't happen for setter methods)
        return $result;
    }
}
