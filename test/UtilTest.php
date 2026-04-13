<?php

/**
 * Horde Routes package
 *
 * This package is heavily inspired by the Python "Routes" library
 * by Ben Bangert (http://routes.groovie.org).  Routes is based
 * largely on ideas from Ruby on Rails (http://www.rubyonrails.org).
 *
 * @author  Maintainable Software, LLC. (http://www.maintainable.com)
 * @author  Mike Naberezny <mike@maintainable.com>
 * @license http://www.horde.org/licenses/bsd BSD
 * @package Routes
 */

namespace Horde\Routes\Test;

use PHPUnit\Framework\TestCase;
use Horde\Routes\Mapper;
use Horde\Routes\Utils;

require_once __DIR__ . '/TestHelper.php';

/**
 * @package Routes
 * @coversNothing
 */
class UtilTest extends TestCase
{
    protected $mapper;
    protected $utils;
    protected $redirectToResult;

    public function setUp(): void
    {
        $m = new Mapper();
        $m->environ = ['HTTP_HOST' => 'www.test.com'];

        $m->connect(
            'archive/:year/:month/:day',
            ['controller' => 'blog',
                'action' => 'view',
                'month' => null,
                'day' => null,
                'requirements' => ['month' => '\d{1,2}', 'day' => '\d{1,2}']]
        );
        $m->connect('viewpost/:id', ['controller' => 'post', 'action' => 'view']);
        $m->connect(':controller/:action/:id');

        $this->mapper = $m;
        $this->utils = $m->utils;
    }

    public function testUrlForSelf()
    {
        $utils = $this->utils;
        $utils->mapperDict = [];

        $this->assertEquals('/blog', $utils->urlFor(['controller' => 'blog']));
        $this->assertEquals('/content', $utils->urlFor());
        $this->assertEquals('https://www.test.com/viewpost', $utils->urlFor(['controller' => 'post', 'action' => 'view', 'protocol' => 'https']));
        $this->assertEquals('http://www.test.org/content/view/2', $utils->urlFor(['host' => 'www.test.org', 'controller' => 'content', 'action' => 'view', 'id' => 2]));
    }

    public function testUrlForWithDefaults()
    {
        $utils = $this->utils;
        $utils->mapperDict = ['controller' => 'blog', 'action' => 'view', 'id' => 4];

        $this->assertEquals('/blog/view/4', $utils->urlFor());
        $this->assertEquals('/post/index/4', $utils->urlFor(['controller' => 'post']));
        $this->assertEquals('/blog/view/2', $utils->urlFor(['id' => 2]));
        $this->assertEquals('/viewpost/4', $utils->urlFor(['controller' => 'post', 'action' => 'view', 'id' => 4]));

        $utils->mapperDict = ['controller' => 'blog', 'action' => 'view', 'year' => 2004];
        $this->assertEquals('/archive/2004/10', $utils->urlFor(['month' => 10]));
        $this->assertEquals('/archive/2004/9/2', $utils->urlFor(['month' => 9, 'day' => 2]));
        $this->assertEquals('/blog', $utils->urlFor(['controller' => 'blog', 'year' => null]));
    }

    public function testUrlForWithMoreDefaults()
    {
        $utils = $this->utils;
        $utils->mapperDict = ['controller' => 'blog', 'action' => 'view', 'id' => 4];
        $this->assertEquals('/blog/view/4', $utils->urlFor());
        $this->assertEquals('/post/index/4', $utils->urlFor(['controller' => 'post']));
        $this->assertEquals('/viewpost/4', $utils->urlfor(['controller' => 'post', 'action' => 'view', 'id' => 4]));

        $utils->mapperDict = ['controller' => 'blog', 'action' => 'view', 'year' => 2004];
        $this->assertEquals('/archive/2004/10', $utils->urlFor(['month' => 10]));
        $this->assertEquals('/archive/2004/9/2', $utils->urlFor(['month' => 9, 'day' => 2]));
        $this->assertEquals('/blog', $utils->urlFor(['controller' => 'blog', 'year' => null]));
        $this->assertEquals('/archive/2004', $utils->urlFor());
    }

