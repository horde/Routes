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
use Horde\Routes\RouteBuilder;

/**
 * Tests for PSR-style route builder API
 *
 * @package Routes
 */
class PsrStyleBuilderTest extends TestCase
{
    // ============================================================
    // buildRoute() with Named Parameters Tests
    // ============================================================

    /**
     * Test buildRoute() with both uri and name
     */
    public function testBuildRouteWithBothParameters(): void
    {
        $m = new Mapper();

        $m->buildRoute(uri: '/users/:id', name: 'UserShow')
            ->withController('User')
            ->add();

        $result = $m->match('/users/123');
        $this->assertEquals('User', $result['controller']);
        $this->assertEquals('123', $result['id']);
    }

    /**
     * Test buildRoute() with only uri (name auto-generated)
     */
    public function testBuildRouteWithOnlyUri(): void
    {
        $m = new Mapper();

        $m->buildRoute(uri: '/users/:id')
            ->withController('User')
            ->add();

        $result = $m->match('/users/123');
        $this->assertEquals('User', $result['controller']);

        // Route should be auto-named
        $routes = $m->getRouteList();
        $this->assertCount(1, $routes);
        $this->assertEquals('UsersId', $routes[0]['name']);
    }

    /**
     * Test buildRoute() with only name (uri via withUri)
     */
    public function testBuildRouteWithOnlyName(): void
    {
        $m = new Mapper();

        $m->buildRoute(name: 'UserShow')
            ->withUri('/users/:id')
            ->withController('User')
            ->add();

        $result = $m->match('/users/123');
        $this->assertEquals('User', $result['controller']);

        $routes = $m->getRouteList();
        $this->assertEquals('UserShow', $routes[0]['name']);
    }

    /**
     * Test buildRoute() with no parameters (uri via withUri)
     */
    public function testBuildRouteWithNoParameters(): void
    {
        $m = new Mapper();

        $m->buildRoute()
            ->withUri('/users/:id')
            ->withController('User')
            ->add();

        $result = $m->match('/users/123');
        $this->assertEquals('User', $result['controller']);
    }

    /**
     * Test buildRoute() parameter order doesn't matter
     */
    public function testBuildRouteParameterOrder(): void
    {
        $m = new Mapper();

        // Name first, uri second
        $m->buildRoute(name: 'UserShow', uri: '/users/:id')
            ->withController('User')
            ->add();

        // Uri first, name second
        $m->buildRoute(uri: '/posts/:id', name: 'PostShow')
            ->withController('Post')
            ->add();

        $this->assertNotNull($m->match('/users/123'));
        $this->assertNotNull($m->match('/posts/456'));
    }

    // ============================================================
    // withUri() Method Tests
    // ============================================================

    /**
     * Test withUri() sets URI
     */
    public function testWithUriSetsUri(): void
    {
        $m = new Mapper();

        $m->buildRoute()
            ->withUri('/users/:id')
            ->withController('User')
            ->add();

        $result = $m->match('/users/123');
        $this->assertEquals('User', $result['controller']);
        $this->assertEquals('123', $result['id']);
    }

    /**
     * Test withUri() can override constructor URI
     */
    public function testWithUriOverridesConstructor(): void
    {
        $builder = new RouteBuilder('/old-path');
        $builder->withUri('/new-path')->withController('User');

        $route = $builder->build();
        $this->assertEquals('/new-path', $route->routePath);
    }

    /**
     * Test build() without URI throws exception
     */
    public function testBuildWithoutUriThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('path must be set');

