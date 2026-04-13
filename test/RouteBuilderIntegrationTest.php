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
use Horde\Routes\RouteBuilder;
use Horde\Routes\Route;

/**
 * Integration tests for RouteBuilder with Mapper
 *
 * Tests the interaction between RouteBuilder and Mapper to ensure
 * routes built with the fluent API work correctly for matching and generation.
 *
 * @package Routes
 * @group integration
 * @coversNothing
 */
class RouteBuilderIntegrationTest extends TestCase
{
    /**
     * Test Mapper->addRoute() accepts RouteBuilder
     */
    public function testMapperAddRouteMethod(): void
    {
        $m = new Mapper();

        // Create builder
        $builder = new RouteBuilder('api/users/:id');
        $builder->withController('User')
                ->withAction('show')
                ->requires('id', '\d+')
                ->get();

        // Add to mapper
        $m->addRoute($builder);

        // Should be in matchList
        $this->assertCount(1, $m->matchList);

        // Should be a Route object
        $this->assertInstanceOf(Route::class, $m->matchList[0]);

        // Should have correct path
        $this->assertEquals('api/users/:id', $m->matchList[0]->routePath);
    }

    /**
     * Test Mapper->route()->add() fluent API
     */
    public function testMapperRouteHelperMethod(): void
    {
        $m = new Mapper();

        // Use fluent API
        $result = $m->route('users/:id')
                    ->withController('User')
                    ->withAction('show')
                    ->requires('id', '\d+')
                    ->get()
                    ->add();

        // Should return Mapper for chaining
        $this->assertSame($m, $result);

        // Route should be added
        $this->assertCount(1, $m->matchList);

        // Can chain multiple routes
        $m->route('users')
          ->withController('User')
          ->withAction('index')
          ->get()
          ->add()
          ->route('users')
          ->withController('User')
          ->withAction('create')
          ->post()
          ->add();

        // Should have 3 routes total
        $this->assertCount(3, $m->matchList);
    }

    /**
     * Test route built with builder matches URLs
     */
    public function testBuiltRouteMatches(): void
    {
        $m = new Mapper();

        // Build route with fluent API
        $m->route('api/users/:id')
          ->withController('User')
          ->withAction('show')
          ->requires('id', '\d+')
          ->withMiddleware(['Auth', 'JsonResponse'])
          ->get()
          ->add();

        // Set environ for GET request
        $m->environ = ['REQUEST_METHOD' => 'GET'];

        // Should match valid URL
        $result = $m->match('/api/users/123');
        $this->assertIsArray($result);
        $this->assertEquals('User', $result['controller']);
        $this->assertEquals('show', $result['action']);
        $this->assertEquals('123', $result['id']);
        $this->assertEquals(['Auth', 'JsonResponse'], $result['stack']);

        // Should not match invalid ID
        $this->assertNull($m->match('/api/users/abc'));

        // Should not match POST request
        $m->environ = ['REQUEST_METHOD' => 'POST'];
        $this->assertNull($m->match('/api/users/123'));
    }

    /**
     * Test route built with builder generates URLs
     */
    public function testBuiltRouteGenerates(): void
    {
        $m = new Mapper();

        // Add route with name
        $m->route('users/:id/profile')
          ->withName('user_profile')
          ->withController('User')
          ->withAction('profile')
          ->requires('id', '\d+')
          ->add();

        // Should generate URL
        $url = $m->generate([
            'controller' => 'User',
            'action' => 'profile',
            'id' => '42',
        ]);

        $this->assertEquals('/users/42/profile', $url);
    }

    /**
     * Test mixed array-based and builder-based routes
     */
    public function testMixedApiRoutes(): void
    {
        $m = new Mapper();

        // Add array-based route
        $m->connect('old/path/:id', [
            'controller' => 'Old',
            'action' => 'show',
        ]);

        // Add builder-based route
        $m->route('new/path/:id')
          ->withController('New')
          ->withAction('show')
          ->add();

        // Both should work
        $this->assertCount(2, $m->matchList);

        $result1 = $m->match('/old/path/123');
        $this->assertEquals('Old', $result1['controller']);

        $result2 = $m->match('/new/path/456');
        $this->assertEquals('New', $result2['controller']);

        // Both should generate
        $url1 = $m->generate(['controller' => 'Old', 'action' => 'show', 'id' => '1']);
        $this->assertEquals('/old/path/1', $url1);

        $url2 = $m->generate(['controller' => 'New', 'action' => 'show', 'id' => '2']);
        $this->assertEquals('/new/path/2', $url2);
    }