    public function testUrlForWithDefaultsAndQualified()
    {
        $m = $this->mapper;
        $utils = $m->utils;

        $m->connect('home', '', ['controller' => 'blog', 'action' => 'splash']);
        $m->connect('category_home', 'category/:section', ['controller' => 'blog', 'action' => 'view', 'section' => 'home']);
        $m->connect(':controller/:action/:id');
        $m->createRegs(['content', 'blog', 'admin/comments']);

        $environ = ['SCRIPT_NAME' => '', 'HTTP_HOST' => 'www.example.com',
            'PATH_INFO' => '/blog/view/4'];
        TestHelper::updateMapper($m, $environ);

        $this->assertEquals('/blog/view/4', $utils->urlFor());
        $this->assertEquals('/post/index/4', $utils->urlFor(['controller' => 'post']));
        $this->assertEquals('http://www.example.com/blog/view/4', $utils->urlFor(['qualified' => true]));
        $this->assertEquals('/blog/view/2', $utils->urlFor(['id' => 2]));
        $this->assertEquals('/viewpost/4', $utils->urlFor(['controller' => 'post', 'action' => 'view', 'id' => 4]));

        $environ = ['SCRIPT_NAME' => '', 'HTTP_HOST' => 'www.example.com:8080', 'PATH_INFO' => '/blog/view/4'];
        TestHelper::updateMapper($m, $environ);

        $this->assertEquals(
            '/post/index/4',
            $utils->urlFor(['controller' => 'post'])
        );
        $this->assertEquals(
            'http://www.example.com:8080/blog/view/4',
            $utils->urlFor(['qualified' => true])
        );
    }

    public function testWithRouteNames()
    {
        $m = $this->mapper;

        $utils = $this->utils;
        $utils->mapperDict = [];

        $m->connect('home', '', ['controller' => 'blog', 'action' => 'splash']);
        $m->connect(
            'category_home',
            'category/:section',
            ['controller' => 'blog', 'action' => 'view', 'section' => 'home']
        );
        $m->createRegs(['content', 'blog', 'admin/comments']);

        $this->assertEquals(
            '/content/view',
            $utils->urlFor(['controller' => 'content', 'action' => 'view'])
        );
        $this->assertEquals(
            '/content',
            $utils->urlFor(['controller' => 'content'])
        );
        $this->assertEquals(
            '/admin/comments',
            $utils->urlFor(['controller' => 'admin/comments'])
        );
        $this->assertEquals(
            '/category',
            $utils->urlFor('category_home')
        );
        $this->assertEquals(
            '/category/food',
            $utils->urlFor('category_home', ['section' => 'food'])
        );
        $this->assertNull($utils->urlFor('home', ['action' => 'view', 'section' => 'home']));
        $this->assertNull($utils->urlFor('home', ['controller' => 'content']));
        $this->assertEquals(
            '/',
            $utils->urlFor('/')
        );
    }

    public function testWithRouteNamesAndDefaults()
    {
        $m = $this->mapper;

        $utils = $m->utils;
        $utils->mapperDict = [];

        $m->connect('home', '', ['controller' => 'blog', 'action' => 'splash']);
        $m->connect('category_home', 'category/:section', ['controller' => 'blog', 'action' => 'view', 'section' => 'home']);
        $m->connect('building', 'building/:campus/:building/alljacks', ['controller' => 'building', 'action' => 'showjacks']);
        $m->createRegs(['content', 'blog', 'admin/comments', 'building']);

        $utils->mapperDict = ['controller' => 'building', 'action' => 'showjacks', 'campus' => 'wilma', 'building' => 'port'];
        $this->assertEquals('/building/wilma/port/alljacks', $utils->urlFor());
        $this->assertEquals('/', $utils->urlFor('home'));
    }

