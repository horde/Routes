<?php
/**
 * Tests for controller scanning utility
 *
 * The controllerScan() utility scans a directory for PHP controller files
 * and returns normalized controller names for route mapping.
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Routes
 */

namespace Horde\Routes\Test;

use PHPUnit\Framework\TestCase;
use Horde\Routes\Utils;

/**
 * @package Routes
 */
class ControllerScanTest extends TestCase
{
    private string $testDir;

    public function setUp(): void
    {
        // Create temporary test directory
        $this->testDir = sys_get_temp_dir() . '/routes_test_' . uniqid();
        mkdir($this->testDir, 0755, true);
    }

    public function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Test scanning directory with simple PHP files
     */
    public function testSimpleControllerScan(): void
    {
        // Create test files
        touch($this->testDir . '/foo.php');
        touch($this->testDir . '/bar.php');
        touch($this->testDir . '/baz.php');

        $controllers = Utils::controllerScan($this->testDir);

        $this->assertIsArray($controllers);
        $this->assertCount(3, $controllers);
        $this->assertContains('foo', $controllers);
        $this->assertContains('bar', $controllers);
        $this->assertContains('baz', $controllers);
    }

    /**
     * Test null directory returns empty array
     */
    public function testNullDirectoryReturnsEmpty(): void
    {
        $controllers = Utils::controllerScan(null);

        $this->assertIsArray($controllers);
        $this->assertEmpty($controllers);
    }

    /**
     * Test files starting with underscore are ignored
     */
    public function testUnderscoreFilesIgnored(): void
    {
        touch($this->testDir . '/public.php');
        touch($this->testDir . '/_private.php');
        touch($this->testDir . '/_helper.php');

        $controllers = Utils::controllerScan($this->testDir);

        $this->assertCount(1, $controllers);
        $this->assertContains('public', $controllers);
        $this->assertNotContains('_private', $controllers);
        $this->assertNotContains('_helper', $controllers);
    }

    /**
     * Test non-PHP files are ignored
     */
    public function testNonPhpFilesIgnored(): void
    {
        touch($this->testDir . '/controller.php');
        touch($this->testDir . '/readme.txt');
        touch($this->testDir . '/config.json');
        touch($this->testDir . '/style.css');

        $controllers = Utils::controllerScan($this->testDir);

        $this->assertCount(1, $controllers);
        $this->assertContains('controller', $controllers);
    }

    /**
     * Test subdirectories are scanned recursively
     */
    public function testRecursiveDirectoryScan(): void
    {
        mkdir($this->testDir . '/admin', 0755, true);
        mkdir($this->testDir . '/api', 0755, true);

        touch($this->testDir . '/home.php');
        touch($this->testDir . '/admin/users.php');
        touch($this->testDir . '/admin/posts.php');
        touch($this->testDir . '/api/v1.php');

        $controllers = Utils::controllerScan($this->testDir);

        $this->assertCount(4, $controllers);
        $this->assertContains('home', $controllers);
        $this->assertContains('admin/users', $controllers);
        $this->assertContains('admin/posts', $controllers);
        $this->assertContains('api/v1', $controllers);
    }

    /**
     * Test CamelCase filenames are converted to snake_case
     */
    public function testCamelCaseConversion(): void
    {
        touch($this->testDir . '/UserProfile.php');
        touch($this->testDir . '/BlogPost.php');
        touch($this->testDir . '/APIEndpoint.php');

        $controllers = Utils::controllerScan($this->testDir);

        $this->assertContains('user_profile', $controllers);
        $this->assertContains('blog_post', $controllers);
        // APIEndpoint converts to apiendpoint (no underscore between consecutive caps)
        $this->assertContains('apiendpoint', $controllers);
    }

    /**
     * Test _controller suffix is stripped
     */
    public function testControllerSuffixStripped(): void
    {
        touch($this->testDir . '/blog_controller.php');
        touch($this->testDir . '/user_controller.php');
        touch($this->testDir . '/admin.php'); // No suffix

        $controllers = Utils::controllerScan($this->testDir);

        $this->assertContains('blog', $controllers);
        $this->assertContains('user', $controllers);
        $this->assertContains('admin', $controllers);
        $this->assertNotContains('blog_controller', $controllers);
        $this->assertNotContains('user_controller', $controllers);
    }

