<?php
/**
 * Horde Routes package
 *
 * @author  Ralf Lang <lang@b1-systems.de>
 * @license http://www.horde.org/licenses/bsd BSD
 * @package Routes
 */

namespace Horde\Routes\Test\Analysis;

use PHPUnit\Framework\TestCase;
use Horde\Routes\Mapper;
use Horde\Routes\Analysis\RouteAnalyzer;

/**
 * Unit tests for RouteAnalyzer
 *
 * RouteAnalyzer uses runtime verification to detect routing issues by
 * generating test URLs and verifying which route matches. This approach
 * avoids false positives from static regex analysis.
 *
 * @package Routes
 */
class RouteAnalyzerTest extends TestCase
{
    // ============================================================
    // Basic Analysis Tests
    // ============================================================

    /**
     * Test that clean configuration returns no warnings
     */
    public function testNoIssuesReturnsEmpty(): void
    {
        $m = new Mapper();
        $m->connect('users/:id', ['controller' => 'User', 'action' => 'show']);
        $m->connect('posts/:id', ['controller' => 'Post', 'action' => 'show']);

        $analyzer = new RouteAnalyzer($m);
        $warnings = $analyzer->analyze();

        $this->assertIsArray($warnings);
        $this->assertEmpty($warnings);
    }

    /**
     * Test RouteAnalyzer constructor accepts Mapper
     */
    public function testConstructorAcceptsMapper(): void
    {
        $m = new Mapper();
        $analyzer = new RouteAnalyzer($m);

        $this->assertInstanceOf(RouteAnalyzer::class, $analyzer);
    }

    /**
     * Test verbose mode flag
     */
    public function testVerboseModeFlag(): void
    {
        $m = new Mapper();

        // Non-verbose (default)
        $analyzer1 = new RouteAnalyzer($m);
        $this->assertInstanceOf(RouteAnalyzer::class, $analyzer1);

        // Explicit verbose mode
        $analyzer2 = new RouteAnalyzer($m, verbose: true);
        $this->assertInstanceOf(RouteAnalyzer::class, $analyzer2);
    }

    // ============================================================
    // Shadowing Detection Tests
    // ============================================================

    /**
     * Test static route shadowed by dynamic route
     *
     * Example: /users/search (static) shadowed by /users/:action
     */
    public function testStaticShadowedByDynamic(): void
    {
        $m = new Mapper();

        // Order matters: route defined first has priority
        $m->connect('users/:action', ['controller' => 'User']);
        $m->connect('users/search', ['controller' => 'Search', 'action' => 'users']);

        $analyzer = new RouteAnalyzer($m);
        $warnings = $analyzer->analyze();

        // Should detect that users/search is shadowed
        $this->assertNotEmpty($warnings);

        // Find the shadowing warning
        $shadowWarning = null;
        foreach ($warnings as $warning) {
            if ($warning['type'] === 'shadowed' &&
                str_contains($warning['shadowed_route'], 'users/search')) {
                $shadowWarning = $warning;
                break;
            }
        }

        $this->assertNotNull($shadowWarning, 'Should detect shadowed route');
        $this->assertStringContainsString('users/:action', $shadowWarning['shadowing_route']);
    }

    /**
     * Test dynamic route shadowed by broader dynamic route
     *
     * Example: /users/:id shadowed by /users/:action/:id
     */
    public function testDynamicShadowedByBroader(): void
    {
        $m = new Mapper();

        // Broader pattern first
        $m->connect('articles/:category/:slug', ['controller' => 'Article', 'action' => 'show']);
        // More specific pattern
        $m->connect('articles/:id', ['controller' => 'Article', 'action' => 'show_by_id', 'requirements' => ['id' => '\d+']]);

        $analyzer = new RouteAnalyzer($m);
        $warnings = $analyzer->analyze();

        // articles/123 will match first route (as "category/slug") instead of second
        $this->assertNotEmpty($warnings);

        $shadowWarning = null;
        foreach ($warnings as $warning) {
            if ($warning['type'] === 'shadowed' &&
                str_contains($warning['shadowed_route'], 'articles/:id')) {
                $shadowWarning = $warning;
                break;
            }
        }

        $this->assertNotNull($shadowWarning, 'Should detect shadowed route');
    }