    // callback used by testRedirectTo
    // Python version is inlined in test_redirect_to
    public function printer($echo)
    {
        $this->redirectToResult = $echo;
    }

    public function testRedirectTo()
    {
        $m = $this->mapper;
        $m->environ = ['SCRIPT_NAME' => '', 'HTTP_HOST' => 'www.example.com'];

        $utils = $m->utils;
        $utils->mapperDict = [];

        $callback = [$this, 'printer'];
        $utils->redirect = $callback;

        $m->createRegs(['content', 'blog', 'admin/comments']);

        $this->redirectToResult = null;
        $utils->redirectTo(['controller' => 'content', 'action' => 'view']);
        $this->assertEquals('/content/view', $this->redirectToResult);

        $this->redirectToResult = null;
        $utils->redirectTo(['controller' => 'content', 'action' => 'lookup', 'id' => 4]);
        $this->assertEquals('/content/lookup/4', $this->redirectToResult);

        $this->redirectToResult = null;
        $utils->redirectTo(['controller' => 'admin/comments', 'action' => 'splash']);
        $this->assertEquals('/admin/comments/splash', $this->redirectToResult);

        $this->redirectToResult = null;
        $utils->redirectTo('http://www.example.com/');
        $this->assertEquals('http://www.example.com/', $this->redirectToResult);

        $this->redirectToResult = null;
        $utils->redirectTo('/somewhere.html', ['var' => 'keyword']);
        $this->assertEquals('/somewhere.html?var=keyword', $this->redirectToResult);
    }

    public function testStaticRoute()
    {
        $m = $this->mapper;

        $utils = $m->utils;
        $utils->mapperDict = [];

        $environ = ['SCRIPT_NAME' => '', 'HTTP_HOST' => 'example.com'];
        TestHelper::updateMapper($m, $environ);

        $m->connect(':controller/:action/:id');
        $m->connect('home', 'http://www.groovie.org/', ['_static' => true]);
        $m->connect('space', '/nasa/images', ['_static' => true]);
        $m->createRegs(['content', 'blog']);

        $this->assertEquals(
            'http://www.groovie.org/',
            $utils->urlFor('home')
        );
        $this->assertEquals(
            'http://www.groovie.org/?s=stars',
            $utils->urlFor('home', ['s' => 'stars'])
        );
        $this->assertEquals(
            '/content/view',
            $utils->urlFor(['controller' => 'content', 'action' => 'view'])
        );
        $this->assertEquals(
            '/nasa/images?search=all',
            $utils->urlFor('space', ['search' => 'all'])
        );
    }

    public function testStaticRouteWithScript()
    {
        $m = $this->mapper;
        $m->environ = ['SCRIPT_NAME' => '/webapp', 'HTTP_HOST' => 'example.com'];

        $utils = $m->utils;
        $utils->mapperDict = [];


        $m->connect(':controller/:action/:id');
        $m->connect('home', 'http://www.groovie.org/', ['_static' => true]);
        $m->connect('space', '/nasa/images', ['_static' => true]);
        $m->createRegs(['content', 'blog']);

        $this->assertEquals(
            'http://www.groovie.org/',
            $utils->urlFor('home')
        );
        $this->assertEquals(
            'http://www.groovie.org/?s=stars',
            $utils->urlFor('home', ['s' => 'stars'])
        );
        $this->assertEquals(
            '/webapp/content/view',
            $utils->urlFor(['controller' => 'content', 'action' => 'view'])
        );
        $this->assertEquals(
            '/webapp/nasa/images?search=all',
            $utils->urlFor('space', ['search' => 'all'])
        );
        $this->assertEquals(
            'http://example.com/webapp/nasa/images',
            $utils->urlFor('space', ['protocol' => 'http'])
        );
    }

