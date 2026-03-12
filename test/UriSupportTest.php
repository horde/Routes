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
use Horde\Routes\Route;
use Horde\Routes\Utils;
use Horde\Http\Uri;
use Psr\Http\Message\UriInterface;

/**
 * Tests for PSR-7 Uri support in Routes
 *
 * @package Routes
 */
class UriSupportTest extends TestCase
{
    // ============================================================
    // Route::generateUri() Tests
    // ============================================================

    /**
     * Test Route::generateUri() returns UriInterface
     */
    public function testRouteGenerateUriReturnsUriInterface(): void
    {
        $route = new Route('/users/:id', ['controller' => 'User']);
        $uri = $route->generateUri(['id' => '123']);

        $this->assertInstanceOf(UriInterface::class, $uri);
        $this->assertEquals('/users/123', (string) $uri);
    }

    /**
     * Test Route::generateUri() returns null when route doesn't match
     */
    public function testRouteGenerateUriReturnsNullOnNoMatch(): void
    {
        $route = new Route('/users/:id', [
            'controller' => 'User',
            'requirements' => ['id' => '\d+']
        ]);

        $uri = $route->generateUri(['id' => 'abc']);
        $this->assertNull($uri);
    }

    /**
     * Test Route::generateUri() with query parameters
     */
    public function testRouteGenerateUriWithQueryParams(): void
    {
        $route = new Route('/users/:id', ['controller' => 'User']);
        $uri = $route->generateUri(['id' => '123', 'format' => 'json']);

        $this->assertInstanceOf(UriInterface::class, $uri);
        $this->assertStringContainsString('/users/123', (string) $uri);
        $this->assertStringContainsString('format=json', (string) $uri);
    }

    /**
     * Test Route::generateUri() is immutable (returns Uri object)
     */
    public function testRouteGenerateUriIsImmutable(): void
    {
        $route = new Route('/users/:id', ['controller' => 'User']);
        $uri1 = $route->generateUri(['id' => '123']);
        $uri2 = $route->generateUri(['id' => '456']);

        $this->assertNotSame($uri1, $uri2);
        $this->assertEquals('/users/123', (string) $uri1);
        $this->assertEquals('/users/456', (string) $uri2);
    }

    // ============================================================
    // Mapper::generateUri() Tests
    // ============================================================

    /**
     * Test Mapper::generateUri() returns UriInterface
     */
    public function testMapperGenerateUriReturnsUriInterface(): void
    {
        $m = new Mapper();
        $m->connect('/users/:id', ['controller' => 'User']);

        $uri = $m->generateUri(['controller' => 'User', 'id' => '123']);

        $this->assertInstanceOf(UriInterface::class, $uri);
        $this->assertEquals('/users/123', (string) $uri);
    }

    /**
     * Test Mapper::generateUri() returns null when no route matches
     */
    public function testMapperGenerateUriReturnsNullOnNoMatch(): void
    {
        $m = new Mapper();
        $m->connect('/users/:id', ['controller' => 'User']);

        $uri = $m->generateUri(['controller' => 'NonExistent', 'id' => '123']);
        $this->assertNull($uri);
    }

    /**
     * Test Mapper::generateUri() with named routes
     */
    public function testMapperGenerateUriWithNamedRoute(): void
    {
        $m = new Mapper();
        $m->buildRoute(uri: '/users/:id', name: 'UserShow')
            ->withController('User')
            ->add();

        $uri = $m->generateUri(['controller' => 'User', 'id' => '123']);

        $this->assertInstanceOf(UriInterface::class, $uri);
        $this->assertEquals('/users/123', (string) $uri);
    }

    /**
     * Test Mapper::generateUri() with prefix
     */
    public function testMapperGenerateUriWithPrefix(): void
    {
        $m = new Mapper(['prefix' => '/api/v1']);
        $m->connect('/users/:id', ['controller' => 'User']);

        $uri = $m->generateUri(['controller' => 'User', 'id' => '123']);

        $this->assertInstanceOf(UriInterface::class, $uri);
        $this->assertEquals('/api/v1/users/123', (string) $uri);
    }

    /**
     * Test Mapper::generateUri() with two-arg form
     */
    public function testMapperGenerateUriTwoArgForm(): void
    {
        $m = new Mapper();
        $m->connect('/users/:id', ['controller' => 'User']);

        $route = $m->matchList[0];
        $uri = $m->generateUri([$route], ['controller' => 'User', 'id' => '123']);

        $this->assertInstanceOf(UriInterface::class, $uri);
        $this->assertEquals('/users/123', (string) $uri);
    }

    // ============================================================
    // Utils::urlForUri() Tests
    // ============================================================

