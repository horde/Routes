<?php
/**
 * Horde Routes package
 *
 * @author  Ralf Lang <ralf.lang@ralf-lang.de>
 * @license http://www.horde.org/licenses/bsd BSD
 * @package Routes
 */

declare(strict_types=1);

namespace Horde\Routes\Analysis;

use Horde\Routes\Mapper;
use Horde\Routes\Route;

/**
 * Runtime route analyzer for detecting configuration issues
 *
 * Analyzes route configurations by generating test URLs and verifying which
 * routes match. This runtime verification approach avoids false positives
 * from static regex analysis.
 *
 * DESIGN PHILOSOPHY:
 *
 * This analyzer follows a "progressive enhancement" philosophy:
 * - It will find REAL problems when they exist
 * - It will NEVER report false positives (problems that don't exist)
 * - It may not find EVERY problem (some edge cases may be missed)
 * - This is intentional and acceptable
 *
 * The analyzer may remain incomplete forever, and that's OK. Better to miss
 * some issues than to waste developer time investigating non-existent problems.
 * When in doubt, the analyzer stays silent rather than guessing.
 *
 * Runtime verification ensures reported issues are actual routing problems,
 * not theoretical possibilities from regex pattern analysis.
 *
 * Detects:
 * - Shadowed/unreachable routes (later route never matches)
 * - Invalid regex patterns in requirements
 * - Duplicate route definitions
 *
 * May miss:
 * - Complex subdomain interactions
 * - Custom function conditions
 * - Some edge cases with requirement patterns
 *
 * Usage:
 * <code>
 * $mapper = new Mapper();
 * // ... configure routes ...
 *
 * $analyzer = new RouteAnalyzer($mapper);
 * $warnings = $analyzer->analyze();
 *
 * if (!empty($warnings)) {
 *     $report = new RouteAnalysisReport($warnings);
 *     echo $report->formatText();
 * }
 * </code>
 *
 * @package Routes
 */
class RouteAnalyzer
{
    /**
     * The Mapper to analyze
     */
    private Mapper $mapper;

    /**
     * Verbose output mode
     */
    private bool $verbose;

    /**
     * Generated test URLs for debugging
     *
     * @var array<string>
     */
    private array $testUrls = [];

    /**
     * Create a new route analyzer
     *
     * @param Mapper $mapper The mapper to analyze
     * @param bool $verbose Enable verbose output
     */
    public function __construct(Mapper $mapper, bool $verbose = false)
    {
        $this->mapper = $mapper;
        $this->verbose = $verbose;
    }

    /**
     * Analyze routes and return warnings
     *
     * @return array<array<string, mixed>> Array of warning objects
     */
    public function analyze(): array
    {
        $warnings = [];

        // Ensure routes have regexp generated (needed for matching)
        $this->ensureRouteRegexps();

        // Check for invalid regex patterns first
        $warnings = array_merge($warnings, $this->checkInvalidRequirements());

        // Check for exact duplicates
        $warnings = array_merge($warnings, $this->checkDuplicates());

        // Check for shadowing using runtime verification
        $warnings = array_merge($warnings, $this->checkShadowing());

        return $warnings;
    }

    /**
     * Ensure all routes have their regexps generated
     *
     * @return void
     */
    private function ensureRouteRegexps(): void
    {
        // Get controller list (or use empty array if no scan function)
        $clist = [];
        if ($this->mapper->controllerScan !== null) {
            if ($this->mapper->directory === null) {
                $clist = call_user_func($this->mapper->controllerScan);
            } else {
                $clist = call_user_func($this->mapper->controllerScan, $this->mapper->directory);
            }
        }

        // Generate regexp for all routes
        foreach ($this->mapper->matchList as $route) {
            if (!$route->static) {
                $route->makeRegexp($clist);
            }
        }
    }

    /**
     * Get generated test URLs (for debugging)
     *
     * Generates test URLs if not already generated.
     *
     * @return array<string> Array of test URLs
     */
    public function getTestUrls(): array
    {
        // Generate test URLs if not already done
        if (empty($this->testUrls)) {
            $this->generateAllTestUrls();
        }

        return $this->testUrls;
    }

    /**
     * Generate all test URLs for all routes
     *
     * @return void
     */
    private function generateAllTestUrls(): void
    {
        foreach ($this->mapper->matchList as $index => $route) {
            if ($route->static) {
                continue;
            }

            $tests = $this->generateTestsForRoute($route);
            foreach ($tests as $test) {
                $this->testUrls[] = $test['url'];
            }
        }
    }

