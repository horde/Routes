<?php

/**
 * Tests for stack parameter handling in legacy Routes (PSR-0)
 *
 * These tests verify that the legacy Horde_Routes_* classes have the same
 * stack parameter behavior as the modern namespaced classes.
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Routes
 */

namespace Horde\Routes\Test;

use PHPUnit\Framework\TestCase;
use Horde_Routes_Mapper;

/**
 * @package Routes
 * @coversNothing
 */
class StackLegacyTest extends TestCase
{
    /**
     * Test that empty stack array is preserved in match result (legacy class)
     */
    public function testEmptyStackArrayPreservedLegacy(): void
    {
        $mapper = new Horde_Routes_Mapper();
        $mapper->connect('test', '/path', ['controller' => 'Test', 'stack' => []]);
        $match = $mapper->match('/path');

        $this->assertIsArray($match, 'Match should return array');
        $this->assertArrayHasKey('stack', $match, 'Stack key must be in match result');
        $this->assertIsArray($match['stack'], 'Stack value must be array');
        $this->assertEmpty($match['stack'], 'Stack must be empty array');
        $this->assertSame([], $match['stack'], 'Stack must be exactly []');
    }

    /**
     * Test that null stack is preserved in match result (legacy class)
     */
    public function testNullStackPreservedLegacy(): void
    {
        $mapper = new Horde_Routes_Mapper();
        $mapper->connect('test', '/path', ['controller' => 'Test', 'stack' => null]);
        $match = $mapper->match('/path');

        $this->assertIsArray($match, 'Match should return array');
        $this->assertArrayHasKey('stack', $match, 'Stack key must be in match result');
        $this->assertNull($match['stack'], 'Stack value must be null');
    }

    /**
     * Test that populated stack array is preserved (legacy class)
     */
    public function testPopulatedStackPreservedLegacy(): void
    {
        $stack = ['AuthMiddleware', 'LoggerMiddleware'];
        $mapper = new Horde_Routes_Mapper();
        $mapper->connect('test', '/path', ['controller' => 'Test', 'stack' => $stack]);
        $match = $mapper->match('/path');

        $this->assertIsArray($match, 'Match should return array');
        $this->assertArrayHasKey('stack', $match, 'Stack key must be in match result');
        $this->assertSame($stack, $match['stack'], 'Stack must be preserved exactly');
    }

    /**
     * Test that unset stack is NOT in match result (legacy class)
     */
    public function testUnsetStackNotInResultLegacy(): void
    {
        $mapper = new Horde_Routes_Mapper();
        $mapper->connect('test', '/path', ['controller' => 'Test']);
        $match = $mapper->match('/path');

        $this->assertIsArray($match, 'Match should return array');
        $this->assertArrayNotHasKey('stack', $match, 'Unset stack must not be in match result');
    }

    /**
     * Test empty stack with Mapper (legacy class)
     */
    public function testEmptyStackWithMapperLegacy(): void
    {
        $mapper = new Horde_Routes_Mapper();
        $mapper->connect('public', '/public', [
            'controller' => 'PublicController',
            'stack' => [],
        ]);

        $match = $mapper->match('/public');

        $this->assertIsArray($match);
        $this->assertArrayHasKey('stack', $match);
        $this->assertSame([], $match['stack']);
    }

    /**
     * Test null stack with Mapper (legacy class)
     */
    public function testNullStackWithMapperLegacy(): void
    {
        $mapper = new Horde_Routes_Mapper();
        $mapper->connect('default', '/default', [
            'controller' => 'DefaultController',
            'stack' => null,
        ]);

        $match = $mapper->match('/default');

        $this->assertIsArray($match);
        $this->assertArrayHasKey('stack', $match);
        $this->assertNull($match['stack']);
    }

    /**
     * Test populated stack with Mapper (legacy class)
     */
    public function testPopulatedStackWithMapperLegacy(): void
    {
        $stack = ['Auth', 'CSRF', 'Logging'];
        $mapper = new Horde_Routes_Mapper();
        $mapper->connect('protected', '/protected', [
            'controller' => 'ProtectedController',
            'stack' => $stack,
        ]);

        $match = $mapper->match('/protected');

        $this->assertIsArray($match);
        $this->assertArrayHasKey('stack', $match);
        $this->assertSame($stack, $match['stack']);
    }

    /**
     * Test unset stack with Mapper (legacy class)
     */
    public function testUnsetStackWithMapperLegacy(): void
    {
        $mapper = new Horde_Routes_Mapper();
        $mapper->connect('unset', '/unset', [
            'controller' => 'UnsetController',
        ]);

        $match = $mapper->match('/unset');

        $this->assertIsArray($match);
        $this->assertArrayNotHasKey('stack', $match);
    }

    /**
     * Test that empty stack is different from unset stack (legacy)
     */
    public function testEmptyStackVsUnsetStackLegacy(): void
    {
        $mapper = new Horde_Routes_Mapper();

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
     * Test empty stack with additional route parameters (legacy)
     */
    public function testEmptyStackWithParametersLegacy(): void
    {
        $mapper = new Horde_Routes_Mapper();
        $mapper->connect('item', '/item/:id', [
            'controller' => 'ItemController',
            'action' => 'view',
            'stack' => [],
        ]);

        $match = $mapper->match('/item/123');

        $this->assertIsArray($match);
        $this->assertEquals('123', $match['id']);
        $this->assertEquals('ItemController', $match['controller']);
        $this->assertEquals('view', $match['action']);
        $this->assertArrayHasKey('stack', $match);
        $this->assertSame([], $match['stack']);
    }

    /**
     * Test feature parity: legacy and modern behave identically
     */
    public function testLegacyModernParity(): void
    {
        $params = ['controller' => 'Test', 'stack' => []];

        // Modern
        $modernMapper = new \Horde\Routes\Mapper();
        $modernMapper->connect('test', '/path', $params);
        $modernMatch = $modernMapper->match('/path');

        // Legacy
        $legacyMapper = new Horde_Routes_Mapper();
        $legacyMapper->connect('test', '/path', $params);
        $legacyMatch = $legacyMapper->match('/path');

        // Should be identical
        $this->assertEquals($modernMatch, $legacyMatch, 'Legacy and modern must have identical behavior');
    }
}