        $builder = new RouteBuilder();
        $builder->withController('User')->build();
    }

    // ============================================================
    // PSR-style with* Method Tests
    // ============================================================

    /**
     * Test withName() sets route name
     */
    public function testWithNameSetsName(): void
    {
        $m = new Mapper();

        $m->buildRoute(uri: '/users/:id')
            ->withName('UserShow')
            ->withController('User')
            ->add();

        $routes = $m->getRouteList();
        $this->assertEquals('UserShow', $routes[0]['name']);
    }

    /**
     * Test withController() sets controller
     */
    public function testWithControllerSetsController(): void
    {
        $m = new Mapper();

        $m->buildRoute(uri: '/users')
            ->withController('UserController')
            ->add();

        $result = $m->match('/users');
        $this->assertEquals('UserController', $result['controller']);
    }

    /**
     * Test withAction() sets action
     */
    public function testWithActionSetsAction(): void
    {
        $m = new Mapper();

        $m->buildRoute(uri: '/users')
            ->withController('User')
            ->withAction('index')
            ->add();

        $result = $m->match('/users');
        $this->assertEquals('index', $result['action']);
    }

    /**
     * Test withMiddleware() sets middleware stack
     */
    public function testWithMiddlewareSetsStack(): void
    {
        $m = new Mapper();

        $m->buildRoute(uri: '/users')
            ->withController('User')
            ->withMiddleware(['Auth', 'RateLimit'])
            ->add();

        $result = $m->match('/users');
        $this->assertEquals(['Auth', 'RateLimit'], $result['stack']);
    }

    /**
     * Test chaining all with* methods
     */
    public function testChainingAllWithMethods(): void
    {
        $m = new Mapper();

        $m->buildRoute(uri: '/users/:id')
            ->withName('UserShow')
            ->withController('UserController')
            ->withAction('show')
            ->withMiddleware(['Auth'])
            ->add();

        $result = $m->match('/users/123');
        $this->assertEquals('UserController', $result['controller']);
        $this->assertEquals('show', $result['action']);
        $this->assertEquals(['Auth'], $result['stack']);
        $this->assertEquals('123', $result['id']);
    }

    /**
     * Test withSubdomain() sets subdomain condition
     */
    public function testWithSubdomainSetsCondition(): void
    {
        $m = new Mapper();

        $m->buildRoute(uri: '/api/users')
            ->withController('ApiUserController')
            ->withAction('index')
            ->withSubdomain('api')
            ->add();

        $route = $m->matchList[0];
        $this->assertEquals('api', $route->conditions['subDomain']);
    }

    /**
     * Test withMethods() sets HTTP method restrictions
     */
    public function testWithMethodsSetsHttpMethods(): void
    {
        $m = new Mapper();

        $m->buildRoute(uri: '/api/data')
            ->withController('Api')
            ->withAction('data')
            ->withMethods(['GET', 'HEAD'])
            ->add();

        $route = $m->matchList[0];
        $this->assertEquals(['GET', 'HEAD'], $route->conditions['method']);

        // Verify it matches GET
        $m->environ = ['REQUEST_METHOD' => 'GET'];
        $result = $m->match('/api/data');
        $this->assertNotNull($result);
        $this->assertEquals('Api', $result['controller']);

        // Verify it matches HEAD
        $m->environ = ['REQUEST_METHOD' => 'HEAD'];
        $result = $m->match('/api/data');
        $this->assertNotNull($result);

        // Verify it doesn't match POST
        $m->environ = ['REQUEST_METHOD' => 'POST'];
        $result = $m->match('/api/data');
        $this->assertNull($result);
    }

    /**
     * Test methods() still works as alias
     */
    public function testMethodsAliasStillWorks(): void
    {
        $m = new Mapper();

        $m->buildRoute(uri: '/api/resource')
            ->withController('Resource')
            ->methods(['PUT', 'PATCH'])  // Using old methods() syntax
            ->add();

        $route = $m->matchList[0];
        $this->assertEquals(['PUT', 'PATCH'], $route->conditions['method']);
    }

    /**
     * Test noMiddleware() sets empty stack
     */
    public function testNoMiddlewareSetsEmptyStack(): void
    {
        $m = new Mapper();

        $m->buildRoute(uri: '/public')
            ->withController('Public')
            ->noMiddleware()
            ->add();

        $result = $m->match('/public');
        $this->assertEquals([], $result['stack']);
    }

    // ============================================================
    // withSecondaryRoute() Tests
    // ============================================================

    /**
     * Test withSecondaryRoute() adds alternative paths
     */
    public function testWithSecondaryRouteAddsAlternativePaths(): void
    {
        $m = new Mapper();

        $m->buildRoute(uri: '/responsive')
            ->withController('ResponsiveController')
            ->withSecondaryRoute('/smartmobile')
            ->withSecondaryRoute('/mobile')
            ->add();

        // All paths should match
        $this->assertNotNull($m->match('/responsive'));
        $this->assertNotNull($m->match('/smartmobile'));
        $this->assertNotNull($m->match('/mobile'));

        // All should have same controller
        $this->assertEquals('ResponsiveController', $m->match('/responsive')['controller']);
        $this->assertEquals('ResponsiveController', $m->match('/smartmobile')['controller']);
        $this->assertEquals('ResponsiveController', $m->match('/mobile')['controller']);
    }

    /**
     * Test only primary route generates URLs
     */
    public function testOnlyPrimaryGenerates(): void
    {
        $m = new Mapper();

        $m->buildRoute(uri: '/responsive')
            ->withController('ResponsiveController')
            ->withSecondaryRoute('/smartmobile')
            ->add();

        $url = $m->generate(['controller' => 'ResponsiveController']);
        $this->assertEquals('/responsive', $url);
        $this->assertNotEquals('/smartmobile', $url);
    }

    /**
     * Test secondary routes inherit all configuration
     */
    public function testSecondaryRoutesInheritConfig(): void
    {
        $m = new Mapper();

        $m->buildRoute(uri: '/api/users/:id')
            ->withController('User')
            ->withAction('show')
            ->withMiddleware(['Auth', 'RateLimit'])
            ->requires('id', '\d+')
            ->get()
            ->withSecondaryRoute('/user/:id')
            ->add();

        $m->environ = ['REQUEST_METHOD' => 'GET'];
        $primary = $m->match('/api/users/123');
        $secondary = $m->match('/user/123');

        $this->assertEquals($primary['controller'], $secondary['controller']);
        $this->assertEquals($primary['action'], $secondary['action']);
        $this->assertEquals($primary['stack'], $secondary['stack']);
        $this->assertEquals($primary['id'], $secondary['id']);
    }

    // ============================================================
    // Mapper::addSecondary() Tests
    // ============================================================

    /**
     * Test addSecondary() adds secondary to existing named route
     */
    public function testAddSecondaryAddsToNamedRoute(): void
    {
        $m = new Mapper();

        $m->buildRoute(uri: '/responsive', name: 'ResponsiveRules')
            ->withController('ResponsiveController')
            ->add();

        $m->addSecondary('/smartmobile', 'ResponsiveRules');

        $this->assertNotNull($m->match('/responsive'));
        $this->assertNotNull($m->match('/smartmobile'));
        $this->assertEquals('ResponsiveController', $m->match('/smartmobile')['controller']);
    }

    /**
     * Test addSecondary() with non-existent route throws
     */
    public function testAddSecondaryWithInvalidRouteThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("named route 'NonExistent' does not exist");

        $m = new Mapper();
        $m->addSecondary('/some-path', 'NonExistent');
    }

    /**
     * Test addSecondary() copies all configuration from primary
     */
    public function testAddSecondaryCopiesConfiguration(): void
    {
        $m = new Mapper();

        $m->buildRoute(uri: '/users/:id', name: 'UserShow')
            ->withController('User')
            ->withAction('show')
            ->withMiddleware(['Auth'])
            ->requires('id', '\d+')
            ->add();

        $m->addSecondary('/profile/:id', 'UserShow');

        $primary = $m->match('/users/123');
        $secondary = $m->match('/profile/123');

        $this->assertEquals($primary['controller'], $secondary['controller']);
        $this->assertEquals($primary['action'], $secondary['action']);
        $this->assertEquals($primary['stack'], $secondary['stack']);
    }

    // ============================================================
    // HTTP Method Tests with PSR-style
    // ============================================================

    /**
     * Test get() with PSR-style methods
     */
    public function testGetMethodWithPsrStyle(): void
    {
        $m = new Mapper();

        $m->buildRoute(uri: '/users')
            ->withController('User')
            ->get()
            ->add();

        $m->environ = ['REQUEST_METHOD' => 'GET'];
        $this->assertNotNull($m->match('/users'));

        $m->environ = ['REQUEST_METHOD' => 'POST'];
        $this->assertNull($m->match('/users'));
    }

    /**
     * Test post() with PSR-style methods
     */
    public function testPostMethodWithPsrStyle(): void
    {
        $m = new Mapper();

        $m->buildRoute(uri: '/users')
            ->withController('User')
            ->post()
            ->add();

        $m->environ = ['REQUEST_METHOD' => 'POST'];
        $this->assertNotNull($m->match('/users'));

        $m->environ = ['REQUEST_METHOD' => 'GET'];
        $this->assertNull($m->match('/users'));
    }

    // ============================================================
    // Integration Tests
    // ============================================================

    /**
     * Test complete route definition with all features
     */
    public function testCompleteRouteDefinition(): void
    {
        $m = new Mapper();

        $m->buildRoute(name: 'UserShow', uri: '/users/:id')
            ->withController('UserController')
            ->withAction('show')
            ->withMiddleware(['Auth', 'Logging'])
            ->requires('id', '\d+')
            ->get()
            ->withSecondaryRoute('/profile/:id')
            ->withSecondaryRoute('/member/:id')
            ->add();

        // Test primary route
        $m->environ = ['REQUEST_METHOD' => 'GET'];
        $primary = $m->match('/users/123');
        $this->assertEquals('UserController', $primary['controller']);
        $this->assertEquals('show', $primary['action']);
        $this->assertEquals(['Auth', 'Logging'], $primary['stack']);
        $this->assertEquals('123', $primary['id']);

        // Test secondary routes
        $secondary1 = $m->match('/profile/123');
        $this->assertEquals($primary['controller'], $secondary1['controller']);

        $secondary2 = $m->match('/member/123');
        $this->assertEquals($primary['controller'], $secondary2['controller']);

        // Test generation uses primary only
        $url = $m->generate([
            'controller' => 'UserController',
            'action' => 'show',
            'id' => '123'
        ]);
        $this->assertEquals('/users/123', $url);
    }

    /**
     * Test backward compatibility with old route() method
     */
    public function testBackwardCompatibilityWithRouteMethod(): void
    {
        $m = new Mapper();

        // Old route() method should still work
        $m->route('/old-style')
            ->withController('OldController')
            ->add();

        // New buildRoute() method
        $m->buildRoute(uri: '/new-style')
            ->withController('NewController')
            ->add();

        $this->assertEquals('OldController', $m->match('/old-style')['controller']);
        $this->assertEquals('NewController', $m->match('/new-style')['controller']);
    }
}
