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
use Horde\Routes\RouteBuilder;
use Horde\Routes\Route;

/**
 * Unit tests for RouteBuilder fluent API
 *
 * These tests document the expected behavior of the RouteBuilder class.
 * They should fail until RouteBuilder is implemented.
 *
 * @package Routes
 */
class RouteBuilderTest extends TestCase
{
    // ============================================================
    // Basic Builder Tests
    // ============================================================

    /**
     * Test RouteBuilder constructor accepts path
     */
    public function testConstructorWithPath(): void
    {
        $builder = new RouteBuilder('users/:id');

        $this->assertInstanceOf(RouteBuilder::class, $builder);

        // Should be able to build a route
        $route = $builder->build();
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('users/:id', $route->routePath);
    }

    /**
     * Test withName() method sets route name
     */
    public function testWithNameMethod(): void
    {
        $builder = new RouteBuilder('users/:id');
        $result = $builder->withName('user_show');

        // Should return self for chaining
        $this->assertSame($builder, $result);

        // Name should be retrievable
        $this->assertEquals('user_show', $builder->getName());
    }

    /**
     * Test withController() method sets controller default
     */
    public function testWithControllerMethod(): void
    {
        $builder = new RouteBuilder('users/:id');
        $result = $builder->withController('User');

        $this->assertSame($builder, $result);

        $config = $builder->toArray();
        $this->assertEquals('User', $config['controller']);
    }

    /**
     * Test withAction() method sets action default
     */
    public function testWithActionMethod(): void
    {
        $builder = new RouteBuilder('users/:id');
        $result = $builder->withAction('show');

        $this->assertSame($builder, $result);

        $config = $builder->toArray();
        $this->assertEquals('show', $config['action']);
    }

    /**
     * Test defaults() method sets arbitrary default
     */
    public function testDefaultsMethod(): void
    {
        $builder = new RouteBuilder('posts/:slug');
        $result = $builder->defaults('format', 'html');

        $this->assertSame($builder, $result);

        $config = $builder->toArray();
        $this->assertEquals('html', $config['format']);
    }

    // ============================================================
    // Requirements & Conditions Tests
    // ============================================================

    /**
     * Test requires() method adds requirement
     */
    public function testRequiresMethod(): void
    {
        $builder = new RouteBuilder('users/:id');
        $result = $builder->requires('id', '\d+');

        $this->assertSame($builder, $result);

        $config = $builder->toArray();
        $this->assertArrayHasKey('requirements', $config);
        $this->assertEquals('\d+', $config['requirements']['id']);
    }

    /**
     * Test withRequirements() adds multiple requirements
     */
    public function testWithRequirements(): void
    {
        $builder = new RouteBuilder('posts/:year/:month/:day');
        $result = $builder->withRequirements([
            'year' => '\d{4}',
            'month' => '\d{2}',
            'day' => '\d{2}'
        ]);

        $this->assertSame($builder, $result);

        $config = $builder->toArray();
        $this->assertEquals('\d{4}', $config['requirements']['year']);
        $this->assertEquals('\d{2}', $config['requirements']['month']);
        $this->assertEquals('\d{2}', $config['requirements']['day']);
    }

    /**
     * Test HTTP method shorthand methods
     */
    public function testHttpMethodShorthand(): void
    {
        // Test get()
        $builder1 = new RouteBuilder('users');
        $builder1->get();
        $config1 = $builder1->toArray();
        $this->assertEquals(['GET'], $config1['conditions']['method']);

        // Test post()
        $builder2 = new RouteBuilder('users');
        $builder2->post();
        $config2 = $builder2->toArray();
        $this->assertEquals(['POST'], $config2['conditions']['method']);

        // Test put()
        $builder3 = new RouteBuilder('users/:id');
        $builder3->put();
        $config3 = $builder3->toArray();
        $this->assertEquals(['PUT'], $config3['conditions']['method']);

        // Test delete()
        $builder4 = new RouteBuilder('users/:id');
        $builder4->delete();
        $config4 = $builder4->toArray();
        $this->assertEquals(['DELETE'], $config4['conditions']['method']);

        // Test patch()
        $builder5 = new RouteBuilder('users/:id');
        $builder5->patch();
        $config5 = $builder5->toArray();
        $this->assertEquals(['PATCH'], $config5['conditions']['method']);
    }

    /**
     * Test methods() with array of HTTP methods
     */
    public function testMethodsArray(): void
    {
        $builder = new RouteBuilder('users/:id');
        $result = $builder->methods(['GET', 'HEAD']);

        $this->assertSame($builder, $result);

        $config = $builder->toArray();
        $this->assertEquals(['GET', 'HEAD'], $config['conditions']['method']);
    }

    /**
     * Test withSubdomain() condition
     */
    public function testWithSubdomainCondition(): void
    {
        $builder = new RouteBuilder('api/users');
        $result = $builder->withSubdomain('api');

        $this->assertSame($builder, $result);

        $config = $builder->toArray();
        $this->assertEquals('api', $config['conditions']['subDomain']);
    }

    /**
     * Test where() custom condition function
     */
    public function testWhereFunction(): void
    {
        $builder = new RouteBuilder('special/:id');
        $callable = function($environ) { return true; };
        $result = $builder->where($callable);

        $this->assertSame($builder, $result);

        $config = $builder->toArray();
        $this->assertSame($callable, $config['conditions']['function']);
    }

