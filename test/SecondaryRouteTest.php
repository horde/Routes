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
 * Tests for secondary/legacy route feature
 *
 * @package Routes
 */
class SecondaryRouteTest extends TestCase
{
    /**
     * Test that secondary route matches incoming URL
     */
    public function testSecondaryRouteMatches(): void
    {
        $m = new Mapper();
        $m->buildRoute(uri: 'api/users/:id')
            ->withController('User')
            ->withAction('show')
            ->withSecondaryRoute('user/:id')
            ->add();

        // Both should match
        $result1 = $m->match('/api/users/123');
        $this->assertIsArray($result1);
        $this->assertEquals('User', $result1['controller']);
        $this->assertEquals('show', $result1['action']);
        $this->assertEquals('123', $result1['id']);

        $result2 = $m->match('/user/123');
        $this->assertIsArray($result2);
        $this->assertEquals('User', $result2['controller']);
        $this->assertEquals('show', $result2['action']);
        $this->assertEquals('123', $result2['id']);
    }

    /**
     * Test that secondary route is NOT used for generation
     */
    public function testSecondaryRouteNotGenerated(): void
    {
        $m = new Mapper();
        $m->buildRoute(uri: 'api/users/:id')
            ->withController('User')
            ->withAction('show')
            ->withSecondaryRoute('user/:id')
            ->add();

        // Generation should only use primary route
        $url = $m->generate(['controller' => 'User', 'action' => 'show', 'id' => '123']);
        $this->assertEquals('/api/users/123', $url);

        // Should NOT generate /user/123 (secondary route)
        $this->assertNotEquals('/user/123', $url);
    }

    /**
     * Test that primary route still generates correctly
     */
    public function testPrimaryRouteStillGenerates(): void
    {
        $m = new Mapper();
        $m->buildRoute(uri: 'users/:id/profile')
            ->withController('User')
            ->withAction('show')
            ->withSecondaryRoute('profile/:id')
            ->withSecondaryRoute('member/:id')
            ->add();

        // Primary should be used for generation
        $url = $m->generate(['controller' => 'User', 'action' => 'show', 'id' => '42']);
        $this->assertEquals('/users/42/profile', $url);
    }

    /**
     * Test multiple secondary routes for same controller
     */
    public function testMultipleSecondaryRoutes(): void
    {
        $m = new Mapper();
        $m->buildRoute(uri: 'api/users/:id')
            ->withController('User')
            ->withAction('show')
            ->withSecondaryRoute('user/:id')
            ->withSecondaryRoute('profile/:id')
            ->withSecondaryRoute('member/:id')
            ->add();

        // All should match
        $this->assertNotNull($m->match('/api/users/123'));
        $this->assertNotNull($m->match('/user/123'));
        $this->assertNotNull($m->match('/profile/123'));
        $this->assertNotNull($m->match('/member/123'));

        // But only primary generates
        $url = $m->generate(['controller' => 'User', 'action' => 'show', 'id' => '123']);
        $this->assertEquals('/api/users/123', $url);
    }

    /**
     * Test named secondary routes
     */
    public function testSecondaryNamedRoute(): void
    {
        $m = new Mapper();
        $m->buildRoute(uri: 'users/:id', name: 'user_profile')
            ->withController('User')
            ->withAction('show')
            ->withSecondaryRoute('profile/:id')
            ->add();

        // Both should match
        $this->assertNotNull($m->match('/users/123'));
        $this->assertNotNull($m->match('/profile/123'));

        // Generation should use primary
        $url = $m->generate(['controller' => 'User', 'action' => 'show', 'id' => '123']);
        $this->assertEquals('/users/123', $url);
    }

    /**
     * Test secondary route with dynamic parameters
     */
    public function testSecondaryWithParameters(): void
    {
        $m = new Mapper();
        $m->buildRoute(uri: 'posts/:year/:month/:slug')
            ->withController('Post')
            ->withAction('show')
            ->withSecondaryRoute('blog/:year/:month/:slug')
            ->add();

        // Secondary should extract parameters correctly
        $result = $m->match('/blog/2024/03/hello-world');
        $this->assertIsArray($result);
        $this->assertEquals('2024', $result['year']);
        $this->assertEquals('03', $result['month']);
        $this->assertEquals('hello-world', $result['slug']);

        // Primary should generate
        $url = $m->generate([
            'controller' => 'Post',
            'action' => 'show',
            'year' => '2024',
            'month' => '03',
            'slug' => 'hello-world'
        ]);
        $this->assertEquals('/posts/2024/03/hello-world', $url);
    }