    /**
     * Test builder with withSecondaryRoute()
     */
    public function testBuilderWithSecondaryRoute(): void
    {
        $m = new Mapper();

        // Primary route with secondary paths
        $m->route('users/:id')
          ->withController('User')
          ->withAction('show')
          ->withSecondaryRoute('/profile/:id')
          ->add();

        // Both should match
        $this->assertNotNull($m->match('/users/123'));
        $this->assertNotNull($m->match('/profile/123'));

        // But only primary generates
        $url = $m->generate(['controller' => 'User', 'action' => 'show', 'id' => '42']);
        $this->assertEquals('/users/42', $url);
    }

    /**
     * Test builder with named route
     */
    public function testBuilderWithNamedRoute(): void
    {
        $m = new Mapper();

        $m->route('api/v2/users/:id')
          ->withName('api_user_show')
          ->withController('Api\\User')
          ->withAction('show')
          ->requires('id', '\d+')
          ->add();

        // Named route should be in routeNames
        $this->assertArrayHasKey('api_user_show', $m->routeNames);
        $this->assertInstanceOf(Route::class, $m->routeNames['api_user_show']);

        // Should be able to match
        $result = $m->match('/api/v2/users/99');
        $this->assertEquals('Api\\User', $result['controller']);
    }

    /**
     * Test builder with noMiddleware()
     */
    public function testBuilderWithNoMiddleware(): void
    {
        $m = new Mapper();

        // Public route with no middleware
        $m->route('public/health')
          ->withController('Public')
          ->withAction('health')
          ->noMiddleware()
          ->add();

        $result = $m->match('/public/health');

        // Should have empty stack
        $this->assertArrayHasKey('stack', $result);
        $this->assertIsArray($result['stack']);
        $this->assertEmpty($result['stack']);
    }

    /**
     * Test complex RESTful routes with builder
     */
    public function testRESTfulRoutesWithBuilder(): void
    {
        $m = new Mapper();

        // Define RESTful resource
        $m->route('posts')
          ->withController('Post')
          ->withAction('index')
          ->get()
          ->add()
          ->route('posts')
          ->withController('Post')
          ->withAction('create')
          ->post()
          ->add()
          ->route('posts/:id')
          ->withController('Post')
          ->withAction('show')
          ->requires('id', '\d+')
          ->get()
          ->add()
          ->route('posts/:id')
          ->withController('Post')
          ->withAction('update')
          ->requires('id', '\d+')
          ->put()
          ->add()
          ->route('posts/:id')
          ->withController('Post')
          ->withAction('delete')
          ->requires('id', '\d+')
          ->delete()
          ->add();

        $this->assertCount(5, $m->matchList);

        // Test GET collection
        $m->environ = ['REQUEST_METHOD' => 'GET'];
        $result1 = $m->match('/posts');
        $this->assertEquals('index', $result1['action']);

        // Test GET member
        $result2 = $m->match('/posts/42');
        $this->assertEquals('show', $result2['action']);
        $this->assertEquals('42', $result2['id']);

        // Test POST
        $m->environ = ['REQUEST_METHOD' => 'POST'];
        $result3 = $m->match('/posts');
        $this->assertEquals('create', $result3['action']);

        // Test PUT
        $m->environ = ['REQUEST_METHOD' => 'PUT'];
        $result4 = $m->match('/posts/42');
        $this->assertEquals('update', $result4['action']);

        // Test DELETE
        $m->environ = ['REQUEST_METHOD' => 'DELETE'];
        $result5 = $m->match('/posts/42');
        $this->assertEquals('delete', $result5['action']);
    }
}
