<?php

/**
 * Horde Routes package
 *
 * @author  Ralf Lang <ralf.lang@ralf-lang.de>
 * @license http://www.horde.org/licenses/bsd BSD
 * @package Routes
 */

namespace Horde\Routes\Test;

use PHPUnit\Framework\TestCase;
use Horde\Routes\Mapper;

/**
 * Integration tests for secondary/legacy route feature
 *
 * @package Routes
 * @group integration
 * @coversNothing
 */
class SecondaryRouteIntegrationTest extends TestCase
{
    /**
     * Test realistic scenario: multiple legacy URLs redirect to canonical
     */
    public function testLegacyUrlMigration(): void
    {
        $m = new Mapper();

        // Modern canonical URL with legacy alternatives
        $m->buildRoute(uri: 'api/v2/users/:id', name: 'api_user_show')
            ->withController('Api\UserController')
            ->withAction('show')
            ->requires('id', '\d+')
            ->withSecondaryRoute('api/v1/user/:id')
            ->withSecondaryRoute('user.php')
            ->add();

        // Test all URLs match
        $result1 = $m->match('/api/v2/users/123');
        $this->assertIsArray($result1);
        $this->assertEquals('Api\UserController', $result1['controller']);

        $result2 = $m->match('/api/v1/user/123');
        $this->assertIsArray($result2);
        $this->assertEquals('Api\UserController', $result2['controller']);

        $result3 = $m->match('/user.php');
        $this->assertIsArray($result3);
        $this->assertEquals('Api\UserController', $result3['controller']);

        // Generation always uses canonical URL
        $canonical = $m->generate([
            'controller' => 'Api\UserController',
            'action' => 'show',
            'id' => '123',
        ]);
        $this->assertEquals('/api/v2/users/123', $canonical);
    }

    /**
     * Test multiple alternative URLs for single endpoint
     */
    public function testMultipleAlternativesOneController(): void
    {
        $m = new Mapper();

        // Primary route with multiple alternative URLs
        $m->buildRoute(uri: 'dashboard', name: 'dashboard')
            ->withController('Dashboard')
            ->withAction('index')
            ->withSecondaryRoute('home')
            ->withSecondaryRoute('index')
            ->withSecondaryRoute('start')
            ->withSecondaryRoute('welcome')
            ->withSecondaryRoute('portal')
            ->add();

        // All URLs should match
        $paths = ['/dashboard', '/home', '/index', '/start', '/welcome', '/portal'];
        foreach ($paths as $path) {
            $result = $m->match($path);
            $this->assertIsArray($result, "Failed to match: $path");
            $this->assertEquals('Dashboard', $result['controller']);
            $this->assertEquals('index', $result['action']);
        }

        // But only canonical generates
        $url = $m->generate(['controller' => 'Dashboard', 'action' => 'index']);
        $this->assertEquals('/dashboard', $url);
    }

    /**
     * Test secondary routes with middleware stacks (PSR-15 pattern)
     */
    public function testSecondaryWithMiddlewareStack(): void
    {
        $m = new Mapper();

        // Modern API endpoint with auth middleware and legacy endpoint
        $m->buildRoute(uri: 'api/secure/data')
            ->withController('Api\SecureController')
            ->withAction('getData')
            ->withMiddleware(['ApiAuth', 'RateLimit'])
            ->withSecondaryRoute('legacy/secure.php')
            ->add();

        // Both should have middleware stack
        $result1 = $m->match('/api/secure/data');
        $this->assertIsArray($result1);
        $this->assertArrayHasKey('stack', $result1);
        $this->assertEquals(['ApiAuth', 'RateLimit'], $result1['stack']);

        $result2 = $m->match('/legacy/secure.php');
        $this->assertIsArray($result2);
        $this->assertArrayHasKey('stack', $result2);
        $this->assertEquals(['ApiAuth', 'RateLimit'], $result2['stack']);

        // Generation uses primary
        $url = $m->generate([
            'controller' => 'Api\SecureController',
            'action' => 'getData',
        ]);
        $this->assertEquals('/api/secure/data', $url);
    }

    /**
     * Test route listing output format
     */
    public function testRouteListingFormat(): void
    {
        $m = new Mapper();

        $m->buildRoute(uri: 'api/items', name: 'api_list')
            ->withController('Item')
            ->withAction('list')
            ->withMethods(['GET'])
            ->withSecondaryRoute('items')
            ->add();

        $m->buildRoute(uri: 'list.php', name: 'legacy_items_list')
            ->withController('Item')
            ->withAction('list')
            ->withDefaults(['_secondary' => true])
            ->add();

        $routes = $m->getRouteList();

        // Should have 3 routes
        $this->assertCount(3, $routes);

        // Check primary route
        $this->assertEquals('primary', $routes[0]['type']);
        $this->assertEquals('api/items', $routes[0]['path']);
        $this->assertEquals('api_list', $routes[0]['name']);
        $this->assertIsString($routes[0]['static']);  // Route->static is typed as string, not bool
        $this->assertEquals('Item', $routes[0]['defaults']['controller']);

        // Check first secondary
        $this->assertEquals('secondary', $routes[1]['type']);
        $this->assertEquals('items', $routes[1]['path']);
        $this->assertNull($routes[1]['name']);

        // Check named secondary
        $this->assertEquals('secondary', $routes[2]['type']);
        $this->assertEquals('list.php', $routes[2]['path']);
        $this->assertEquals('legacy_items_list', $routes[2]['name']);
    }

    /**
     * Test RESTful resource routes with secondary routes
     */
    public function testRESTfulWithSecondary(): void
    {
        $m = new Mapper();

        // Modern RESTful routes
        $m->buildRoute(uri: 'users')
            ->withController('User')
            ->withAction('index')
            ->withMethods(['GET'])
            ->withSecondaryRoute('user/list')
            ->add();

        $m->buildRoute(uri: 'users')
            ->withController('User')
            ->withAction('create')
            ->withMethods(['POST'])
            ->add();

        $m->buildRoute(uri: 'users/:id')
            ->withController('User')
            ->withAction('show')
            ->withMethods(['GET'])
            ->withSecondaryRoute('user/:id/view')
            ->add();

        // Modern GET /users should match
        $m->environ = ['REQUEST_METHOD' => 'GET'];
        $result1 = $m->match('/users');
        $this->assertIsArray($result1);
        $this->assertEquals('index', $result1['action']);

        // Legacy GET /user/list should match
        $result2 = $m->match('/user/list');
        $this->assertIsArray($result2);
        $this->assertEquals('index', $result2['action']);

        // Modern GET /users/123 should match
        $result3 = $m->match('/users/123');
        $this->assertIsArray($result3);
        $this->assertEquals('show', $result3['action']);
        $this->assertEquals('123', $result3['id']);

        // Legacy GET /user/123/view should match
        $result4 = $m->match('/user/123/view');
        $this->assertIsArray($result4);
        $this->assertEquals('show', $result4['action']);
        $this->assertEquals('123', $result4['id']);

        // Generation uses primary routes
        $url1 = $m->generate(['controller' => 'User', 'action' => 'index']);
        $this->assertEquals('/users', $url1);

        $url2 = $m->generate(['controller' => 'User', 'action' => 'show', 'id' => '123']);
        $this->assertEquals('/users/123', $url2);
    }
}