    /**
     * Test secondary route with requirements
     */
    public function testSecondaryWithRequirements(): void
    {
        $m = new Mapper();
        $m->buildRoute(uri: 'api/users/:id')
            ->withController('User')
            ->withAction('show')
            ->requires('id', '\d+')
            ->withSecondaryRoute('user/:id')
            ->add();

        // Numeric ID should match
        $this->assertNotNull($m->match('/user/123'));

        // Non-numeric ID should not match
        $this->assertNull($m->match('/user/abc'));
    }

    /**
     * Test secondary route with HTTP method conditions
     */
    public function testSecondaryWithConditions(): void
    {
        $m = new Mapper();
        $m->buildRoute(uri: 'api/users/:id')
            ->withController('User')
            ->withAction('show')
            ->withMethods(['GET'])
            ->withSecondaryRoute('user/:id')
            ->add();

        // GET should match (simulated via environ)
        $m->environ = ['REQUEST_METHOD' => 'GET'];
        $this->assertNotNull($m->match('/user/123'));

        // POST should not match
        $m->environ = ['REQUEST_METHOD' => 'POST'];
        $this->assertNull($m->match('/user/123'));
    }

    /**
     * Test getRouteList() includes secondary routes
     */
    public function testGetRouteListIncludesSecondary(): void
    {
        $m = new Mapper();
        $m->buildRoute(uri: 'users/:id')
            ->withController('User')
            ->withAction('show')
            ->withSecondaryRoute('profile/:id')
            ->add();

        $routes = $m->getRouteList();

        $this->assertCount(2, $routes);
        $this->assertEquals('primary', $routes[0]['type']);
        $this->assertEquals('users/:id', $routes[0]['path']);
        $this->assertEquals('secondary', $routes[1]['type']);
        $this->assertEquals('profile/:id', $routes[1]['path']);
    }

    /**
     * Test secondary routes appear in matchList
     */
    public function testSecondaryInMatchList(): void
    {
        $m = new Mapper();
        $m->buildRoute(uri: 'api/users/:id')
            ->withController('User')
            ->withAction('show')
            ->withSecondaryRoute('user/:id')
            ->add();

        $this->assertCount(2, $m->matchList);
        $this->assertFalse($m->matchList[0]->secondary);
        $this->assertTrue($m->matchList[1]->secondary);
    }

    /**
     * Test connectSecondary with all argument variations
     */
    public function testConnectSecondaryArguments(): void
    {
        $m = new Mapper();

        // 1 arg: path only
        $m->buildRoute(uri: 'simple/path')
            ->withDefaults(['_secondary' => true])
            ->add();
        $this->assertTrue($m->matchList[0]->secondary);

        // 2 args: path + kargs
        $m->buildRoute(uri: 'path/:id')
            ->withController('Test')
            ->withDefaults(['_secondary' => true])
            ->add();
        $this->assertTrue($m->matchList[1]->secondary);
        $this->assertEquals('Test', $m->matchList[1]->defaults['controller']);

        // 2 args: name + path
        $m->buildRoute(uri: 'named/path', name: 'test_route')
            ->withDefaults(['_secondary' => true])
            ->add();
        $this->assertTrue($m->matchList[2]->secondary);

        // 3 args: name + path + kargs
        $m->buildRoute(uri: 'another/:id', name: 'test_route2')
            ->withAction('show')
            ->withDefaults(['_secondary' => true])
            ->add();
        $this->assertTrue($m->matchList[3]->secondary);
        $this->assertEquals('show', $m->matchList[3]->defaults['action']);
    }

    /**
     * Test edge case: all routes are secondary (generation should fail gracefully)
     */
    public function testAllRoutesSecondary(): void
    {
        $m = new Mapper();
        $m->buildRoute(uri: 'user/:id')
            ->withController('User')
            ->withAction('show')
            ->withDefaults(['_secondary' => true])
            ->add();
        $m->buildRoute(uri: 'profile/:id')
            ->withController('User')
            ->withAction('show')
            ->withDefaults(['_secondary' => true])
            ->add();

        // Matching should still work
        $this->assertNotNull($m->match('/user/123'));

        // Generation should return null (no primary routes)
        $url = $m->generate(['controller' => 'User', 'action' => 'show', 'id' => '123']);
        $this->assertNull($url);
    }
}
