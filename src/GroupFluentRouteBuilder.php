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
 * Fluent wrapper for RouteBuilder that enables ->add() chaining with GroupMapper.
 *
 * @package Routes
 */
class GroupFluentRouteBuilder
{
    private GroupMapper $mapper;
    private RouteBuilder $builder;

    public function __construct(GroupMapper $mapper, RouteBuilder $builder)
    {
        $this->mapper = $mapper;
        $this->builder = $builder;
    }

    public function getBuilder(): RouteBuilder
    {
        return $this->builder;
    }

    /**
     * Build and add the route to the mapper, returning the mapper for chaining.
     */
    public function add(): GroupMapper
    {
        $this->mapper->addRoute($this->builder);
        return $this->mapper;
    }

    /**
     * Proxy all method calls to the underlying RouteBuilder.
     *
     * @return self|mixed
     */
    public function __call(string $method, array $args): mixed
    {
        $result = $this->builder->$method(...$args);
        if ($result === $this->builder) {
            return $this;
        }
        return $result;
    }
}