    /**
     * Test different HTTP methods do NOT cause shadowing
     *
     * GET /users vs POST /users should both be reachable
     */
    public function testDifferentHttpMethodsNotShadowed(): void
    {
        $m = new Mapper();

        $m->connect('users', ['controller' => 'User', 'action' => 'index', 'conditions' => ['method' => ['GET']]]);
        $m->connect('users', ['controller' => 'User', 'action' => 'create', 'conditions' => ['method' => ['POST']]]);

        $analyzer = new RouteAnalyzer($m);
        $warnings = $analyzer->analyze();

        // Should NOT detect shadowing (different HTTP methods)
        $this->assertEmpty($warnings, 'Different HTTP methods should not shadow each other');
    }

    /**
     * Test same pattern with different subdomains
     *
     * api.example.com/users vs www.example.com/users
     */
    public function testSamePatternDifferentSubdomains(): void
    {
        $m = new Mapper();
        $m->subDomains = true;

        $m->connect('users', ['controller' => 'ApiUser', 'conditions' => ['subdomain' => 'api']]);
        $m->connect('users', ['controller' => 'WebUser', 'conditions' => ['subdomain' => 'www']]);

        $analyzer = new RouteAnalyzer($m);
        $warnings = $analyzer->analyze();

        // Should NOT detect shadowing (different subdomains)
        $this->assertEmpty($warnings, 'Different subdomains should not shadow each other');
    }

    /**
     * Test requirements differentiate routes
     *
     * /posts/:id with id=\d+ vs /posts/:slug with slug=[a-z-]+
     * These should NOT shadow if requirements are mutually exclusive
     */
    public function testRequirementsDifferentiate(): void
    {
        $m = new Mapper();

        // Numeric ID route
        $m->connect('posts/:id', [
            'controller' => 'Post',
            'action' => 'show',
            'requirements' => ['id' => '\d+']
        ]);

        // Slug route
        $m->connect('posts/:slug', [
            'controller' => 'Post',
            'action' => 'show_by_slug',
            'requirements' => ['slug' => '[a-z][a-z0-9-]*']
        ]);

        $analyzer = new RouteAnalyzer($m);
        $warnings = $analyzer->analyze();

        // With runtime verification, analyzer tests both numeric and alpha URLs
        // If requirements work correctly, both routes should be reachable
        $this->assertEmpty($warnings, 'Mutually exclusive requirements should not cause shadowing');
    }

    /**
     * Test one route shadows multiple later routes
     */
    public function testMultipleShadowedRoutes(): void
    {
        $m = new Mapper();

        // Very broad catch-all route
        $m->connect(':controller/:action/:id');

        // These more specific routes will all be shadowed
        $m->connect('users/search', ['controller' => 'Search', 'action' => 'users']);
        $m->connect('posts/recent', ['controller' => 'Post', 'action' => 'recent']);
        $m->connect('admin/dashboard', ['controller' => 'Admin', 'action' => 'dashboard']);

        $analyzer = new RouteAnalyzer($m);
        $warnings = $analyzer->analyze();

        // Should detect all 3 shadowed routes
        $this->assertCount(3, $warnings);

        foreach ($warnings as $warning) {
            $this->assertEquals('shadowed', $warning['type']);
        }
    }

    // ============================================================
    // Test URL Generation Tests
    // ============================================================

