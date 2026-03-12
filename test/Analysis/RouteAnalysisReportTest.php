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
use Horde\Routes\Analysis\RouteAnalysisReport;

/**
 * Tests for RouteAnalysisReport formatting
 *
 * Tests the various output formats (text, JSON) for route analysis reports.
 *
 * @package Routes
 */
class RouteAnalysisReportTest extends TestCase
{
    // ============================================================
    // Format Tests
    // ============================================================

    /**
     * Test formatting empty warnings array as text
     */
    public function testFormatTextEmpty(): void
    {
        $warnings = [];

        $report = new RouteAnalysisReport($warnings);
        $output = $report->formatText();

        $this->assertIsString($output);
        $this->assertNotEmpty($output);

        // Should indicate no issues found
        $this->assertStringContainsString('No issues', $output);
    }

    /**
     * Test formatting shadowed route warning as text
     */
    public function testFormatTextShadowedRoute(): void
    {
        $warnings = [
            [
                'type' => 'shadowed',
                'message' => 'Route is unreachable due to earlier route',
                'shadowed_route' => 'users/search',
                'shadowing_route' => 'users/:action',
                'test_url' => '/users/search',
                'matched_route' => 'users/:action',
                'severity' => 'error'
            ]
        ];

        $report = new RouteAnalysisReport($warnings);
        $output = $report->formatText();

        $this->assertIsString($output);

        // Should contain key information
        $this->assertStringContainsString('shadowed', $output);
        $this->assertStringContainsString('users/search', $output);
        $this->assertStringContainsString('users/:action', $output);

        // Should indicate severity
        $this->assertStringContainsString('error', $output);
    }

    /**
     * Test formatting invalid requirement warning as text
     */
    public function testFormatTextInvalidRequirement(): void
    {
        $warnings = [
            [
                'type' => 'invalid_requirement',
                'message' => 'Invalid regex pattern in requirement',
                'route' => 'posts/:id',
                'parameter' => 'id',
                'pattern' => '[0-9',
                'error' => 'Compilation failed: missing terminating ]',
                'severity' => 'error'
            ]
        ];

        $report = new RouteAnalysisReport($warnings);
        $output = $report->formatText();

        $this->assertIsString($output);

        // Should contain error details
        $this->assertStringContainsString('invalid', $output);
        $this->assertStringContainsString('posts/:id', $output);
        $this->assertStringContainsString('id', $output);
        $this->assertStringContainsString('[0-9', $output);
    }

    /**
     * Test formatting empty warnings array as JSON
     */
    public function testFormatJsonEmpty(): void
    {
        $warnings = [];

        $report = new RouteAnalysisReport($warnings);
        $output = $report->formatJson();

        $this->assertIsString($output);

        // Should be valid JSON
        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);

        // Should have expected structure
        $this->assertArrayHasKey('warnings', $decoded);
        $this->assertArrayHasKey('summary', $decoded);

        // Warnings should be empty
        $this->assertEmpty($decoded['warnings']);

        // Summary should show 0 issues
        $this->assertEquals(0, $decoded['summary']['total']);
    }

    /**
     * Test formatting warnings as JSON
     */
    public function testFormatJsonWithWarnings(): void
    {
        $warnings = [
            [
                'type' => 'shadowed',
                'message' => 'Route is unreachable',
                'shadowed_route' => 'users/search',
                'shadowing_route' => 'users/:action',
                'test_url' => '/users/search',
                'matched_route' => 'users/:action',
                'severity' => 'error'
            ],
            [
                'type' => 'duplicate',
                'message' => 'Duplicate route definition',
                'route' => 'posts/:id',
                'severity' => 'warning'
            ]
        ];

        $report = new RouteAnalysisReport($warnings);
        $output = $report->formatJson();

        $this->assertIsString($output);

        // Should be valid JSON
        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);

        // Should have all warnings
        $this->assertCount(2, $decoded['warnings']);

        // First warning should have expected fields
        $first = $decoded['warnings'][0];
        $this->assertEquals('shadowed', $first['type']);
        $this->assertEquals('users/search', $first['shadowed_route']);
        $this->assertEquals('error', $first['severity']);

        // Summary should count by severity
        $this->assertEquals(2, $decoded['summary']['total']);
        $this->assertArrayHasKey('by_severity', $decoded['summary']);
    }

    /**
     * Test warning serialization excludes Route objects
     *
     * Route objects should not be serialized to JSON (circular refs, too large)
     * Only use route paths and relevant string data.
     */
    public function testSerializeWarning(): void
    {
        $warnings = [
            [
                'type' => 'shadowed',
                'message' => 'Route is unreachable',
                'shadowed_route' => 'users/search',
                'shadowing_route' => 'users/:action',
                'severity' => 'error',
                // These should be excluded from serialization
                'route_object' => new \stdClass(),
                'internal_data' => ['debug' => 'info']
            ]
        ];

        $report = new RouteAnalysisReport($warnings);
        $output = $report->formatJson();

        $decoded = json_decode($output, true);

        // Should not contain internal/debug fields
        $first = $decoded['warnings'][0];
        $this->assertArrayNotHasKey('route_object', $first);
        $this->assertArrayNotHasKey('internal_data', $first);

        // Should contain public fields
        $this->assertArrayHasKey('type', $first);
        $this->assertArrayHasKey('message', $first);
    }
}
