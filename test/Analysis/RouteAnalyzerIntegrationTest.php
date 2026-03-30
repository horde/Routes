<?php
/**
 * Horde Routes package
 *
 * @author  Ralf Lang <ralf.lang@ralf-lang.de>
 * @license http://www.horde.org/licenses/bsd BSD
 * @package Routes
 */

namespace Horde\Routes\Test\Analysis;

use PHPUnit\Framework\TestCase;
use Horde\Routes\Mapper;
use Horde\Routes\Analysis\RouteAnalyzer;
use Horde\Routes\Analysis\RouteAnalysisReport;

/**
 * Integration tests for RouteAnalyzer with real-world scenarios
 *
 * Tests complex routing patterns and performance with larger route sets.
 *
 * @package Routes
 * @group integration
 */
class RouteAnalyzerIntegrationTest extends TestCase
{
    // ============================================================
    // Real-World Scenarios
    // ============================================================

    /**
     * Test classic controller/action shadowing issue
     *
     * This is the most common routing mistake:
     * Route with :action before specific action routes
     */
    public function testClassicControllerActionShadowing(): void
    {
        $m = new Mapper();

        // Anti-pattern: catch-all with :action
        $m->connect('users/:action/:id', ['controller' => 'User']);

        // These specific routes are now unreachable
        $m->connect('users/search', ['controller' => 'Search', 'action' => 'users']);
        $m->connect('users/export', ['controller' => 'Export', 'action' => 'users']);

        $analyzer = new RouteAnalyzer($m);
        $warnings = $analyzer->analyze();

        // Should detect both shadowed routes
        $this->assertGreaterThanOrEqual(2, count($warnings));

        $shadowedPaths = [];
        foreach ($warnings as $warning) {
            if ($warning['type'] === 'shadowed') {
                $shadowedPaths[] = $warning['shadowed_route'];
            }
        }

        $this->assertContains('users/search', $shadowedPaths);
        $this->assertContains('users/export', $shadowedPaths);
    }

    /**
     * Test RESTful resource shadowing
     *
     * GET /posts/:id vs GET /posts/new - "new" gets interpreted as :id
     */
    public function testRESTfulResourceShadowing(): void
    {
        $m = new Mapper();

        // RESTful routes - common pattern
        $m->connect('posts/:id', [
            'controller' => 'Post',
            'action' => 'show',
            'conditions' => ['method' => ['GET']]
        ]);

        // Form to create new post - shadowed by :id route
        $m->connect('posts/new', [
            'controller' => 'Post',
            'action' => 'new',
            'conditions' => ['method' => ['GET']]
        ]);

        $analyzer = new RouteAnalyzer($m);
        $warnings = $analyzer->analyze();

        // Should detect posts/new being shadowed
        $this->assertNotEmpty($warnings);

        $shadowedNew = false;
        foreach ($warnings as $warning) {
            if ($warning['type'] === 'shadowed' &&
                str_contains($warning['shadowed_route'], 'posts/new')) {
                $shadowedNew = true;
                break;
            }
        }

        $this->assertTrue($shadowedNew, 'Should detect posts/new shadowed by posts/:id');
    }

    /**
     * Test API versioning does NOT cause shadowing
     *
     * v1/users/:id and v2/users/:id are different routes
     */
    public function testApiVersioningShadowing(): void
    {
        $m = new Mapper();

        // Different API versions - should not shadow
        $m->connect('api/v1/users/:id', ['controller' => 'Api\\V1\\User', 'action' => 'show']);
        $m->connect('api/v2/users/:id', ['controller' => 'Api\\V2\\User', 'action' => 'show']);

        $analyzer = new RouteAnalyzer($m);
        $warnings = $analyzer->analyze();

        // Should NOT detect shadowing (different paths)
        $this->assertEmpty($warnings, 'API versioning routes should not shadow each other');
    }