    public function testNoNamedPath()
    {
        $m = $this->mapper;
        $m->environ = ['SCRIPT_NAME' => '', 'HTTP_HOST' => 'example.com'];

        $utils = $m->utils;
        $utils->mapperDict = [];

        $m->connect(':controller/:action/:id');
        $m->connect('home', 'http://www.groovie.org', ['_static' => true]);
        $m->connect('space', '/nasa/images', ['_static' => true]);
        $m->createRegs(['content', 'blog']);

        $this->assertEquals(
            'http://www.google.com/search',
            $utils->urlFor('http://www.google.com/search')
        );
        $this->assertEquals(
            'http://www.google.com/search?q=routes',
            $utils->urlFor('http://www.google.com/search', ['q' => 'routes'])
        );
        $this->assertEquals(
            '/delicious.jpg',
            $utils->urlFor('/delicious.jpg')
        );
        $this->assertEquals(
            '/delicious/search?v=routes',
            $utils->urlFor('/delicious/search', ['v' => 'routes'])
        );
    }

    public function testAppendSlash()
    {
        $m = $this->mapper;
        $m->environ = ['SCRIPT_NAME' => '', 'HTTP_HOST' => 'example.com'];
        $m->appendSlash = true;

        $utils = $m->utils;
        $utils->mapperDict = [];

        $m->connect(':controller/:action/:id');
        $m->connect('home', 'http://www.groovie.org/', ['_static' => true]);
        $m->connect('space', '/nasa/images', ['_static' => true]);
        $m->createRegs(['content', 'blog']);

        $this->assertEquals(
            'http://www.google.com/search',
            $utils->urlFor('http://www.google.com/search')
        );
        $this->assertEquals(
            'http://www.google.com/search?q=routes',
            $utils->urlFor('http://www.google.com/search', ['q' => 'routes'])
        );
        $this->assertEquals(
            '/delicious.jpg',
            $utils->urlFor('/delicious.jpg')
        );
        $this->assertEquals(
            '/delicious/search?v=routes',
            $utils->urlFor('/delicious/search', ['v' => 'routes'])
        );
        $this->assertEquals(
            '/content/list/',
            $utils->urlFor(['controller' => '/content', 'action' => 'list'])
        );
        $this->assertEquals(
            '/content/list/?page=1',
            $utils->urlFor(['controller' => '/content', 'action' => 'list', 'page' => '1'])
        );
    }

    public function testNoNamedPathWithScript()
    {
        $m = $this->mapper;
        $m->environ = ['SCRIPT_NAME' => '/webapp', 'HTTP_HOST' => 'example.com'];

        $utils = $m->utils;
        $utils->mapperDict = [];

        $m->connect(':controller/:action/:id');
        $m->connect('home', 'http://www.groovie.org/', ['_static' => true]);
        $m->connect('space', '/nasa/images', ['_static' => true]);
        $m->createRegs(['content', 'blog']);

        $this->assertEquals(
            'http://www.google.com/search',
            $utils->urlFor('http://www.google.com/search')
        );
        $this->assertEquals(
            'http://www.google.com/search?q=routes',
            $utils->urlFor('http://www.google.com/search', ['q' => 'routes'])
        );
        $this->assertEquals(
            '/webapp/delicious.jpg',
            $utils->urlFor('/delicious.jpg')
        );
        $this->assertEquals(
            '/webapp/delicious/search?v=routes',
            $utils->urlFor('/delicious/search', ['v' => 'routes'])
        );
    }

    // callback used by testRouteFilter
    // Python version is inlined in test_route_filter
    public function articleFilter($kargs)
    {
        $article = $kargs['article'] ?? null;
        unset($kargs['article']);

        if ($article !== null) {
            $kargs['year']  = $article['year'] ?? 2004;
            $kargs['month'] = $article['month'] ?? 12;
            $kargs['day']   = $article['day'] ?? 20;
            $kargs['slug']  = $article['slug'] ?? 'default';
        }

        return $kargs;
    }

