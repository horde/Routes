<?php
/**
 * Tests for stack parameter handling in Routes
 *
 * The stack parameter is used by horde/core's Rampage framework to specify
 * middleware stacks for routes. This feature has three states:
 * - unset/null: Use default middleware stack
 * - empty array []: NO middleware (explicit bypass)
 * - array with values: Use specified middleware
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
 */
class StackTest extends TestCase
{
    /**
     * Test empty stack with Mapper (modern class)
     */
    public function testEmptyStackWithMapperModern(): void
    {
        $mapper = new Mapper();
        $mapper->connect('public', '/public', [
            'controller' => 'PublicController',
            'stack' => []
        ]);

        $match = $mapper->match('/public');

        $this->assertIsArray($match);
        $this->assertArrayHasKey('stack', $match, 'Empty stack array must be in result');
        $this->assertSame([], $match['stack'], 'Stack must be empty array');
    }

    /**
     * Test null stack with Mapper (modern class)
     */
    public function testNullStackWithMapperModern(): void
    {
        $mapper = new Mapper();
        $mapper->connect('default', '/default', [
            'controller' => 'DefaultController',
            'stack' => null
        ]);

        $match = $mapper->match('/default');

        $this->assertIsArray($match);
        $this->assertArrayHasKey('stack', $match, 'Null stack must be in result');
        $this->assertNull($match['stack'], 'Stack value must be null');
    }

    /**
     * Test populated stack with Mapper (modern class)
     */
    public function testPopulatedStackWithMapperModern(): void
    {
        $stack = ['Auth', 'CSRF', 'Logging'];
        $mapper = new Mapper();
        $mapper->connect('protected', '/protected', [
            'controller' => 'ProtectedController',
            'stack' => $stack
        ]);

        $match = $mapper->match('/protected');

        $this->assertIsArray($match);
        $this->assertArrayHasKey('stack', $match);
        $this->assertSame($stack, $match['stack'], 'Stack must be preserved exactly');
    }

    /**
     * Test unset stack with Mapper (modern class)
     */
    public function testUnsetStackWithMapperModern(): void
    {
        $mapper = new Mapper();
        $mapper->connect('unset', '/unset', [
            'controller' => 'UnsetController'
        ]);

        $match = $mapper->match('/unset');

        $this->assertIsArray($match);
        $this->assertArrayNotHasKey('stack', $match, 'Unset stack must not be in result');
    }

    /**
     * Test that empty stack is different from unset stack
     */
    public function testEmptyStackVsUnsetStackModern(): void
    {
        $mapper = new Mapper();

        // Empty stack route
        $mapper->connect('empty', '/empty', ['controller' => 'Empty', 'stack' => []]);
        // Unset stack route
        $mapper->connect('unset', '/unset', ['controller' => 'Unset']);

        $mapper->createRegs();

        $match1 = $mapper->match('/empty');
        $match2 = $mapper->match('/unset');

        // Empty stack HAS the key
        $this->assertArrayHasKey('stack', $match1, 'Empty stack must have stack key');
        $this->assertSame([], $match1['stack'], 'Empty stack must be []');

        // Unset stack does NOT have the key
        $this->assertArrayNotHasKey('stack', $match2, 'Unset stack must not have stack key');
    }

    /**
     * Test empty stack with route parameters
     */
    public function testEmptyStackWithParametersModern(): void
    {
        $mapper = new Mapper();
        $mapper->connect('item', '/item/:id', [
            'controller' => 'ItemController',
            'action' => 'view',
            'stack' => []
        ]);

        $match = $mapper->match('/item/123');

        $this->assertIsArray($match);
        $this->assertEquals('123', $match['id'], 'Route parameter must be captured');
        $this->assertEquals('ItemController', $match['controller']);
        $this->assertEquals('view', $match['action']);
        $this->assertArrayHasKey('stack', $match);
        $this->assertSame([], $match['stack'], 'Empty stack must be preserved');
    }

    /**
     * Test that false !== null for stack (edge case)
     */
    public function testFalseStackPreservedModern(): void
    {
        $mapper = new Mapper();
        $mapper->connect('test', '/path', ['controller' => 'Test', 'stack' => false]);
        $match = $mapper->match('/path');

        $this->assertIsArray($match);
        $this->assertArrayHasKey('stack', $match, 'False is not null, should be preserved');
        $this->assertFalse($match['stack']);
    }

    /**
     * Test that 0 !== null for stack (edge case)
     */
    public function testZeroStackPreservedModern(): void
    {
        $mapper = new Mapper();
        $mapper->connect('test', '/path', ['controller' => 'Test', 'stack' => 0]);
        $match = $mapper->match('/path');

        $this->assertIsArray($match);
        $this->assertArrayHasKey('stack', $match, 'Zero is not null, should be preserved');
        $this->assertSame(0, $match['stack']);
    }

    /**
     * Test that empty string !== null for stack (edge case)
     */
    public function testEmptyStringStackPreservedModern(): void
    {
        $mapper = new Mapper();
        $mapper->connect('test', '/path', ['controller' => 'Test', 'stack' => '']);
        $match = $mapper->match('/path');

        $this->assertIsArray($match);
        $this->assertArrayHasKey('stack', $match, 'Empty string is not null, should be preserved');
        $this->assertSame('', $match['stack']);
    }

    /**
     * Test multiple routes with different stack configurations
     */
    public function testMultipleRoutesWithDifferentStacksModern(): void
    {
        $mapper = new Mapper();

        $mapper->connect('public', '/public', ['controller' => 'Public', 'stack' => []]);
        $mapper->connect('admin', '/admin', ['controller' => 'Admin', 'stack' => ['Auth', 'Admin']]);
        $mapper->connect('api', '/api', ['controller' => 'Api', 'stack' => ['ApiAuth']]);
        $mapper->connect('static', '/static', ['controller' => 'Static']); // No stack

        $mapper->createRegs();

        // Test each route
        $public = $mapper->match('/public');
        $this->assertSame([], $public['stack'], 'Public route has empty stack');

        $admin = $mapper->match('/admin');
        $this->assertSame(['Auth', 'Admin'], $admin['stack'], 'Admin route has middleware');

        $api = $mapper->match('/api');
        $this->assertSame(['ApiAuth'], $api['stack'], 'API route has API middleware');

        $static = $mapper->match('/static');
        $this->assertArrayNotHasKey('stack', $static, 'Static route has no stack key');
    }
}
