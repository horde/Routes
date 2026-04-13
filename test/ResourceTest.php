<?php

/**
 * Tests for RESTful resource routing in Routes
 *
 * The resource() method creates RESTful route patterns for CRUD operations.
 * It generates standard routes for index, show, new, create, edit, update, delete.
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Routes
 */

namespace Horde\Routes\Test;

use PHPUnit\Framework\TestCase;
use Horde\Routes\Mapper;

/**
 * @package Routes
 * @coversNothing
 */
class ResourceTest extends TestCase
{
    /**
     * Test basic resource routes (messages)
     */
    public function testBasicResourceRoutes(): void
    {
        $mapper = new Mapper();
        $mapper->resource('message', 'messages');
        $mapper->createRegs(['messages']);

        // GET /messages - index action
        $mapper->environ = ['REQUEST_METHOD' => 'GET'];
        $match = $mapper->match('/messages');
        $this->assertIsArray($match);
        $this->assertEquals('messages', $match['controller']);
        $this->assertEquals('index', $match['action']);

        // GET /messages/123 - show action
        $mapper->environ = ['REQUEST_METHOD' => 'GET'];
        $match = $mapper->match('/messages/123');
        $this->assertIsArray($match);
        $this->assertEquals('messages', $match['controller']);
        $this->assertEquals('show', $match['action']);
        $this->assertEquals('123', $match['id']);

        // GET /messages/new - new action (form)
        $mapper->environ = ['REQUEST_METHOD' => 'GET'];
        $match = $mapper->match('/messages/new');
        $this->assertIsArray($match);
        $this->assertEquals('messages', $match['controller']);
        $this->assertEquals('new', $match['action']);

        // GET /messages/123/edit - edit action (form)
        $mapper->environ = ['REQUEST_METHOD' => 'GET'];
        $match = $mapper->match('/messages/123/edit');
        $this->assertIsArray($match);
        $this->assertEquals('messages', $match['controller']);
        $this->assertEquals('edit', $match['action']);
        $this->assertEquals('123', $match['id']);
    }

    /**
     * Test POST/PUT/DELETE HTTP methods
     */
    public function testResourceHttpMethods(): void
    {
        $mapper = new Mapper();
        $mapper->resource('message', 'messages');
        $mapper->createRegs(['messages']);

        // POST /messages - create action
        $mapper->environ = ['REQUEST_METHOD' => 'POST'];
        $match = $mapper->match('/messages');
        $this->assertIsArray($match);
        $this->assertEquals('messages', $match['controller']);
        $this->assertEquals('create', $match['action']);

        // PUT /messages/123 - update action
        $mapper->environ = ['REQUEST_METHOD' => 'PUT'];
        $match = $mapper->match('/messages/123');
        $this->assertIsArray($match);
        $this->assertEquals('messages', $match['controller']);
        $this->assertEquals('update', $match['action']);
        $this->assertEquals('123', $match['id']);

        // DELETE /messages/123 - delete action
        $mapper->environ = ['REQUEST_METHOD' => 'DELETE'];
        $match = $mapper->match('/messages/123');
        $this->assertIsArray($match);
        $this->assertEquals('messages', $match['controller']);
        $this->assertEquals('delete', $match['action']);
        $this->assertEquals('123', $match['id']);
    }

    /**
     * Test resource route generation
     */
    public function testResourceRouteGeneration(): void
    {
        $mapper = new Mapper();
        $mapper->resource('message', 'messages');
        $mapper->createRegs(['messages']);

        // Generate index route
        $url = $mapper->generate(['controller' => 'messages', 'action' => 'index']);
        $this->assertEquals('/messages', $url);

        // Generate show route
        $url = $mapper->generate(['controller' => 'messages', 'action' => 'show', 'id' => 42]);
        $this->assertEquals('/messages/42', $url);

        // Generate new route
        $url = $mapper->generate(['controller' => 'messages', 'action' => 'new']);
        $this->assertEquals('/messages/new', $url);

        // Generate edit route
        $url = $mapper->generate(['controller' => 'messages', 'action' => 'edit', 'id' => 42]);
        $this->assertEquals('/messages/42/edit', $url);
    }

    /**
     * Test resource with custom controller name
     */
    public function testResourceWithCustomController(): void
    {
        $mapper = new Mapper();
        $mapper->resource('message', 'messages', ['controller' => 'msg']);
        $mapper->createRegs(['msg']);

        $mapper->environ = ['REQUEST_METHOD' => 'GET'];
        $match = $mapper->match('/messages');
        $this->assertIsArray($match);
        $this->assertEquals('msg', $match['controller']);
        $this->assertEquals('index', $match['action']);
    }

    /**
     * Test resource with path prefix
     */
    public function testResourceWithPathPrefix(): void
    {
        $mapper = new Mapper();
        $mapper->resource('comment', 'comments', ['pathPrefix' => '/admin']);
        $mapper->createRegs(['comments']);

        // GET /admin/comments - index
        $mapper->environ = ['REQUEST_METHOD' => 'GET'];
        $match = $mapper->match('/admin/comments');
        $this->assertIsArray($match);
        $this->assertEquals('comments', $match['controller']);
        $this->assertEquals('index', $match['action']);
    }

    /**
     * Test nested resources (parent resource)
     */
    public function testNestedResources(): void
    {
        $mapper = new Mapper();

        // Create nested resource (comments under posts)
        $parentResource = [
            'memberName' => 'post',
            'collectionName' => 'posts',
        ];
        $mapper->resource('comment', 'comments', ['parentResource' => $parentResource]);
        $mapper->createRegs(['comments']);

        // GET /posts/5/comments - index of comments for post 5
        $mapper->environ = ['REQUEST_METHOD' => 'GET'];
        $match = $mapper->match('/posts/5/comments');
        $this->assertIsArray($match);
        $this->assertEquals('comments', $match['controller']);
        $this->assertEquals('index', $match['action']);
        $this->assertEquals('5', $match['post_id']);
    }

    /**
     * Test resource routes with routematch() return route object
     */
    public function testResourceMetadata(): void
    {
        $mapper = new Mapper();
        $mapper->resource('message', 'messages');
        $mapper->createRegs(['messages']);

        $mapper->environ = ['REQUEST_METHOD' => 'GET'];
        $result = $mapper->routematch('/messages');
        $this->assertIsArray($result);

        $match = $result[0];
        $route = $result[1];

        // Check basic route match
        $this->assertEquals('messages', $match['controller']);
        $this->assertEquals('index', $match['action']);

        // Route object has the route details
        $this->assertEquals('messages', $route->defaults['controller']);
        $this->assertEquals('index', $route->defaults['action']);
    }

    /**
     * Test resource with formatted routes (REST API with format extension)
     */
    public function testResourceWithFormat(): void
    {
        $mapper = new Mapper();
        $mapper->resource('api', 'apis');
        $mapper->createRegs(['apis']);

        // GET /apis.json - index with format
        $mapper->environ = ['REQUEST_METHOD' => 'GET'];
        $match = $mapper->match('/apis.json');
        $this->assertIsArray($match);
        $this->assertEquals('apis', $match['controller']);
        $this->assertEquals('index', $match['action']);
        $this->assertEquals('json', $match['format']);

        // GET /apis/123.xml - show with format
        $mapper->environ = ['REQUEST_METHOD' => 'GET'];
        $match = $mapper->match('/apis/123.xml');
        $this->assertEquals('apis', $match['controller']);
        $this->assertEquals('show', $match['action']);
        $this->assertEquals('123', $match['id']);
        $this->assertEquals('xml', $match['format']);
    }
}