    /**
     * Test analyzer generates URLs from simple placeholders
     *
     * :id should generate something like "123"
     * :slug should generate something like "test-slug"
     */
    public function testGenerateSimplePlaceholders(): void
    {
        $m = new Mapper();
        $m->connect('posts/:id/comments/:comment_id', ['controller' => 'Comment', 'action' => 'show']);

        $analyzer = new RouteAnalyzer($m);
        $testUrls = $analyzer->getTestUrls();

        // Should have generated test URLs for this route
        $this->assertNotEmpty($testUrls);

        // Test URLs should contain numeric values for :id placeholders
        $found = false;
        foreach ($testUrls as $url) {
            if (preg_match('#posts/\d+/comments/\d+#', $url)) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Should generate test URLs with numeric values for :id placeholders');
    }

    /**
     * Test analyzer generates URLs from regex requirements
     *
     * \d+ should generate numeric values
     * [a-z]+ should generate alphabetic strings
     */
    public function testGenerateFromRegex(): void
    {
        $m = new Mapper();
        $m->connect('posts/:year/:month/:day', [
            'controller' => 'Post',
            'action' => 'by_date',
            'requirements' => [
                'year' => '\d{4}',
                'month' => '\d{2}',
                'day' => '\d{2}'
            ]
        ]);

        $analyzer = new RouteAnalyzer($m);
        $testUrls = $analyzer->getTestUrls();

        $this->assertNotEmpty($testUrls);

        // Should generate URLs matching the pattern
        $found = false;
        foreach ($testUrls as $url) {
            if (preg_match('#posts/\d{4}/\d{2}/\d{2}#', $url)) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Should generate test URLs matching regex requirements');
    }

    /**
     * Test analyzer generates multiple test URLs per route
     *
     * To thoroughly test for shadowing, should generate 2-3 variants
     */
    public function testMultipleTestUrlsPerRoute(): void
    {
        $m = new Mapper();
        $m->connect('posts/:id', ['controller' => 'Post', 'action' => 'show']);

        $analyzer = new RouteAnalyzer($m);
        $testUrls = $analyzer->getTestUrls();

        // Should generate multiple test URLs (at least 2)
        $postUrls = array_filter($testUrls, fn($url) => str_contains($url, 'posts/'));
        $this->assertGreaterThanOrEqual(2, count($postUrls), 'Should generate multiple test URLs per route');
    }

    // ============================================================
    // Other Detection Tests
    // ============================================================

    /**
     * Test detection of invalid regex in requirements
     */
    public function testInvalidRegexDetected(): void
    {
        $m = new Mapper();

        // Invalid regex: unclosed bracket
        $m->connect('posts/:id', [
            'controller' => 'Post',
            'action' => 'show',
            'requirements' => ['id' => '[0-9']  // Invalid: unclosed [
        ]);

        $analyzer = new RouteAnalyzer($m);
        $warnings = $analyzer->analyze();

        // Should detect invalid regex
        $this->assertNotEmpty($warnings);

        $invalidRegex = null;
        foreach ($warnings as $warning) {
            if ($warning['type'] === 'invalid_requirement') {
                $invalidRegex = $warning;
                break;
            }
        }

        $this->assertNotNull($invalidRegex, 'Should detect invalid regex in requirements');
        $this->assertStringContainsString('id', $invalidRegex['message']);
    }

    /**
     * Test detection of duplicate routes
     *
     * Two routes with exact same path and conditions
     */
    public function testDuplicateRoutesDetected(): void
    {
        $m = new Mapper();

        // Exact duplicates
        $m->connect('users/:id', ['controller' => 'User', 'action' => 'show']);
        $m->connect('users/:id', ['controller' => 'User', 'action' => 'show']);

        $analyzer = new RouteAnalyzer($m);
        $warnings = $analyzer->analyze();

        // Should detect duplicate
        $this->assertNotEmpty($warnings);

        $duplicate = null;
        foreach ($warnings as $warning) {
            if ($warning['type'] === 'duplicate') {
                $duplicate = $warning;
                break;
            }
        }

        $this->assertNotNull($duplicate, 'Should detect duplicate routes');
    }

    /**
     * Test empty mapper returns no warnings
     */
    public function testEmptyMapperReturnsNoWarnings(): void
    {
        $m = new Mapper();

        $analyzer = new RouteAnalyzer($m);
        $warnings = $analyzer->analyze();

        $this->assertIsArray($warnings);
        $this->assertEmpty($warnings);
    }
}