    /**
     * Test Utils::urlForUri() returns UriInterface
     */
    public function testUtilsUrlForUriReturnsUriInterface(): void
    {
        $m = new Mapper();
        $m->connect('/users/:id', ['controller' => 'User', 'action' => 'show']);

        $uri = $m->utils->urlForUri(['controller' => 'User', 'action' => 'show', 'id' => '123']);

        $this->assertInstanceOf(UriInterface::class, $uri);
        $this->assertEquals('/users/123', (string) $uri);
    }

    /**
     * Test Utils::urlForUri() with static path
     */
    public function testUtilsUrlForUriWithStaticPath(): void
    {
        $m = new Mapper();
        $uri = $m->utils->urlForUri('/static/path');

        $this->assertInstanceOf(UriInterface::class, $uri);
        $this->assertEquals('/static/path', (string) $uri);
    }

    /**
     * Test Utils::urlForUri() with query parameters
     */
    public function testUtilsUrlForUriWithQueryParams(): void
    {
        $m = new Mapper();
        $m->connect('/users/:id', ['controller' => 'User', 'action' => 'show']);

        $uri = $m->utils->urlForUri([
            'controller' => 'User',
            'action' => 'show',
            'id' => '123',
            'format' => 'json'
        ]);

        $this->assertInstanceOf(UriInterface::class, $uri);
        $path = (string) $uri;
        $this->assertStringContainsString('/users/123', $path);
        $this->assertStringContainsString('format=json', $path);
    }

    /**
     * Test Utils::urlForUri() with anchor
     */
    public function testUtilsUrlForUriWithAnchor(): void
    {
        $m = new Mapper();
        $m->connect('/users/:id', ['controller' => 'User', 'action' => 'show']);

        $uri = $m->utils->urlForUri([
            'controller' => 'User',
            'action' => 'show',
            'id' => '123',
            'anchor' => 'profile'
        ]);

        $this->assertInstanceOf(UriInterface::class, $uri);
        $this->assertStringContainsString('#profile', (string) $uri);
    }

    /**
     * Test Utils::urlForUri() with qualified URL
     */
    public function testUtilsUrlForUriWithQualifiedUrl(): void
    {
        $m = new Mapper();
        $m->environ = [
            'HTTP_HOST' => 'example.com',
            'SERVER_NAME' => 'example.com',
            'HTTPS' => 'on'
        ];
        $m->connect('/users/:id', ['controller' => 'User', 'action' => 'show']);

        $uri = $m->utils->urlForUri([
            'controller' => 'User',
            'action' => 'show',
            'id' => '123',
            'qualified' => true
        ]);

        $this->assertInstanceOf(UriInterface::class, $uri);
        $url = (string) $uri;
        $this->assertStringContainsString('https://example.com', $url);
        $this->assertStringContainsString('/users/123', $url);
    }

    // ============================================================
    // Mapper Constructor Uri Input Tests
    // ============================================================

    /**
     * Test Mapper constructor accepts Uri for prefix
     */
    public function testMapperConstructorAcceptsUriPrefix(): void
    {
        $prefixUri = new Uri('https://example.com/api/v1/path');
        $m = new Mapper(['prefix' => $prefixUri]);

        $this->assertEquals('/api/v1/path', $m->prefix);
    }

    /**
     * Test Mapper constructor extracts path from Uri prefix
     */
    public function testMapperConstructorExtractsPathFromUri(): void
    {
        $prefixUri = new Uri('https://example.com:8080/api/v2?query=param#fragment');
        $m = new Mapper(['prefix' => $prefixUri]);

        // Should only extract path component
        $this->assertEquals('/api/v2', $m->prefix);
    }

    /**
     * Test Mapper constructor still accepts string prefix
     */
    public function testMapperConstructorAcceptsStringPrefix(): void
    {
        $m = new Mapper(['prefix' => '/api/v1']);

        $this->assertEquals('/api/v1', $m->prefix);
    }

    /**
     * Test Mapper with Uri prefix generates correct URLs
     */
    public function testMapperWithUriPrefixGeneratesCorrectUrls(): void
    {
        $prefixUri = new Uri('https://example.com/api/v1');
        $m = new Mapper(['prefix' => $prefixUri]);
        $m->connect('/users/:id', ['controller' => 'User']);

        $uri = $m->generateUri(['controller' => 'User', 'id' => '123']);

        $this->assertInstanceOf(UriInterface::class, $uri);
        $this->assertEquals('/api/v1/users/123', (string) $uri);
    }

    // ============================================================
    // Uri Manipulation Tests (PSR-7 immutability)
    // ============================================================

