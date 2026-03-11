<?php
/**
 * Tests for PSR-7 Matcher class
 *
 * The Matcher class is a convenience wrapper that matches PSR-7 ServerRequest
 * objects against Routes mapper. It extracts the path from the request and
 * returns the matched route dictionary.
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Routes
 */

namespace Horde\Routes\Test;

use PHPUnit\Framework\TestCase;
use Horde\Routes\Mapper;
use Horde\Routes\Matcher;
use Horde_Support_Array;

/**
 * Simple test request object that implements getPath() for Matcher testing
 * This mimics the interface that Matcher expects without requiring a full PSR-7 implementation
 */
class TestRequest
{
    private string $path;
    private string $method;

    public function __construct(string $path, string $method = 'GET')
    {
        $this->path = $path;
        $this->method = $method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMethod(): string
    {
        return $this->method;
    }
}

/**
 * @package Routes
 */
class MatcherTest extends TestCase
{
    /**
     * Test basic PSR-7 request matching
     */
    public function testBasicRequestMatching(): void
    {
        $mapper = new Mapper();
        $mapper->connect(':controller/:action/:id');
        $mapper->createRegs(['blog']);

        // Create test request
        $request = new TestRequest('/blog/view/123');

        $matcher = new Matcher($mapper, $request);
        $matchDict = $matcher->getMatchDict();

        $this->assertInstanceOf(Horde_Support_Array::class, $matchDict);
        $this->assertEquals('blog', $matchDict['controller']);
        $this->assertEquals('view', $matchDict['action']);
        $this->assertEquals('123', $matchDict['id']);
    }

    /**
     * Test request with query string
     */
    public function testRequestWithQueryString(): void
    {
        $mapper = new Mapper();
        $mapper->connect(':controller/:action');
        $mapper->createRegs(['blog']);

        // Request with query string - should be stripped for matching
        $request = new TestRequest('/blog/list?page=2&sort=date');

        $matcher = new Matcher($mapper, $request);
        $matchDict = $matcher->getMatchDict();

        $this->assertEquals('blog', $matchDict['controller']);
        $this->assertEquals('list', $matchDict['action']);
        // Query parameters are not part of route matching
    }

    /**
     * Test request to root path
     */
    public function testRootPathRequest(): void
    {
        $mapper = new Mapper();
        $mapper->connect('', ['controller' => 'home', 'action' => 'index']);
        $mapper->createRegs();

        $request = new TestRequest('/');

        $matcher = new Matcher($mapper, $request);
        $matchDict = $matcher->getMatchDict();

        $this->assertEquals('home', $matchDict['controller']);
        $this->assertEquals('index', $matchDict['action']);
    }

    /**
     * Test request with empty path defaults to root
     */
    public function testEmptyPathRequest(): void
    {
        $mapper = new Mapper();
        $mapper->connect('', ['controller' => 'home', 'action' => 'index']);
        $mapper->createRegs();

        // Empty path should be treated as '/'
        $request = new TestRequest('');

        $matcher = new Matcher($mapper, $request);
        $matchDict = $matcher->getMatchDict();

        $this->assertEquals('home', $matchDict['controller']);
        $this->assertEquals('index', $matchDict['action']);
    }

    /**
     * Test non-matching request returns null
     */
    public function testNonMatchingRequest(): void
    {
        $mapper = new Mapper();
        $mapper->connect('blog/:action', ['controller' => 'blog']);
        $mapper->createRegs();

        // Request that doesn't match any route
        $request = new TestRequest('/admin/users');

        $matcher = new Matcher($mapper, $request);
        $matchDict = $matcher->getMatchDict();

        // Non-matching routes return Horde_Support_Array wrapping null
        $this->assertInstanceOf(Horde_Support_Array::class, $matchDict);
    }

