<?php
/**
 * Horde Routes package
 *
 * @author  Ralf Lang <lang@b1-systems.de>
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
 */
class SecondaryRouteIntegrationTest extends TestCase
{
    /**
     * Test realistic scenario: multiple legacy URLs redirect to canonical
     */
    public function testLegacyUrlMigration(): void
    {
        $m = new Mapper();

        // Modern canonical URLs
        $m->connect('api_user_show', 'api/v2/users/:id', [
            'controller' => 'Api\UserController',
            'action' => 'show',
            'requirements' => ['id' => '\d+']
        ]);

        // Legacy URLs from v1 API
        $m->connectSecondary('api/v1/user/:id', [
            'controller' => 'Api\UserController',
            'action' => 'show',
            'requirements' => ['id' => '\d+']
        ]);

        // Even older legacy URL
        $m->connectSecondary('user.php', [
            'controller' => 'Api\UserController',
            'action' => 'show'
        ]);

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
            'id' => '123'
        ]);
        $this->assertEquals('/api/v2/users/123', $canonical);
    }

    /**
     * Test multiple alternative URLs for single endpoint
     */
    public function testMultipleAlternativesOneController(): void
    {
        $m = new Mapper();

        // Primary route
        $m->connect('dashboard', 'dashboard', [
            'controller' => 'Dashboard',
            'action' => 'index'
        ]);

        // Alternative URLs (marketing campaigns, shortcuts, etc.)
        $m->connectSecondary('home', [
            'controller' => 'Dashboard',
            'action' => 'index'
        ]);
        $m->connectSecondary('index', [
            'controller' => 'Dashboard',
            'action' => 'index'
        ]);
        $m->connectSecondary('start', [
            'controller' => 'Dashboard',
            'action' => 'index'
        ]);
        $m->connectSecondary('welcome', [
            'controller' => 'Dashboard',
            'action' => 'index'
        ]);
        $m->connectSecondary('portal', [
            'controller' => 'Dashboard',
            'action' => 'index'
        ]);

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

        // Modern API endpoint with auth middleware
        $m->connect('api/secure/data', [
            'controller' => 'Api\SecureController',
            'action' => 'getData',
            'stack' => ['ApiAuth', 'RateLimit']
        ]);

        // Legacy endpoint (also requires same auth)
        $m->connectSecondary('legacy/secure.php', [
            'controller' => 'Api\SecureController',
            'action' => 'getData',
            'stack' => ['ApiAuth', 'RateLimit']
        ]);

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
            'action' => 'getData'
        ]);
        $this->assertEquals('/api/secure/data', $url);
    }

    /**
     * Test route listing output format
     */
    public function testRouteListingFormat(): void
    {
        $m = new Mapper();

        $m->connect('api_list', 'api/items', [
            'controller' => 'Item',
            'action' => 'list',
            'conditions' => ['method' => 'GET']
        ]);

        $m->connectSecondary('items', [
            'controller' => 'Item',
            'action' => 'list',
            'conditions' => ['method' => 'GET']
        ]);

        $m->connectSecondary('legacy_items_list', 'list.php', [
            'controller' => 'Item',
            'action' => 'list'
        ]);

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
        $m->connect('users', [
            'controller' => 'User',
            'action' => 'index',
            'conditions' => ['method' => ['GET']]  // Must be array
        ]);
        $m->connect('users', [
            'controller' => 'User',
            'action' => 'create',
            'conditions' => ['method' => ['POST']]  // Must be array
        ]);
        $m->connect('users/:id', [
            'controller' => 'User',
            'action' => 'show',
            'conditions' => ['method' => ['GET']]  // Must be array
        ]);

        // Legacy routes (different URL patterns)
        $m->connectSecondary('user/list', [
            'controller' => 'User',
            'action' => 'index'
        ]);
        $m->connectSecondary('user/:id/view', [
            'controller' => 'User',
            'action' => 'show'
        ]);

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