    /**
     * Check for invalid regex patterns in requirements
     *
     * @return array<array<string, mixed>> Array of warnings
     */
    private function checkInvalidRequirements(): array
    {
        $warnings = [];

        foreach ($this->mapper->matchList as $route) {
            if (empty($route->reqs)) {
                continue;
            }

            foreach ($route->reqs as $param => $pattern) {
                // Test if regex is valid
                $testPattern = '/' . $pattern . '/';
                $result = @preg_match($testPattern, '');

                if ($result === false) {
                    $error = error_get_last();
                    $warnings[] = [
                        'type' => 'invalid_requirement',
                        'message' => 'Invalid regex pattern in requirement',
                        'route' => $route->routePath,
                        'parameter' => $param,
                        'pattern' => $pattern,
                        'error' => $error['message'] ?? 'Unknown error',
                        'severity' => 'error'
                    ];
                }
            }
        }

        return $warnings;
    }

    /**
     * Check for duplicate route definitions
     *
     * @return array<array<string, mixed>> Array of warnings
     */
    private function checkDuplicates(): array
    {
        $warnings = [];
        $seen = [];

        foreach ($this->mapper->matchList as $route) {
            // Create signature from path, conditions, and requirements
            $signature = $this->getRouteSignature($route);

            if (isset($seen[$signature])) {
                $warnings[] = [
                    'type' => 'duplicate',
                    'message' => 'Duplicate route definition',
                    'route' => $route->routePath,
                    'severity' => 'warning'
                ];
            } else {
                $seen[$signature] = true;
            }
        }

        return $warnings;
    }

    /**
     * Get unique signature for a route
     *
     * @param Route $route Route to get signature for
     * @return string Unique signature
     */
    private function getRouteSignature(Route $route): string
    {
        $parts = [
            'path' => $route->routePath,
            'conditions' => $route->conditions ?? [],
            'requirements' => $route->reqs ?? []
        ];

        return md5(serialize($parts));
    }

    /**
     * Check for shadowing using runtime verification
     *
     * @return array<array<string, mixed>> Array of warnings
     */
    private function checkShadowing(): array
    {
        $warnings = [];

        // Generate test URLs for each route
        $routeTests = $this->generateRouteTests();

        // For each route, test its URLs to see if it's reachable
        foreach ($routeTests as $routeIndex => $tests) {
            $route = $this->mapper->matchList[$routeIndex];

            foreach ($tests as $test) {
                $testUrl = $test['url'];
                $environ = $test['environ'];

                // Add to test URLs collection
                if (!in_array($testUrl, $this->testUrls)) {
                    $this->testUrls[] = $testUrl;
                }

                // Save original environ
                $originalEnviron = $this->mapper->environ;

                // Set test environ
                $this->mapper->environ = $environ;

                // Test which route matches
                $matchedRoute = $this->findMatchingRoute($testUrl);

                // Restore environ
                $this->mapper->environ = $originalEnviron;

                // If a different route matched, this route is shadowed
                if ($matchedRoute !== null && $matchedRoute !== $routeIndex) {
                    $shadowingRoute = $this->mapper->matchList[$matchedRoute];

                    $warnings[] = [
                        'type' => 'shadowed',
                        'message' => 'Route is unreachable due to earlier route',
                        'shadowed_route' => $route->routePath,
                        'shadowing_route' => $shadowingRoute->routePath,
                        'test_url' => $testUrl,
                        'matched_route' => $shadowingRoute->routePath,
                        'severity' => 'error'
                    ];

                    // Only report once per route
                    break;
                }
            }
        }

        return $warnings;
    }

    /**
     * Generate test cases for all routes
     *
     * @return array<int, array<array<string, mixed>>> Array indexed by route index
     */
    private function generateRouteTests(): array
    {
        $tests = [];

        foreach ($this->mapper->matchList as $index => $route) {
            if ($route->static) {
                continue; // Skip static routes
            }

            $tests[$index] = $this->generateTestsForRoute($route);
        }

        return $tests;
    }

    /**
     * Generate test cases for a single route
     *
     * @param Route $route Route to generate tests for
     * @return array<array<string, mixed>> Array of test cases
     */
    private function generateTestsForRoute(Route $route): array
    {
        $tests = [];

        // Generate multiple test URLs with different value types
        $variants = [
            ['numeric' => true],
            ['alpha' => true],
            ['mixed' => true]
        ];

        foreach ($variants as $variant) {
            $url = $this->generateTestUrl($route, $variant);
            $environ = $this->generateTestEnviron($route);

            $tests[] = [
                'url' => $url,
                'environ' => $environ
            ];
        }

        return $tests;
    }