    /**
     * Test matcher caches match result
     */
    public function testMatcherCachesResult(): void
    {
        $mapper = new Mapper();
        $mapper->connect(':controller/:action');
        $mapper->createRegs();

        $request = new TestRequest('/blog/view');
        $matcher = new Matcher($mapper, $request);

        // First call
        $matchDict1 = $matcher->getMatchDict();
        // Second call should return same instance (cached)
        $matchDict2 = $matcher->getMatchDict();

        $this->assertSame($matchDict1, $matchDict2);
    }

    /**
     * Test matcher with named routes
     */
    public function testMatcherWithNamedRoutes(): void
    {
        $mapper = new Mapper();
        $mapper->connect('blog_post', '/posts/:id', ['controller' => 'posts', 'action' => 'show']);
        $mapper->createRegs();

        $request = new TestRequest('/posts/42');
        $matcher = new Matcher($mapper, $request);
        $matchDict = $matcher->getMatchDict();

        $this->assertEquals('posts', $matchDict['controller']);
        $this->assertEquals('show', $matchDict['action']);
        $this->assertEquals('42', $matchDict['id']);
    }

    /**
     * Test matcher with RESTful resources
     */
    public function testMatcherWithResources(): void
    {
        $mapper = new Mapper();
        $mapper->resource('message', 'messages');
        $mapper->createRegs(['messages']);

        // Index request
        $mapper->environ = ['REQUEST_METHOD' => 'GET'];
        $request = new TestRequest('/messages');
        $matcher = new Matcher($mapper, $request);
        $matchDict = $matcher->getMatchDict();

        $this->assertEquals('messages', $matchDict['controller']);
        $this->assertEquals('index', $matchDict['action']);

        // Show request
        $mapper->environ = ['REQUEST_METHOD' => 'GET'];
        $request2 = new TestRequest('/messages/123');
        $matcher2 = new Matcher($mapper, $request2);
        $matchDict2 = $matcher2->getMatchDict();

        $this->assertEquals('messages', $matchDict2['controller']);
        $this->assertEquals('show', $matchDict2['action']);
        $this->assertEquals('123', $matchDict2['id']);
    }

    /**
     * Test matcher with route defaults
     */
    public function testMatcherWithRouteDefaults(): void
    {
        $mapper = new Mapper();
        $mapper->connect('archive/:year/:month', [
            'controller' => 'blog',
            'action' => 'archive',
            'month' => '01'
        ]);
        $mapper->createRegs();

        $request = new TestRequest('/archive/2024');
        $matcher = new Matcher($mapper, $request);
        $matchDict = $matcher->getMatchDict();

        $this->assertEquals('blog', $matchDict['controller']);
        $this->assertEquals('archive', $matchDict['action']);
        $this->assertEquals('2024', $matchDict['year']);
        $this->assertEquals('01', $matchDict['month']); // Default value
    }

    /**
     * Test matcher with stack parameter (middleware control)
     */
    public function testMatcherWithStackParameter(): void
    {
        $mapper = new Mapper();
        $mapper->connect('public/:action', [
            'controller' => 'public',
            'stack' => [] // No middleware
        ]);
        $mapper->connect('admin/:action', [
            'controller' => 'admin',
            'stack' => ['Auth', 'Admin'] // Specific middleware
        ]);
        $mapper->createRegs();

        // Public route with empty stack
        $request1 = new TestRequest('/public/index');
        $matcher1 = new Matcher($mapper, $request1);
        $matchDict1 = $matcher1->getMatchDict();

        $this->assertEquals('public', $matchDict1['controller']);
        $this->assertArrayHasKey('stack', $matchDict1);
        $this->assertSame([], $matchDict1['stack']);

        // Admin route with middleware stack
        $request2 = new TestRequest('/admin/dashboard');
        $matcher2 = new Matcher($mapper, $request2);
        $matchDict2 = $matcher2->getMatchDict();

        $this->assertEquals('admin', $matchDict2['controller']);
        $this->assertSame(['Auth', 'Admin'], $matchDict2['stack']);
    }