    /**
     * Test Uri objects can be manipulated with withQuery()
     */
    public function testUriObjectSupportsWithQuery(): void
    {
        $m = new Mapper();
        $m->connect('/users/:id', ['controller' => 'User']);

        $uri = $m->generateUri(['controller' => 'User', 'id' => '123']);
        $uriWithQuery = $uri->withQuery('page=2&limit=10');

        $this->assertNotSame($uri, $uriWithQuery);
        $this->assertEquals('/users/123', (string) $uri);
        $this->assertStringContainsString('page=2', (string) $uriWithQuery);
        $this->assertStringContainsString('limit=10', (string) $uriWithQuery);
    }

    /**
     * Test Uri objects can be manipulated with withPath()
     */
    public function testUriObjectSupportsWithPath(): void
    {
        $m = new Mapper();
        $m->connect('/users/:id', ['controller' => 'User']);

        $uri = $m->generateUri(['controller' => 'User', 'id' => '123']);
        $uriWithNewPath = $uri->withPath('/admin/users/123');

        $this->assertNotSame($uri, $uriWithNewPath);
        $this->assertEquals('/users/123', (string) $uri);
        $this->assertEquals('/admin/users/123', (string) $uriWithNewPath);
    }

    /**
     * Test Uri objects can be manipulated with withFragment()
     */
    public function testUriObjectSupportsWithFragment(): void
    {
        $m = new Mapper();
        $m->connect('/users/:id', ['controller' => 'User']);

        $uri = $m->generateUri(['controller' => 'User', 'id' => '123']);
        $uriWithFragment = $uri->withFragment('profile');

        $this->assertNotSame($uri, $uriWithFragment);
        $this->assertEquals('/users/123', (string) $uri);
        $this->assertStringContainsString('#profile', (string) $uriWithFragment);
    }

    /**
     * Test Uri objects can build full URLs with withScheme() and withHost()
     */
    public function testUriObjectSupportsWithSchemeAndHost(): void
    {
        $m = new Mapper();
        $m->connect('/users/:id', ['controller' => 'User']);

        $uri = $m->generateUri(['controller' => 'User', 'id' => '123']);
        $fullUri = $uri->withScheme('https')->withHost('example.com');

        $this->assertNotSame($uri, $fullUri);
        $this->assertEquals('/users/123', (string) $uri);
        $this->assertEquals('https://example.com/users/123', (string) $fullUri);
    }

    // ============================================================
    // Integration Tests
    // ============================================================

    /**
     * Test complete workflow: build route with PSR-style API, generate Uri, manipulate
     */
    public function testCompleteUriWorkflow(): void
    {
        $m = new Mapper();

        // Build route with PSR-style API
        $m->buildRoute(uri: '/api/users/:id', name: 'ApiUserShow')
            ->withController('ApiUser')
            ->withAction('show')
            ->withMiddleware(['Auth'])
            ->add();

        // Generate Uri
        $uri = $m->generateUri(['controller' => 'ApiUser', 'action' => 'show', 'id' => '123']);

        // Verify basic Uri
        $this->assertInstanceOf(UriInterface::class, $uri);
        $this->assertEquals('/api/users/123', (string) $uri);

        // Manipulate Uri (add query params)
        $uriWithQuery = $uri->withQuery(http_build_query(['include' => 'profile', 'format' => 'json']));
        $this->assertStringContainsString('include=profile', (string) $uriWithQuery);
        $this->assertStringContainsString('format=json', (string) $uriWithQuery);

        // Build full URL
        $fullUri = $uriWithQuery->withScheme('https')->withHost('api.example.com')->withPort(443);
        $this->assertStringStartsWith('https://api.example.com', (string) $fullUri);
        $this->assertStringContainsString('/api/users/123', (string) $fullUri);
    }

    /**
     * Test Uri prefix from external component integration
     */
    public function testUriPrefixFromExternalComponent(): void
    {
        // Simulate receiving a base URI from another PSR-7 component
        $baseUri = new Uri('https://api.example.com/v2');

        // Create mapper with Uri prefix
        $m = new Mapper(['prefix' => $baseUri]);
        $m->buildRoute(uri: '/users/:id')
            ->withController('User')
            ->add();

        // Generate URL
        $uri = $m->generateUri(['controller' => 'User', 'id' => '123']);

        // Should have extracted path from base URI
        $this->assertEquals('/v2/users/123', (string) $uri);

        // Can build full URL by re-adding scheme/host
        $fullUri = $uri->withScheme($baseUri->getScheme())
                       ->withHost($baseUri->getHost());
        $this->assertEquals('https://api.example.com/v2/users/123', (string) $fullUri);
    }
}