    /**
     * Generate a test URL for a route
     *
     * @param Route $route Route to generate URL for
     * @param array<string, bool> $options Generation options
     * @return string Generated URL
     */
    private function generateTestUrl(Route $route, array $options = []): string
    {
        $path = $route->routePath;

        // Replace placeholders with test values
        $path = preg_replace_callback('/:([a-zA-Z_][a-zA-Z0-9_]*)/', function($matches) use ($route, $options) {
            $param = $matches[1];

            // Check if there's a requirement for this parameter
            if (isset($route->reqs[$param])) {
                return $this->generateValueFromRequirement($route->reqs[$param], $options);
            }

            // Generate default test value based on parameter name
            return $this->generateDefaultValue($param, $options);
        }, $path);

        // Ensure leading slash
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return $path;
    }

    /**
     * Generate a value matching a requirement pattern
     *
     * @param string $pattern Regex pattern
     * @param array<string, bool> $options Generation options
     * @return string Generated value
     */
    private function generateValueFromRequirement(string $pattern, array $options): string
    {
        // Handle common patterns
        if ($pattern === '\d+') {
            return '123';
        }

        if (preg_match('/^\\\d\{(\d+)\}$/', $pattern, $matches)) {
            $length = (int)$matches[1];
            return str_repeat('1', $length);
        }

        if (preg_match('/^\\\d\{(\d+),(\d+)\}$/', $pattern, $matches)) {
            $minLength = (int)$matches[1];
            return str_repeat('1', $minLength);
        }

        if ($pattern === '[a-z]+' || $pattern === '[a-zA-Z]+') {
            return isset($options['numeric']) ? 'abc' : 'test';
        }

        if (preg_match('/^\[a-z\]\[a-z0-9-\]\*$/', $pattern)) {
            return 'test-slug';
        }

        // Default numeric value
        return '123';
    }

    /**
     * Generate a default value for a parameter
     *
     * @param string $param Parameter name
     * @param array<string, bool> $options Generation options
     * @return string Generated value
     */
    private function generateDefaultValue(string $param, array $options): string
    {
        // Parameter name-based heuristics
        if (in_array($param, ['id', 'user_id', 'post_id', 'comment_id'])) {
            return isset($options['numeric']) ? '123' : (isset($options['alpha']) ? '456' : '789');
        }

        if (in_array($param, ['slug', 'name', 'title'])) {
            return isset($options['numeric']) ? 'test-slug' : (isset($options['alpha']) ? 'another-slug' : 'third-slug');
        }

        if ($param === 'action') {
            return isset($options['numeric']) ? 'show' : (isset($options['alpha']) ? 'edit' : 'delete');
        }

        if ($param === 'controller') {
            return 'Test';
        }

        if ($param === 'year') {
            return '2024';
        }

        if ($param === 'month') {
            return '12';
        }

        if ($param === 'day') {
            return '25';
        }

        // Default value
        return isset($options['numeric']) ? '123' : (isset($options['alpha']) ? 'test' : 'value');
    }

    /**
     * Generate test environment for a route
     *
     * @param Route $route Route to generate environ for
     * @return array<string, mixed> Test environment
     */
    private function generateTestEnviron(Route $route): array
    {
        $environ = [];

        // Set HTTP method from conditions
        if (isset($route->conditions['method'])) {
            $methods = $route->conditions['method'];
            $environ['REQUEST_METHOD'] = is_array($methods) ? $methods[0] : $methods;
        } else {
            $environ['REQUEST_METHOD'] = 'GET';
        }

        // Set subdomain from conditions
        if (isset($route->conditions['subdomain'])) {
            $environ['HTTP_HOST'] = $route->conditions['subdomain'] . '.example.com';
        }

        return $environ;
    }

    /**
     * Find which route matches a URL
     *
     * Tests each route in order to find the first match.
     *
     * @param string $url URL to test
     * @return int|null Index of matching route, or null if no match
     */
    private function findMatchingRoute(string $url): ?int
    {
        // Test each route in order (mimicking Mapper's behavior)
        foreach ($this->mapper->matchList as $index => $route) {
            if ($route->static) {
                continue;
            }

            // Test if this route matches the URL
            $match = $route->match($url, [
                'environ' => $this->mapper->environ,
                'subDomains' => $this->mapper->subDomains,
                'subDomainsIgnore' => $this->mapper->subDomainsIgnore,
                'domainMatch' => $this->mapper->domainMatch ?? ''
            ]);

            if ($match !== null) {
                return $index;
            }
        }

        return null;
    }
}