    /**
     * Test matcher with complex path patterns
     */
    public function testMatcherWithComplexPaths(): void
    {
        $mapper = new Mapper();
        $mapper->connect('user/:username/posts/:post_id/comments/:id', [
            'controller' => 'comments',
            'action' => 'show'
        ]);
        $mapper->createRegs();

        $request = new TestRequest('/user/johndoe/posts/42/comments/7');

        $matcher = new Matcher($mapper, $request);
        $matchDict = $matcher->getMatchDict();

        $this->assertEquals('comments', $matchDict['controller']);
        $this->assertEquals('show', $matchDict['action']);
        $this->assertEquals('johndoe', $matchDict['username']);
        $this->assertEquals('42', $matchDict['post_id']);
        $this->assertEquals('7', $matchDict['id']);
    }

    /**
     * Test matcher automatically populates environ from request
     */
    public function testMatcherPopulatesEnvironFromRequest(): void
    {
        $mapper = new Mapper();

        // Create routes that depend on HTTP method
        $mapper->resource('message', 'messages');
        $mapper->createRegs(['messages']);

        // Before creating matcher, environ should be empty or default
        $this->assertEmpty($mapper->environ);

        // Create request with GET method
        $request = new TestRequest('/messages');

        // Create matcher - should auto-populate environ
        $matcher = new Matcher($mapper, $request);
        $matchDict = $matcher->getMatchDict();

        // Verify environ was populated from request
        $this->assertArrayHasKey('REQUEST_METHOD', $mapper->environ);
        $this->assertEquals('GET', $mapper->environ['REQUEST_METHOD']);

        // Verify route matched correctly with method
        $this->assertEquals('messages', $matchDict['controller']);
        $this->assertEquals('index', $matchDict['action']);
    }

    /**
     * Test matcher respects HTTP method for RESTful routes
     */
    public function testMatcherRespectsHttpMethodForResources(): void
    {
        $mapper = new Mapper();
        $mapper->resource('post', 'posts');
        $mapper->createRegs(['posts']);

        // Test GET /posts (index action)
        $getRequest = new TestRequest('/posts', 'GET');
        $getMatcher = new Matcher($mapper, $getRequest);
        $getMatch = $getMatcher->getMatchDict();

        $this->assertEquals('posts', $getMatch['controller']);
        $this->assertEquals('index', $getMatch['action']);
        $this->assertEquals('GET', $mapper->environ['REQUEST_METHOD']);

        // Test POST /posts (create action)
        $postRequest = new TestRequest('/posts', 'POST');
        $postMatcher = new Matcher($mapper, $postRequest);
        $postMatch = $postMatcher->getMatchDict();

        $this->assertEquals('posts', $postMatch['controller']);
        $this->assertEquals('create', $postMatch['action']);
        $this->assertEquals('POST', $mapper->environ['REQUEST_METHOD']);
    }

    /**
     * Test matcher works without setting environ manually
     */
    public function testMatcherWorksWithoutManualEnvironSetup(): void
    {
        $mapper = new Mapper();

        // Define route that works regardless of method
        $mapper->connect('blog/:action/:id', ['controller' => 'blog']);
        $mapper->createRegs(['blog']);

        // DON'T manually set environ (this is what we're testing)
        // $mapper->environ = ['REQUEST_METHOD' => 'GET']; // OLD WAY

        $request = new TestRequest('/blog/view/123');
        $matcher = new Matcher($mapper, $request);
        $matchDict = $matcher->getMatchDict();

        // Should work even without manual environ setup
        $this->assertEquals('blog', $matchDict['controller']);
        $this->assertEquals('view', $matchDict['action']);
        $this->assertEquals('123', $matchDict['id']);

        // Environ should have been auto-populated
        $this->assertArrayHasKey('REQUEST_METHOD', $mapper->environ);
    }
}