    /**
     * Test prefix parameter adds prefix to controller names
     */
    public function testControllerPrefix(): void
    {
        touch($this->testDir . '/user.php');
        touch($this->testDir . '/post.php');

        $controllers = Utils::controllerScan($this->testDir, 'api_');

        $this->assertContains('api_user', $controllers);
        $this->assertContains('api_post', $controllers);
    }

    /**
     * Test controllers are sorted longest first
     */
    public function testControllersAreSortedLongestFirst(): void
    {
        mkdir($this->testDir . '/admin', 0755, true);

        touch($this->testDir . '/a.php');
        touch($this->testDir . '/longer_name.php');
        touch($this->testDir . '/admin/very_long_controller_name.php');

        $controllers = Utils::controllerScan($this->testDir);

        // Should be sorted by length (longest first)
        $this->assertEquals('admin/very_long_controller_name', $controllers[0]);
        $this->assertEquals('longer_name', $controllers[1]);
        $this->assertEquals('a', $controllers[2]);
    }

    /**
     * Test Windows-style directory separators are normalized
     */
    public function testDirectorySeparatorNormalization(): void
    {
        mkdir($this->testDir . '/sub/deep', 0755, true);
        touch($this->testDir . '/sub/deep/controller.php');

        $controllers = Utils::controllerScan($this->testDir);

        // Should use forward slashes regardless of OS
        $this->assertContains('sub/deep/controller', $controllers);
        $this->assertNotContains('sub\\deep\\controller', $controllers);
    }

    /**
     * Test combined transformations
     */
    public function testCombinedTransformations(): void
    {
        mkdir($this->testDir . '/admin', 0755, true);
        touch($this->testDir . '/admin/UserManagement_controller.php');

        $controllers = Utils::controllerScan($this->testDir, 'app_');

        // Should apply: CamelCase->snake_case, strip _controller, add prefix, subdirectory
        $this->assertContains('app_admin/user_management', $controllers);
    }

    /**
     * Test empty directory returns empty array
     */
    public function testEmptyDirectoryReturnsEmpty(): void
    {
        $controllers = Utils::controllerScan($this->testDir);

        $this->assertIsArray($controllers);
        $this->assertEmpty($controllers);
    }

    /**
     * Test deeply nested directories
     */
    public function testDeeplyNestedDirectories(): void
    {
        mkdir($this->testDir . '/a/b/c/d', 0755, true);
        touch($this->testDir . '/a/b/c/d/deep.php');

        $controllers = Utils::controllerScan($this->testDir);

        $this->assertContains('a/b/c/d/deep', $controllers);
    }

    /**
     * Test mixed case with numbers
     */
    public function testMixedCaseWithNumbers(): void
    {
        touch($this->testDir . '/Api2Controller.php');
        touch($this->testDir . '/OAuth2Provider.php');

        $controllers = Utils::controllerScan($this->testDir);

        // Api2Controller -> api2controller (consecutive caps, no underscore)
        $this->assertContains('api2controller', $controllers);
        // OAuth2Provider -> oauth2provider (consecutive caps, no underscore)
        $this->assertContains('oauth2provider', $controllers);
    }

    /**
     * Test real-world controller naming patterns
     */
    public function testRealWorldPatterns(): void
    {
        mkdir($this->testDir . '/controllers/admin', 0755, true);

        touch($this->testDir . '/controllers/HomeController.php');
        touch($this->testDir . '/controllers/BlogPostController.php');
        touch($this->testDir . '/controllers/admin/UserManagerController.php');
        touch($this->testDir . '/controllers/_BaseController.php'); // Should be ignored

        $controllers = Utils::controllerScan($this->testDir . '/controllers');

        $this->assertCount(3, $controllers);
        $this->assertContains('home', $controllers);
        $this->assertContains('blog_post', $controllers);
        $this->assertContains('admin/user_manager', $controllers);
        $this->assertNotContains('_base', $controllers);
    }
}
