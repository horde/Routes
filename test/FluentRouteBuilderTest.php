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
use Horde\Routes\FluentRouteBuilder;
use Horde\Routes\RouteBuilder;

/**
 * Tests for FluentRouteBuilder wrapper
 *
 * FluentRouteBuilder wraps RouteBuilder and adds the ->add() method
 * that returns the Mapper for chaining. This enables the fluent API:
 *   $mapper->route('path')->controller('Foo')->add()
 *
 * @package Routes
 */
class FluentRouteBuilderTest extends TestCase
{
    /**
     * Test FluentRouteBuilder constructor
     */
    public function testConstructor(): void
    {
        $m = new Mapper();
        $fluent = new FluentRouteBuilder($m, 'users/:id');

        $this->assertInstanceOf(FluentRouteBuilder::class, $fluent);

        // Should have access to underlying builder
        $builder = $fluent->getBuilder();
        $this->assertInstanceOf(RouteBuilder::class, $builder);
    }

    /**
     * Test methods proxy to underlying RouteBuilder
     */
    public function testFluentProxying(): void
    {
        $m = new Mapper();
        $fluent = new FluentRouteBuilder($m, 'users/:id');

        // All RouteBuilder methods should be available
        $result = $fluent->controller('User')
                         ->action('show')
                         ->requires('id', '\d+')
                         ->get()
                         ->middleware(['Auth']);

        // Should return FluentRouteBuilder (self) for chaining
        $this->assertInstanceOf(FluentRouteBuilder::class, $result);
        $this->assertSame($fluent, $result);

        // Underlying builder should have the configuration
        $builder = $fluent->getBuilder();
        $config = $builder->toArray();

        $this->assertEquals('User', $config['controller']);
        $this->assertEquals('show', $config['action']);
        $this->assertEquals('\d+', $config['requirements']['id']);
        $this->assertEquals(['GET'], $config['conditions']['method']);
        $this->assertEquals(['Auth'], $config['stack']);
    }

    /**
     * Test add() method returns Mapper for chaining
     */
    public function testAddMethodReturnsMapper(): void
    {
        $m = new Mapper();
        $fluent = new FluentRouteBuilder($m, 'users/:id');

        $result = $fluent->controller('User')
                         ->action('show')
                         ->add();

        // Should return the original Mapper
        $this->assertSame($m, $result);

        // Route should be added to Mapper
        $this->assertCount(1, $m->matchList);
        $this->assertEquals('users/:id', $m->matchList[0]->routePath);
    }

    /**
     * Test complex chain: multiple routes
     */
    public function testComplexChain(): void
    {
        $m = new Mapper();

        // Chain multiple routes
        $m->route('users')
          ->controller('User')
          ->action('index')
          ->get()
          ->add()
          ->route('users')
          ->controller('User')
          ->action('create')
          ->post()
          ->add()
          ->route('users/:id')
          ->controller('User')
          ->action('show')
          ->requires('id', '\d+')
          ->get()
          ->add()
          ->route('users/:id')
          ->controller('User')
          ->action('update')
          ->requires('id', '\d+')
          ->put()
          ->add()
          ->route('users/:id')
          ->controller('User')
          ->action('delete')
          ->requires('id', '\d+')
          ->delete()
          ->add();

        // Should have all 5 routes
        $this->assertCount(5, $m->matchList);

        // Verify first route
        $this->assertEquals('users', $m->matchList[0]->routePath);
        $this->assertEquals('index', $m->matchList[0]->defaults['action']);

        // Verify last route
        $this->assertEquals('users/:id', $m->matchList[4]->routePath);
        $this->assertEquals('delete', $m->matchList[4]->defaults['action']);
    }

    /**
     * Test named route with fluent API
     */
    public function testNamedRouteWithFluent(): void
    {
        $m = new Mapper();

        $m->route('users/:id')
          ->name('user_show')
          ->controller('User')
          ->action('show')
          ->add();

        // Named route should be registered
        $this->assertArrayHasKey('user_show', $m->routeNames);
    }

    /**
     * Test secondary route with fluent API
     */
    public function testSecondaryRouteWithFluent(): void
    {
        $m = new Mapper();

        $m->route('users/:id')
          ->controller('User')
          ->action('show')
          ->add()
          ->route('profile/:id')
          ->controller('User')
          ->action('show')
          ->secondary()
          ->add();

        $this->assertCount(2, $m->matchList);
        $this->assertFalse($m->matchList[0]->secondary);
        $this->assertTrue($m->matchList[1]->secondary);
    }

    /**
     * Test method proxying edge cases
     */
    public function testMethodProxyingEdgeCases(): void
    {
        $m = new Mapper();
        $fluent = new FluentRouteBuilder($m, 'test/:id');

        // Test withDefaults (array parameter)
        $fluent->withDefaults([
            'controller' => 'Test',
            'action' => 'show'
        ]);

        $builder = $fluent->getBuilder();
        $config = $builder->toArray();
        $this->assertEquals('Test', $config['controller']);
        $this->assertEquals('show', $config['action']);

        // Test withRequirements (array parameter)
        $fluent->withRequirements([
            'id' => '\d+',
            'format' => 'json|xml'
        ]);

        $config = $builder->toArray();
        $this->assertEquals('\d+', $config['requirements']['id']);
        $this->assertEquals('json|xml', $config['requirements']['format']);

        // Test methods (array parameter)
        $fluent->methods(['GET', 'HEAD']);

        $config = $builder->toArray();
        $this->assertEquals(['GET', 'HEAD'], $config['conditions']['method']);
    }

    /**
     * Test that undefined methods throw error
     */
    public function testUndefinedMethodThrows(): void
    {
        $m = new Mapper();
        $fluent = new FluentRouteBuilder($m, 'test');

        $this->expectException(\Error::class);
        $fluent->nonExistentMethod();
    }

    /**
     * Test real-world usage pattern
     */
    public function testRealWorldUsagePattern(): void
    {
        $m = new Mapper();

        // Define an API with multiple endpoints
        $m->route('api/v1/users')
          ->name('api_users_list')
          ->controller('Api\\V1\\User')
          ->action('index')
          ->middleware(['ApiAuth', 'RateLimit'])
          ->get()
          ->add()

          ->route('api/v1/users/:id')
          ->name('api_users_show')
          ->controller('Api\\V1\\User')
          ->action('show')
          ->requires('id', '\d+')
          ->middleware(['ApiAuth', 'RateLimit'])
          ->methods(['GET', 'HEAD'])
          ->add()

          ->route('api/v1/users')
          ->name('api_users_create')
          ->controller('Api\\V1\\User')
          ->action('create')
          ->middleware(['ApiAuth', 'RateLimit', 'ValidateJson'])
          ->post()
          ->add();

        // Verify all routes added
        $this->assertCount(3, $m->matchList);

        // Verify all are named
        $this->assertArrayHasKey('api_users_list', $m->routeNames);
        $this->assertArrayHasKey('api_users_show', $m->routeNames);
        $this->assertArrayHasKey('api_users_create', $m->routeNames);

        // Verify middleware on first route
        $route = $m->routeNames['api_users_list'];
        $this->assertEquals(['ApiAuth', 'RateLimit'], $route->stack);
    }
}