    public function testRouteFilter()
    {
        $m = new Mapper();
        $m->environ = ['SCRIPT_NAME' => '', 'HTTP_HOST' => 'example.com'];

        $utils = $m->utils;
        $utils->mapperDict = [];

        $callback = [$this, 'articleFilter'];

        $m->connect(':controller/:(action)-:(id).html');
        $m->connect(
            'archives',
            'archives/:year/:month/:day/:slug',
            ['controller' => 'archives', 'action' => 'view', '_filter' => $callback]
        );
        $m->createRegs(['content', 'archives', 'admin/comments']);

        $this->assertNull($utils->urlFor(['controller' => 'content', 'action' => 'view']));
        $this->assertNull($utils->urlFor(['controller' => 'content']));

        $this->assertEquals(
            '/content/view-3.html',
            $utils->urlFor(['controller' => 'content', 'action' => 'view', 'id' => 3])
        );
        $this->assertEquals(
            '/content/index-2.html',
            $utils->urlFor(['controller' => 'content', 'id' => 2])
        );

        $this->assertEquals(
            '/archives/2005/10/5/happy',
            $utils->urlFor('archives', ['year' => 2005, 'month' => 10,
                'day' => 5, 'slug' => 'happy'])
        );

        $story = ['year' => 2003, 'month' => 8, 'day' => 2, 'slug' => 'woopee'];
        $empty = [];

        $expected = ['controller' => 'archives', 'action' => 'view', 'year' => '2005',
            'month' => '10', 'day' => '5', 'slug' => 'happy'];
        $this->assertEquals($expected, $m->match('/archives/2005/10/5/happy'));

        $this->assertEquals(
            '/archives/2003/8/2/woopee',
            $utils->urlFor('archives', ['article' => $story])
        );
        $this->assertEquals(
            '/archives/2004/12/20/default',
            $utils->urlFor('archives', ['article' => $empty])
        );
    }

    public function testWithSslEnviron()
    {
        $m = new Mapper();
        $m->environ = ['SCRIPT_NAME' => '', 'HTTPS' => 'on', 'SERVER_PORT' => '443',
            'PATH_INFO' => '/', 'HTTP_HOST' => 'example.com',
            'SERVER_NAME' => 'example.com'];

        $utils = $m->utils;
        $utils->mapperDict = [];

        $m->connect(':controller/:action/:id');
        $m->createRegs(['content', 'archives', 'admin/comments']);

        // HTTPS is on, but we're running on a different port internally
        $this->assertEquals(
            '/content/view',
            $utils->urlFor(['controller' => 'content', 'action' => 'view'])
        );
        $this->assertEquals(
            '/content/index/2',
            $utils->urlFor(['controller' => 'content', 'id' => 2])
        );
        $this->assertEquals(
            'https://nowhere.com/content',
            $utils->urlFor(['host' => 'nowhere.com', 'controller' => 'content'])
        );

        // If HTTPS is on, but the port isn't 443, we'll need to include the port info
        $m->environ['SERVER_PORT'] = '8080';

        $utils->mapperDict = [];

        $this->assertEquals(
            '/content/index/2',
            $utils->urlFor(['controller' => 'content', 'id' => '2'])
        );
        $this->assertEquals(
            'https://nowhere.com/content',
            $utils->urlFor(['host' => 'nowhere.com', 'controller' => 'content'])
        );
        $this->assertEquals(
            'https://nowhere.com:8080/content',
            $utils->urlFor(['host' => 'nowhere.com:8080', 'controller' => 'content'])
        );
        $this->assertEquals(
            'http://nowhere.com/content',
            $utils->urlFor(['host' => 'nowhere.com', 'protocol' => 'http',
                'controller' => 'content'])
        );
    }