    /**
     * Test analyzer with large route set (100+ routes)
     *
     * Ensures performance is acceptable and finds issues in complex apps
     */
    public function testLargeRouteSet(): void
    {
        $m = new Mapper();

        // Add 100+ routes simulating a real application
        // Public routes
        $m->connect('', ['controller' => 'Home', 'action' => 'index']);
        $m->connect('about', ['controller' => 'Pages', 'action' => 'about']);
        $m->connect('contact', ['controller' => 'Pages', 'action' => 'contact']);

        // User routes (30 routes)
        for ($i = 0; $i < 15; $i++) {
            $m->connect("section{$i}/users/:id", ['controller' => "Section{$i}User", 'action' => 'show']);
            $m->connect("section{$i}/users", ['controller' => "Section{$i}User", 'action' => 'index']);
        }

        // API routes (50 routes)
        $resources = ['users', 'posts', 'comments', 'tags', 'categories', 'photos', 'videos', 'files', 'messages', 'notifications'];
        $actions = ['index', 'show', 'create', 'update', 'delete'];
        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                if ($action === 'index' || $action === 'create') {
                    $m->connect("api/{$resource}", [
                        'controller' => "Api\\" . ucfirst($resource),
                        'action' => $action,
                        'conditions' => ['method' => [$action === 'index' ? 'GET' : 'POST']]
                    ]);
                } else {
                    $m->connect("api/{$resource}/:id", [
                        'controller' => "Api\\" . ucfirst($resource),
                        'action' => $action,
                        'conditions' => ['method' => [
                            $action === 'show' ? 'GET' :
                            ($action === 'update' ? 'PUT' : 'DELETE')
                        ]]
                    ]);
                }
            }
        }

        // Admin routes (20 routes)
        for ($i = 0; $i < 20; $i++) {
            $m->connect("admin/module{$i}/:action", ['controller' => "Admin\\Module{$i}"]);
        }

        // Add problematic route that shadows
        $m->connect('api/:resource/:id', ['controller' => 'Api\\Generic']);

        $this->assertGreaterThan(100, count($m->matchList), 'Should have 100+ routes');

        $analyzer = new RouteAnalyzer($m);

        $startTime = microtime(true);
        $warnings = $analyzer->analyze();
        $duration = microtime(true) - $startTime;

        // Should complete in reasonable time
        $this->assertLessThan(5.0, $duration, 'Analysis should complete in under 5 seconds for 100+ routes');

        // Should find the problematic api/:resource/:id route
        $this->assertNotEmpty($warnings, 'Should detect shadowing in large route set');
    }

    // ============================================================
    // Report Format Tests
    // ============================================================

    /**
     * Test text report format
     *
     * Human-readable output with sections and formatting
     */
    public function testTextReportFormat(): void
    {
        $m = new Mapper();

        // Create some issues
        $m->connect('users/:action', ['controller' => 'User']);
        $m->connect('users/search', ['controller' => 'Search', 'action' => 'users']);

        $analyzer = new RouteAnalyzer($m);
        $warnings = $analyzer->analyze();

        $report = new RouteAnalysisReport($warnings);
        $textOutput = $report->formatText();

        $this->assertIsString($textOutput);
        $this->assertNotEmpty($textOutput);

        // Should contain sections
        $this->assertStringContainsString('Route Analysis', $textOutput);
        $this->assertStringContainsString('Shadowed', $textOutput);

        // Should contain route information
        $this->assertStringContainsString('users/search', $textOutput);
        $this->assertStringContainsString('users/:action', $textOutput);
    }

    /**
     * Test JSON report format
     *
     * Machine-readable output for tooling integration
     */
    public function testJsonReportFormat(): void
    {
        $m = new Mapper();

        // Create some issues
        $m->connect('posts/:id', ['controller' => 'Post', 'action' => 'show']);
        $m->connect('posts/recent', ['controller' => 'Post', 'action' => 'recent']);

        $analyzer = new RouteAnalyzer($m);
        $warnings = $analyzer->analyze();

        $report = new RouteAnalysisReport($warnings);
        $jsonOutput = $report->formatJson();

        $this->assertIsString($jsonOutput);

        // Should be valid JSON
        $decoded = json_decode($jsonOutput, true);
        $this->assertIsArray($decoded);

        // Should have expected structure
        $this->assertArrayHasKey('warnings', $decoded);
        $this->assertArrayHasKey('summary', $decoded);

        // Warnings should have required fields
        if (!empty($decoded['warnings'])) {
            $firstWarning = $decoded['warnings'][0];
            $this->assertArrayHasKey('type', $firstWarning);
            $this->assertArrayHasKey('message', $firstWarning);
        }
    }

    // ============================================================
    // Performance Tests
    // ============================================================

    /**
     * Test performance with 100 routes
     *
     * Should complete analysis in under 1 second
     */
    public function testPerformanceWith100Routes(): void
    {
        $m = new Mapper();

        // Generate 100 unique routes
        for ($i = 0; $i < 100; $i++) {
            $m->connect("route{$i}/:id", [
                'controller' => "Controller{$i}",
                'action' => 'show'
            ]);
        }

        $this->assertCount(100, $m->matchList);

        $analyzer = new RouteAnalyzer($m);

        $startTime = microtime(true);
        $warnings = $analyzer->analyze();
        $duration = microtime(true) - $startTime;

        // Should complete in under 1 second for 100 clean routes
        $this->assertLessThan(1.0, $duration, 'Should analyze 100 routes in under 1 second');

        // Clean routes should have no warnings
        $this->assertEmpty($warnings);
    }
}