    // ============================================================
    // Middleware & Flags Tests
    // ============================================================

    /**
     * Test withMiddleware() sets middleware stack
     */
    public function testWithMiddlewareStack(): void
    {
        $builder = new RouteBuilder('api/users');
        $result = $builder->withMiddleware(['ApiAuth', 'RateLimit']);

        $this->assertSame($builder, $result);

        $config = $builder->toArray();
        $this->assertEquals(['ApiAuth', 'RateLimit'], $config['stack']);
    }

    /**
     * Test noMiddleware() sets empty stack
     */
    public function testNoMiddleware(): void
    {
        $builder = new RouteBuilder('public/health');
        $result = $builder->noMiddleware();

        $this->assertSame($builder, $result);

        $config = $builder->toArray();
        $this->assertIsArray($config['stack']);
        $this->assertEmpty($config['stack']);
    }

    /**
     * Test absolute() flag marks route as absolute
     */
    public function testAbsoluteFlag(): void
    {
        $builder = new RouteBuilder('/absolute/path');
        $result = $builder->absolute();

        $this->assertSame($builder, $result);

        $config = $builder->toArray();
        $this->assertTrue($config['_absolute']);
    }

    // ============================================================
    // Builder Output Tests
    // ============================================================

    /**
     * Test toArray() produces correct structure
     */
    public function testToArrayFormat(): void
    {
        $builder = new RouteBuilder('api/users/:id');
        $builder->withController('User')
                ->withAction('show')
                ->requires('id', '\d+')
                ->get()
                ->withMiddleware(['Auth']);

        $config = $builder->toArray();

        // Check defaults
        $this->assertEquals('User', $config['controller']);
        $this->assertEquals('show', $config['action']);

        // Check requirements
        $this->assertArrayHasKey('requirements', $config);
        $this->assertEquals('\d+', $config['requirements']['id']);

        // Check conditions
        $this->assertArrayHasKey('conditions', $config);
        $this->assertEquals(['GET'], $config['conditions']['method']);

        // Check stack
        $this->assertEquals(['Auth'], $config['stack']);
    }

    /**
     * Test build() creates valid Route object
     */
    public function testBuildCreatesRoute(): void
    {
        $builder = new RouteBuilder('users/:id');
        $builder->withController('User')
                ->withAction('show')
                ->requires('id', '\d+');

        $route = $builder->build();

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('users/:id', $route->routePath);
        $this->assertEquals('User', $route->defaults['controller']);
        $this->assertEquals('show', $route->defaults['action']);
        $this->assertEquals('\d+', $route->reqs['id']);
    }

    /**
     * Test all methods return self for fluent chaining
     */
    public function testFluentChaining(): void
    {
        $builder = new RouteBuilder('users/:id');

        // Chain multiple methods
        $result = $builder
            ->withName('user_show')
            ->withController('User')
            ->withAction('show')
            ->requires('id', '\d+')
            ->get()
            ->withMiddleware(['Auth'])
            ->absolute(false);

        // Final result should be the same builder instance
        $this->assertSame($builder, $result);

        // Should be able to build after chaining
        $route = $builder->build();
        $this->assertInstanceOf(Route::class, $route);
    }

    // ============================================================
    // Edge Cases
    // ============================================================

    /**
     * Test method called twice - last wins
     */
    public function testMethodCalledTwiceLastWins(): void
    {
        $builder = new RouteBuilder('users/:id');
        $builder->withController('User')
                ->withController('Admin');

        $config = $builder->toArray();
        $this->assertEquals('Admin', $config['controller']);
    }

    /**
     * Test withDefaults() merges with existing defaults
     */
    public function testWithDefaultsMerges(): void
    {
        $builder = new RouteBuilder('posts/:id');
        $builder->withController('Post')
                ->withDefaults([
                    'action' => 'show',
                    'format' => 'html'
                ]);

        $config = $builder->toArray();
        $this->assertEquals('Post', $config['controller']);
        $this->assertEquals('show', $config['action']);
        $this->assertEquals('html', $config['format']);
    }

    /**
     * Test null values in defaults
     */
    public function testNullValuesInDefaults(): void
    {
        $builder = new RouteBuilder('posts');
        $builder->defaults('page', null);

        $config = $builder->toArray();
        $this->assertArrayHasKey('page', $config);
        $this->assertNull($config['page']);
    }

    /**
     * Test complex real-world route
     */
    public function testComplexRealWorldRoute(): void
    {
        $builder = new RouteBuilder('api/v2/:resource/:id');
        $builder->withName('api_resource_show')
                ->requires('id', '\d+')
                ->methods(['GET', 'HEAD'])
                ->withMiddleware(['ApiAuth', 'RateLimit', 'JsonResponse'])
                ->withDefaults([
                    'version' => 'v2',
                    'format' => 'json'
                ]);

        $config = $builder->toArray();

        // Verify all components
        $this->assertEquals('api_resource_show', $builder->getName());
        $this->assertEquals('\d+', $config['requirements']['id']);
        $this->assertEquals(['GET', 'HEAD'], $config['conditions']['method']);
        $this->assertEquals(['ApiAuth', 'RateLimit', 'JsonResponse'], $config['stack']);
        $this->assertEquals('v2', $config['version']);
        $this->assertEquals('json', $config['format']);

        // Should build successfully
        $route = $builder->build();
        $this->assertInstanceOf(Route::class, $route);
    }
}