    public function testWithHttpEnviron()
    {
        $m = new Mapper();
        $m->environ = ['SCRIPT_NAME' => '', 'SERVER_PORT' => '1080', 'PATH_INFO' => '/',
            'HTTP_HOST' => 'example.com', 'SERVER_NAME' => 'example.com'];

        $utils = $m->utils;
        $utils->mapperDict = [];

        $m->connect(':controller/:action/:id');
        $m->createRegs(['content', 'archives', 'admin/comments']);

        $this->assertEquals(
            '/content/view',
            $utils->urlFor(['controller' => 'content', 'action' => 'view'])
        );
        $this->assertEquals(
            '/content/index/2',
            $utils->urlFor(['controller' => 'content', 'id' => 2])
        );
        $this->assertEquals(
            'https://example.com/content',
            $utils->urlFor(['protocol' => 'https', 'controller' => 'content'])
        );
    }

    public function testSubdomains()
    {
        $m = new Mapper();
        $m->environ = ['SCRIPT_NAME' => '', 'PATH_INFO' => '/',
            'HTTP_HOST' => 'example.com', 'SERVER_NAME' => 'example.com'];

        $utils = $m->utils;
        $utils->mapperDict = [];

        $m->subDomains = true;
        $m->connect(':controller/:action/:id');
        $m->createRegs(['content', 'archives', 'admin/comments']);

        $this->assertEquals(
            '/content/view',
            $utils->urlFor(['controller' => 'content', 'action' => 'view'])
        );
        $this->assertEquals(
            '/content/index/2',
            $utils->urlFor(['controller' => 'content', 'id' => 2])
        );

        $m->environ['HTTP_HOST'] = 'sub.example.com';

        $utils->mapperDict = ['subDomain' => 'sub'];

        $this->assertEquals(
            '/content/view/3',
            $utils->urlFor(['controller' => 'content', 'action' => 'view', 'id' => 3])
        );
        $this->assertEquals(
            'http://new.example.com/content',
            $utils->urlFor(['controller' => 'content', 'subDomain' => 'new'])
        );
    }

    public function testSubdomainsWithExceptions()
    {
        $m = new Mapper();
        $m->environ = ['SCRIPT_NAME' => '', 'PATH_INFO' => '/',
            'HTTP_HOST' => 'example.com', 'SERVER_NAME' => 'example.com'];

        $utils = $m->utils;
        $utils->mapperDict = [];

        $m->subDomains = true;
        $m->subDomainsIgnore = ['www'];
        $m->connect(':controller/:action/:id');
        $m->createRegs(['content', 'archives', 'admin/comments']);

        $this->assertEquals(
            '/content/view',
            $utils->urlFor(['controller' => 'content', 'action' => 'view'])
        );
        $this->assertEquals(
            '/content/index/2',
            $utils->urlFor(['controller' => 'content', 'id' => 2])
        );

        $m->environ = ['HTTP_HOST' => 'sub.example.com'];

        $utils->mapperDict = ['subDomain' => 'sub'];

        $this->assertEquals(
            '/content/view/3',
            $utils->urlFor(['controller' => 'content', 'action' => 'view', 'id' => 3])
        );
        $this->assertEquals(
            'http://new.example.com/content',
            $utils->urlFor(['controller' => 'content', 'subDomain' => 'new'])
        );
        $this->assertEquals(
            'http://example.com/content',
            $utils->urlFor(['controller' => 'content', 'subDomain' => 'www'])
        );

        $utils->mapperDict = ['subDomain' => 'www'];
        $this->assertEquals(
            'http://example.com/content/view/3',
            $utils->urlFor(['controller' => 'content', 'action' => 'view', 'id' => 3])
        );
        $this->assertEquals(
            'http://new.example.com/content',
            $utils->urlFor(['controller' => 'content', 'subDomain' => 'new'])
        );
        $this->assertEquals(
            '/content',
            $utils->urlFor(['controller' => 'content', 'subDomain' => 'sub'])
        );
    }

    public function testSubdomainsWithNamedRoutes()
    {
        $m = new Mapper();
        $m->environ = ['SCRIPT_NAME' => '', 'PATH_INFO' => '/',
            'HTTP_HOST' => 'example.com', 'SERVER_NAME' => 'example.com'];

        $utils = $m->utils;
        $utils->mapperDict = [];

        $m->subDomains = true;
        $m->connect(':controller/:action/:id');
        $m->connect(
            'category_home',
            'category/:section',
            ['controller' => 'blog', 'action' => 'view', 'section' => 'home']
        );
        $m->connect(
            'building',
            'building/:campus/:building/alljacks',
            ['controller' => 'building', 'action' => 'showjacks']
        );
        $m->createRegs(['content','blog','admin/comments','building']);

        $this->assertEquals(
            '/content/view',
            $utils->urlFor(['controller' => 'content', 'action' => 'view'])
        );
        $this->assertEquals(
            '/content/index/2',
            $utils->urlFor(['controller' => 'content', 'id' => 2])
        );
        $this->assertEquals(
            '/category',
            $utils->urlFor('category_home')
        );
        $this->assertEquals(
            'http://new.example.com/category',
            $utils->urlFor('category_home', ['subDomain' => 'new'])
        );
    }

    public function testSubdomainsWithPorts()
    {
        $m = new Mapper();
        $m->environ = ['SCRIPT_NAME' => '', 'PATH_INFO' => '/',
            'HTTP_HOST' => 'example.com:8000', 'SERVER_NAME' => 'example.com'];

        $utils = $m->utils;
        $utils->mapperDict = [];

        $m->subDomains = true;
        $m->connect(':controller/:action/:id');
        $m->connect(
            'category_home',
            'category/:section',
            ['controller' => 'blog', 'action' => 'view', 'section' => 'home']
        );
        $m->connect(
            'building',
            'building/:campus/:building/alljacks',
            ['controller' => 'building', 'action' => 'showjacks']
        );
        $m->createRegs(['content', 'blog', 'admin/comments', 'building']);

        $this->assertEquals(
            '/content/view',
            $utils->urlFor(['controller' => 'content', 'action' => 'view'])
        );
        $this->assertEquals(
            '/category',
            $utils->urlFor('category_home')
        );
        $this->assertEquals(
            'http://new.example.com:8000/category',
            $utils->urlFor('category_home', ['subDomain' => 'new'])
        );
        $this->assertEquals(
            'http://joy.example.com:8000/building/west/merlot/alljacks',
            $utils->urlFor('building', ['campus' => 'west', 'building' => 'merlot',
                'subDomain' => 'joy'])
        );

        $m->environ = ['HTTP_HOST' => 'example.com'];

        $this->assertEquals(
            'http://new.example.com/category',
            $utils->urlFor('category_home', ['subDomain' => 'new'])
        );
    }

    public function testControllerScan()
    {
        $hereDir = __DIR__;
        $controllerDir = "$hereDir/fixtures/controllers";

        $controllers = Utils::controllerScan($controllerDir);

        $this->assertEquals(3, count($controllers));
        $this->assertEquals('admin/users', $controllers[0]);
        $this->assertEquals('content', $controllers[1]);
        $this->assertEquals('users', $controllers[2]);
    }

    public function testAutoControllerScan()
    {
        $hereDir = __DIR__;
        $controllerDir = "$hereDir/fixtures/controllers";

        $m = new Mapper(['directory' => $controllerDir]);
        $m->alwaysScan = true;

        $m->connect(':controller/:action/:id');

        $expected = ['action' => 'index', 'controller' => 'content', 'id' => null];
        $this->assertEquals($expected, $m->match('/content'));

        $expected = ['action' => 'index', 'controller' => 'users', 'id' => null];
        $this->assertEquals($expected, $m->match('/users'));

        $expected = ['action' => 'index', 'controller' => 'admin/users', 'id' => null];
        $this->assertEquals($expected, $m->match('/admin/users'));
    }

}
