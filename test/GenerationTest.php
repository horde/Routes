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

/**
 * @package Routes
 * @coversNothing
 */
class GenerationTest extends TestCase
{
    public function testAllStaticNoReqs()
    {
        $m = new Mapper();
        $m->connect('hello/world');

        $this->assertEquals('/hello/world', $m->generate());
    }

    public function testBasicDynamic()
    {
        foreach (['hi/:fred', 'hi/:(fred)'] as $path) {
            $m = new Mapper();
            $m->connect($path);

            $this->assertEquals('/hi/index', $m->generate(['fred' => 'index']));
            $this->assertEquals('/hi/show', $m->generate(['fred' => 'show']));
            $this->assertEquals('/hi/list+people', $m->generate(['fred' => 'list people']));
            $this->assertNull($m->generate());
        }
    }

    public function testDynamicWithDefault()
    {
        foreach (['hi/:action', 'hi/:(action)'] as $path) {
            $m = new Mapper();
            $m->connect($path);

            $this->assertEquals('/hi', $m->generate(['action' => 'index']));
            $this->assertEquals('/hi/show', $m->generate(['action' => 'show']));
            $this->assertEquals('/hi/list+people', $m->generate(['action' => 'list people']));
            $this->assertEquals('/hi', $m->generate());
        }
    }

    /**
     * Some of these assertions are invalidated in PHP, it passes in Python because
     * unicode(None) == unicode('None').  In PHP, we don't have a function similar
     * to unicode().  These have the comment "unicode false equiv"
     */
    public function testDynamicWithFalseEquivs()
    {
        $m = new Mapper();
        $m->connect('article/:page', ['page' => false]);
        $m->connect(':controller/:action/:id');

        $this->assertEquals(
            '/blog/view/0',
            $m->generate(['controller' => 'blog', 'action' => 'view', 'id' => '0'])
        );

        // unicode false equiv
        // $this->assertEquals('/blog/view/0',
        //                     $m->generate(array('controller' => 'blog', 'action' => 'view', 'id' => 0)));
        //$this->assertEquals('/blog/view/False',
        //                     $m->generate(array('controller' => 'blog', 'action' => 'view', 'id' => false)));

        $this->assertEquals(
            '/blog/view/False',
            $m->generate(['controller' => 'blog', 'action' => 'view', 'id' => 'False'])
        );
        $this->assertEquals(
            '/blog/view',
            $m->generate(['controller' => 'blog', 'action' => 'view', 'id' => null])
        );

        // unicode false equiv
        // $this->assertEquals('/blog/view',
        //                    $m->generate(array('controller' => 'blog', 'action' => 'view', 'id' => 'null')));

        $this->assertEquals(
            '/article',
            $m->generate(['page' => null])
        );
    }

    public function testDynamicWithUnderscoreParts()
    {
        $m = new Mapper();
        $m->connect('article/:small_page', ['small_page' => false]);
        $m->connect(':(controller)/:(action)/:(id)');

        $this->assertEquals(
            '/blog/view/0',
            $m->generate(['controller' => 'blog', 'action' => 'view', 'id' => '0'])
        );

        // unicode false equiv
        // $this->assertEquals('/blog/view/False',
        //                     $m->generate(array('controller' => 'blog', 'action' => 'view', 'id' => false)));

        // unicode False Equiv
        //$this->assertEquals('/blog/view',
        //                    $m->generate(array('controller' => 'blog', 'action' => 'view', 'id' => 'null'))); */

        $this->assertEquals(
            '/article',
            $m->generate(['small_page' => null])
        );
        $this->assertEquals(
            '/article/hobbes',
            $m->generate(['small_page' => 'hobbes'])
        );
    }

    public function testDynamicWithFalseEquivsAndSplits()
    {
        $m = new Mapper();
        $m->connect('article/:(page)', ['page' => false]);
        $m->connect(':(controller)/:(action)/:(id)');

        $this->assertEquals(
            '/blog/view/0',
            $m->generate(['controller' => 'blog', 'action' => 'view', 'id' => '0'])
        );

        // unicode false equiv
        // $this->assertEquals('/blog/view/0',
        //                     $m->generate(array('controller' => 'blog', 'action' => 'view', 'id' => 0)));
        // $this->assertEquals('/blog/view/False',
        //                     $m->generate(array('controller' => 'blog', 'action' => 'view', 'id' => false));

        $this->assertEquals(
            '/blog/view/False',
            $m->generate(['controller' => 'blog', 'action' => 'view', 'id' => 'False'])
        );
        $this->assertEquals(
            '/blog/view',
            $m->generate(['controller' => 'blog', 'action' => 'view', 'id' => null])
        );

        // unicode false equiv
        //$this->assertEquals('/blog/view',
        //                    $m->generate(array('controller' => 'blog', 'action' => 'view', 'id' => 'null')));

        $this->assertEquals(
            '/article',
            $m->generate(['page' => null])
        );

        $m = new Mapper();
        $m->connect('view/:(home)/:(area)', ['home' => 'austere', 'area' => null]);

        $this->assertEquals(
            '/view/sumatra',
            $m->generate(['home' => 'sumatra'])
        );
        $this->assertEquals(
            '/view/austere/chicago',
            $m->generate(['area' => 'chicago'])
        );

        $m = new Mapper();
        $m->connect('view/:(home)/:(area)', ['home' => null, 'area' => null]);

        $this->assertEquals(
            '/view/null/chicago',
            $m->generate(['home' => null, 'area' => 'chicago'])
        );
    }

    public function testDynamicWithRegExpCondition()
    {
        foreach (['hi/:name', 'hi/:(name)'] as $path) {
            $m = new Mapper();
            $m->connect($path, ['requirements' => ['name' => '[a-z]+']]);

            $this->assertEquals('/hi/index', $m->generate(['name' => 'index']));
            $this->assertNull($m->generate(['name' => 'fox5']));
            $this->assertNull($m->generate(['name' => 'something_is_up']));
            $this->assertEquals(
                '/hi/abunchofcharacter',
                $m->generate(['name' => 'abunchofcharacter'])
            );
            $this->assertNull($m->generate());
        }
    }

    public function testDynamicWithDefaultAndRegexpCondition()
    {
        foreach (['hi/:action', 'hi/:(action)'] as $path) {
            $m = new Mapper();
            $m->connect($path, ['requirements' => ['action' => '[a-z]+']]);

            $this->assertEquals('/hi', $m->generate(['action' => 'index']));
            $this->assertNull($m->generate(['action' => 'fox5']));
            $this->assertNull($m->generate(['action' => 'something_is_up']));
            $this->assertNull($m->generate(['action' => 'list people']));
            $this->assertEquals(
                '/hi/abunchofcharacter',
                $m->generate(['action' => 'abunchofcharacter'])
            );
            $this->assertEquals('/hi', $m->generate());
        }
    }

    public function testPath()
    {
        foreach (['hi/*file', 'hi/*(file)'] as $path) {
            $m = new Mapper();
            $m->connect($path);

            $this->assertEquals(
                '/hi',
                $m->generate(['file' => null])
            );
            $this->assertEquals(
                '/hi/books/learning_python.pdf',
                $m->generate(['file' => 'books/learning_python.pdf'])
            );
            $this->assertEquals(
                '/hi/books/development%26whatever/learning_python.pdf',
                $m->generate(['file' => 'books/development&whatever/learning_python.pdf'])
            );
        }
    }

    public function testPathBackwards()
    {
        foreach (['*file/hi', '*(file)/hi'] as $path) {
            $m = new Mapper();
            $m->connect($path);

            $this->assertEquals(
                '/hi',
                $m->generate(['file' => null])
            );
            $this->assertEquals(
                '/books/learning_python.pdf/hi',
                $m->generate(['file' => 'books/learning_python.pdf'])
            );
            $this->assertEquals(
                '/books/development%26whatever/learning_python.pdf/hi',
                $m->generate(['file' => 'books/development&whatever/learning_python.pdf'])
            );
        }
    }

    public function testController()
    {
        foreach (['hi/:controller', 'hi/:(controller)'] as $path) {
            $m = new Mapper();
            $m->connect($path);

            $this->assertEquals(
                '/hi/content',
                $m->generate(['controller' => 'content'])
            );
            $this->assertEquals(
                '/hi/admin/user',
                $m->generate(['controller' => 'admin/user'])
            );
        }
    }

    public function testControllerWithStatic()
    {
        foreach (['hi/:controller', 'hi/:(controller)'] as $path) {
            $m = new Mapper();
            $utils = $m->utils;
            $m->connect($path);
            $m->connect('google', 'http://www.google.com', ['_static' => true]);

            $this->assertEquals(
                '/hi/content',
                $m->generate(['controller' => 'content'])
            );
            $this->assertEquals(
                '/hi/admin/user',
                $m->generate(['controller' => 'admin/user'])
            );
            $this->assertEquals('http://www.google.com', $utils->urlFor('google'));
        }
    }

    public function testStandardRoute()
    {
        foreach ([':controller/:action/:id', ':(controller)/:(action)/:(id)'] as $path) {
            $m = new Mapper();
            $m->connect($path);

            $this->assertEquals(
                '/content',
                $m->generate(['controller' => 'content', 'action' => 'index'])
            );
            $this->assertEquals(
                '/content/list',
                $m->generate(['controller' => 'content', 'action' => 'list'])
            );
            $this->assertEquals(
                '/content/show/10',
                $m->generate(['controller' => 'content', 'action' => 'show', 'id' => '10'])
            );

            $this->assertEquals(
                '/admin/user',
                $m->generate(['controller' => 'admin/user', 'action' => 'index'])
            );
            $this->assertEquals(
                '/admin/user/list',
                $m->generate(['controller' => 'admin/user', 'action' => 'list'])
            );
            $this->assertEquals(
                '/admin/user/show/10',
                $m->generate(['controller' => 'admin/user', 'action' => 'show', 'id' => '10'])
            );
        }
    }

    public function testMultiroute()
    {
        $m = new Mapper();
        $m->connect('archive/:year/:month/:day', ['controller' => 'blog', 'action' => 'view',
            'month' => null, 'day' => null,
            'requirements' => ['month' => '\d{1,2}',
                'day'   => '\d{1,2}']]);
        $m->connect('viewpost/:id', ['controller' => 'post', 'action' => 'view']);
        $m->connect(':controller/:action/:id');

        $this->assertEquals(
            '/blog/view?year=2004&month=blah',
            $m->generate(['controller' => 'blog', 'action' => 'view',
                'year' => 2004, 'month' => 'blah'])
        );
        $this->assertEquals(
            '/archive/2004/11',
            $m->generate(['controller' => 'blog', 'action' => 'view',
                'year' => 2004, 'month' => 11])
        );
        $this->assertEquals(
            '/archive/2004/11',
            $m->generate(['controller' => 'blog', 'action' => 'view',
                'year' => 2004, 'month' => '11'])
        );
        $this->assertEquals(
            '/archive/2004/11',
            $m->generate(['controller' => 'blog', 'action' => 'view', 'year' => 2004, 'month' => 11])
        );
        $this->assertEquals('/archive/2004', $m->generate(['controller' => 'blog', 'action' => 'view', 'year' => 2004]));
        $this->assertEquals(
            '/viewpost/3',
            $m->generate(['controller' => 'post', 'action' => 'view', 'id' => 3])
        );
    }

    public function testMultirouteWithSplits()
    {
        $m = new Mapper();
        $m->connect('archive/:(year)/:(month)/:(day)', ['controller' => 'blog', 'action' => 'view',
            'month' => null, 'day' => null,
            'requirements' => ['month' => '\d{1,2}',
                'day'   => '\d{1,2}']]);
        $m->connect('viewpost/:(id)', ['controller' => 'post', 'action' => 'view']);
        $m->connect(':(controller)/:(action)/:(id)');

        $this->assertEquals(
            '/blog/view?year=2004&month=blah',
            $m->generate(['controller' => 'blog', 'action' => 'view',
                'year' => 2004, 'month' => 'blah'])
        );
        $this->assertEquals(
            '/archive/2004/11',
            $m->generate(['controller' => 'blog', 'action' => 'view',
                'year' => 2004, 'month' => 11])
        );
        $this->assertEquals(
            '/archive/2004/11',
            $m->generate(['controller' => 'blog', 'action' => 'view',
                'year' => 2004, 'month' => '11'])
        );
        $this->assertEquals(
            '/archive/2004',
            $m->generate(['controller' => 'blog', 'action' => 'view', 'year' => 2004])
        );
        $this->assertEquals(
            '/viewpost/3',
            $m->generate(['controller' => 'post', 'action' => 'view', 'id' => 3])
        );
    }

    public function testBigMultiroute()
    {
        $m = new Mapper();
        $m->connect('', ['controller' => 'articles', 'action' => 'index']);
        $m->connect('admin', ['controller' => 'admin/general', 'action' => 'index']);

        $m->connect(
            'admin/comments/article/:article_id/:action/:id',
            ['controller' => 'admin/comments', 'action' => null, 'id' => null]
        );
        $m->connect(
            'admin/trackback/article/:article_id/:action/:id',
            ['controller' => 'admin/trackback', 'action' => null, 'id' => null]
        );
        $m->connect('admin/content/:action/:id', ['controller' => 'admin/content']);

        $m->connect('xml/:action/feed.xml', ['controller' => 'xml']);
        $m->connect('xml/articlerss/:id/feed.xml', ['controller' => 'xml', 'action' => 'articlerss']);
        $m->connect('index.rdf', ['controller' => 'xml', 'action' => 'rss']);

        $m->connect('articles', ['controller' => 'articles', 'action' => 'index']);
        $m->connect(
            'articles/page/:page',
            ['controller' => 'articles', 'action' => 'index',
                'requirements' => ['page' => '\d+']]
        );
        $m->connect(
            'articles/:year/:month/:day/page/:page',
            ['controller' => 'articles', 'action' => 'find_by_date',
                'month' => null, 'day' => null,
                'requirements' => ['year' => '\d{4}', 'month' => '\d{1,2}',
                    'day' => '\d{1,2}']]
        );
        $m->connect('articles/category/:id', ['controller' => 'articles', 'action' => 'category']);
        $m->connect('pages/*name', ['controller' => 'articles', 'action' => 'view_page']);

        $this->assertEquals(
            '/pages/the/idiot/has/spoken',
            $m->generate(['controller' => 'articles', 'action' => 'view_page',
                'name' => 'the/idiot/has/spoken'])
        );
        $this->assertEquals(
            '/',
            $m->generate(['controller' => 'articles', 'action' => 'index'])
        );
        $this->assertEquals(
            '/xml/articlerss/4/feed.xml',
            $m->generate(['controller' => 'xml', 'action' => 'articlerss', 'id' => 4])
        );
        $this->assertEquals(
            '/xml/rss/feed.xml',
            $m->generate(['controller' => 'xml', 'action' => 'rss'])
        );
        $this->assertEquals(
            '/admin/comments/article/4/view/2',
            $m->generate(['controller' => 'admin/comments', 'action' => 'view',
                'article_id' => 4, 'id' => 2])
        );
        $this->assertEquals(
            '/admin',
            $m->generate(['controller' => 'admin/general'])
        );
        $this->assertEquals(
            '/admin/comments/article/4/index',
            $m->generate(['controller' => 'admin/comments', 'article_id' => 4])
        );
        $this->assertEquals(
            '/admin/comments/article/4',
            $m->generate(['controller' => 'admin/comments', 'action' => null,
                'article_id' => 4])
        );
        $this->assertEquals(
            '/articles/2004/2/20/page/1',
            $m->generate(['controller' => 'articles', 'action' => 'find_by_date',
                'year' => 2004, 'month' => 2, 'day' => 20, 'page' => 1])
        );
        $this->assertEquals(
            '/articles/category',
            $m->generate(['controller' => 'articles', 'action' => 'category'])
        );
        $this->assertEquals(
            '/xml/index/feed.xml',
            $m->generate(['controller' => 'xml'])
        );
        $this->assertEquals(
            '/xml/articlerss/feed.xml',
            $m->generate(['controller' => 'xml', 'action' => 'articlerss'])
        );
        $this->assertNull($m->generate(['controller' => 'admin/comments', 'id' => 2]));
        $this->assertNull($m->generate(['controller' => 'articles', 'action' => 'find_by_date',
            'year' => 2004]));
    }

    public function testBigMultirouteWithSplits()
    {
        $m = new Mapper();
        $m->connect('', ['controller' => 'articles', 'action' => 'index']);
        $m->connect('admin', ['controller' => 'admin/general', 'action' => 'index']);

        $m->connect(
            'admin/comments/article/:(article_id)/:(action)/:(id)',
            ['controller' => 'admin/comments', 'action' => null, 'id' => null]
        );
        $m->connect(
            'admin/trackback/article/:(article_id)/:(action)/:(id)',
            ['controller' => 'admin/trackback', 'action' => null, 'id' => null]
        );
        $m->connect('admin/content/:(action)/:(id)', ['controller' => 'admin/content']);

        $m->connect('xml/:(action)/feed.xml', ['controller' => 'xml']);
        $m->connect('xml/articlerss/:(id)/feed.xml', ['controller' => 'xml', 'action' => 'articlerss']);
        $m->connect('index.rdf', ['controller' => 'xml', 'action' => 'rss']);

        $m->connect('articles', ['controller' => 'articles', 'action' => 'index']);
        $m->connect(
            'articles/page/:(page)',
            ['controller' => 'articles', 'action' => 'index',
                'requirements' => ['page' => '\d+']]
        );
        $m->connect(
            'articles/:(year)/:(month)/:(day)/page/:(page)',
            ['controller' => 'articles', 'action' => 'find_by_date',
                'month' => null, 'day' => null,
                'requirements' => ['year' => '\d{4}', 'month' => '\d{1,2}',
                    'day' => '\d{1,2}']]
        );
        $m->connect('articles/category/:(id)', ['controller' => 'articles', 'action' => 'category']);
        $m->connect('pages/*name', ['controller' => 'articles', 'action' => 'view_page']);

        $this->assertEquals(
            '/pages/the/idiot/has/spoken',
            $m->generate(['controller' => 'articles', 'action' => 'view_page',
                'name' => 'the/idiot/has/spoken'])
        );
        $this->assertEquals(
            '/',
            $m->generate(['controller' => 'articles', 'action' => 'index'])
        );
        $this->assertEquals(
            '/xml/articlerss/4/feed.xml',
            $m->generate(['controller' => 'xml', 'action' => 'articlerss', 'id' => 4])
        );
        $this->assertEquals(
            '/xml/rss/feed.xml',
            $m->generate(['controller' => 'xml', 'action' => 'rss'])
        );
        $this->assertEquals(
            '/admin/comments/article/4/view/2',
            $m->generate(['controller' => 'admin/comments', 'action' => 'view',
                'article_id' => 4, 'id' => 2])
        );
        $this->assertEquals(
            '/admin',
            $m->generate(['controller' => 'admin/general'])
        );
        $this->assertEquals(
            '/admin/comments/article/4/index',
            $m->generate(['controller' => 'admin/comments', 'article_id' => 4])
        );
        $this->assertEquals(
            '/admin/comments/article/4',
            $m->generate(['controller' => 'admin/comments', 'action' => null,
                'article_id' => 4])
        );
        $this->assertEquals(
            '/articles/2004/2/20/page/1',
            $m->generate(['controller' => 'articles', 'action' => 'find_by_date',
                'year' => 2004, 'month' => 2, 'day' => 20, 'page' => 1])
        );
        $this->assertEquals(
            '/articles/category',
            $m->generate(['controller' => 'articles', 'action' => 'category'])
        );
        $this->assertEquals(
            '/xml/index/feed.xml',
            $m->generate(['controller' => 'xml'])
        );
        $this->assertEquals(
            '/xml/articlerss/feed.xml',
            $m->generate(['controller' => 'xml', 'action' => 'articlerss'])
        );
        $this->assertNull($m->generate(['controller' => 'admin/comments', 'id' => 2]));
        $this->assertNull($m->generate(['controller' => 'articles', 'action' => 'find_by_date',
            'year' => 2004]));
    }

    public function testNoExtras()
    {
        $m = new Mapper();
        $m->connect(':controller/:action/:id');
        $m->connect('archive/:year/:month/:day', ['controller' => 'blog', 'action' => 'view',
            'month' => null, 'day' => null]);

        $this->assertEquals(
            '/archive/2004',
            $m->generate(['controller' => 'blog', 'action' => 'view',
                'year' => 2004])
        );
    }

    public function testNoExtrasWithSplits()
    {
        $m = new Mapper();
        $m->connect(':(controller)/:(action)/:(id)');
        $m->connect('archive/:(year)/:(month)/:(day)', ['controller' => 'blog', 'action' => 'view',
            'month' => null, 'day' => null]);

        $this->assertEquals(
            '/archive/2004',
            $m->generate(['controller' => 'blog', 'action' => 'view',
                'year' => 2004])
        );
    }

    public function testTheSmallestRoute()
    {
        foreach (['pages/:title', 'pages/:(title)'] as $path) {
            $m = new Mapper();
            $m->connect('', ['controller' => 'page', 'action' => 'view', 'title' => 'HomePage']);
            $m->connect($path, ['controller' => 'page', 'action' => 'view']);

            $this->assertEquals('/pages/joe', $m->generate(['controller' => 'page', 'action' => 'view', 'title' => 'joe']));
            $this->assertEquals(
                '/',
                $m->generate(['controller' => 'page', 'action' => 'view',
                    'title' => 'HomePage'])
            );
        }
    }

    public function testExtras()
    {
        $m = new Mapper();
        $m->connect('viewpost/:id', ['controller' => 'post', 'action' => 'view']);
        $m->connect(':controller/:action/:id');

        $this->assertEquals(
            '/viewpost/2?extra=x%2Fy',
            $m->generate(['controller' => 'post', 'action' => 'view',
                'id' => 2, 'extra' => 'x/y'])
        );
        $this->assertEquals(
            '/blog?extra=3',
            $m->generate(['controller' => 'blog', 'action' => 'index', 'extra' => 3])
        );
        $this->assertEquals(
            '/viewpost/2?extra=3',
            $m->generate(['controller' => 'post', 'action' => 'view',
                'id' => 2, 'extra' => 3])
        );
    }

    public function testExtrasWithSplits()
    {
        $m = new Mapper();
        $m->connect('viewpost/:(id)', ['controller' => 'post', 'action' => 'view']);
        $m->connect(':(controller)/:(action)/:(id)');

        $this->assertEquals(
            '/viewpost/2?extra=x%2Fy',
            $m->generate(['controller' => 'post', 'action' => 'view',
                'id' => 2, 'extra' => 'x/y'])
        );
        $this->assertEquals(
            '/blog?extra=3',
            $m->generate(['controller' => 'blog', 'action' => 'index', 'extra' => 3])
        );
        $this->assertEquals(
            '/viewpost/2?extra=3',
            $m->generate(['controller' => 'post', 'action' => 'view',
                'id' => 2, 'extra' => 3])
        );
    }

    public function testStatic()
    {
        $m = new Mapper();
        $m->connect('hello/world', ['controller' => 'content', 'action' => 'index',
            'known' => 'known_value']);

        $this->assertEquals(
            '/hello/world',
            $m->generate(['controller' => 'content', 'action' => 'index',
                'known' => 'known_value'])
        );
        $this->assertEquals(
            '/hello/world?extra=hi',
            $m->generate(['controller' => 'content', 'action' => 'index',
                'known' => 'known_value', 'extra' => 'hi'])
        );
        $this->assertNull($m->generate(['known' => 'foo']));
    }

    public function testTypical()
    {
        foreach ([':controller/:action/:id', ':(controller)/:(action)/:(id)'] as $path) {
            $m = new Mapper();
            $m->connect($path, ['action' => 'index', 'id' => null]);

            $this->assertEquals(
                '/content',
                $m->generate(['controller' => 'content', 'action' => 'index'])
            );
            $this->assertEquals(
                '/content/list',
                $m->generate(['controller' => 'content', 'action' => 'list'])
            );
            $this->assertEquals(
                '/content/show/10',
                $m->generate(['controller' => 'content', 'action' => 'show', 'id' => 10])
            );

            $this->assertEquals(
                '/admin/user',
                $m->generate(['controller' => 'admin/user', 'action' => 'index'])
            );
            $this->assertEquals(
                '/admin/user',
                $m->generate(['controller' => 'admin/user'])
            );
            $this->assertEquals(
                '/admin/user/show/10',
                $m->generate(['controller' => 'admin/user', 'action' => 'show', 'id' => 10])
            );

            $this->assertEquals('/content', $m->generate(['controller' => 'content']));
        }
    }

    public function testRouteWithFixnumDefault()
    {
        $m = new Mapper();
        $m->connect('page/:id', ['controller' => 'content', 'action' => 'show_page', 'id' => 1]);

        $m->connect(':controller/:action/:id');

        $this->assertEquals(
            '/page',
            $m->generate(['controller' => 'content', 'action' => 'show_page'])
        );
        $this->assertEquals(
            '/page',
            $m->generate(['controller' => 'content', 'action' => 'show_page', 'id' => 1])
        );
        $this->assertEquals(
            '/page',
            $m->generate(['controller' => 'content', 'action' => 'show_page', 'id' => '1'])
        );
        $this->assertEquals(
            '/page/10',
            $m->generate(['controller' => 'content', 'action' => 'show_page', 'id' => 10])
        );
        $this->assertEquals(
            '/blog/show/4',
            $m->generate(['controller' => 'blog', 'action' => 'show', 'id' => 4])
        );
        $this->assertEquals(
            '/page',
            $m->generate(['controller' => 'content', 'action' => 'show_page'])
        );
        $this->assertEquals(
            '/page/4',
            $m->generate(['controller' => 'content', 'action' => 'show_page', 'id' => 4])
        );
        $this->assertEquals(
            '/content/show',
            $m->generate(['controller' => 'content', 'action' => 'show'])
        );
    }

    public function testRouteWithFixnumDefaultWithSplits()
    {
        $m = new Mapper();
        $m->connect('page/:(id)', ['controller' => 'content', 'action' => 'show_page', 'id' => 1]);
        $m->connect(':(controller)/:(action)/:(id)');

        $this->assertEquals(
            '/page',
            $m->generate(['controller' => 'content', 'action' => 'show_page'])
        );
        $this->assertEquals(
            '/page',
            $m->generate(['controller' => 'content', 'action' => 'show_page', 'id' => 1])
        );
        $this->assertEquals(
            '/page',
            $m->generate(['controller' => 'content', 'action' => 'show_page', 'id' => '1'])
        );
        $this->assertEquals(
            '/page/10',
            $m->generate(['controller' => 'content', 'action' => 'show_page', 'id' => 10])
        );
        $this->assertEquals(
            '/blog/show/4',
            $m->generate(['controller' => 'blog', 'action' => 'show', 'id' => 4])
        );
        $this->assertEquals(
            '/page',
            $m->generate(['controller' => 'content', 'action' => 'show_page'])
        );
        $this->assertEquals(
            '/page/4',
            $m->generate(['controller' => 'content', 'action' => 'show_page', 'id' => 4])
        );
        $this->assertEquals(
            '/content/show',
            $m->generate(['controller' => 'content', 'action' => 'show'])
        );
    }

    public function testUppercaseRecognition()
    {
        foreach ([':controller/:action/:id', ':(controller)/:(action)/:(id)'] as $path) {
            $m = new Mapper();
            $m->connect($path);

            $this->assertEquals(
                '/Content',
                $m->generate(['controller' => 'Content', 'action' => 'index'])
            );
            $this->assertEquals(
                '/Content/list',
                $m->generate(['controller' => 'Content', 'action' => 'list'])
            );
            $this->assertEquals(
                '/Content/show/10',
                $m->generate(['controller' => 'Content', 'action' => 'show', 'id' => '10'])
            );

            $this->assertEquals(
                '/Admin/NewsFeed',
                $m->generate(['controller' => 'Admin/NewsFeed', 'action' => 'index'])
            );
        }
    }

    public function testBackwards()
    {
        $m = new Mapper();
        $m->connect('page/:id/:action', ['controller' => 'pages', 'action' => 'show']);
        $m->connect(':controller/:action/:id');

        $this->assertEquals(
            '/page/20',
            $m->generate(['controller' => 'pages', 'action' => 'show', 'id' => 20])
        );
        $this->assertEquals(
            '/pages/boo',
            $m->generate(['controller' => 'pages', 'action' => 'boo'])
        );
    }

    public function testBackwardsWithSplits()
    {
        $m = new Mapper();
        $m->connect('page/:(id)/:(action)', ['controller' => 'pages', 'action' => 'show']);
        $m->connect(':(controller)/:(action)/:(id)');

        $this->assertEquals(
            '/page/20',
            $m->generate(['controller' => 'pages', 'action' => 'show', 'id' => 20])
        );
        $this->assertEquals(
            '/pages/boo',
            $m->generate(['controller' => 'pages', 'action' => 'boo'])
        );
    }

    public function testBothRequirementAndOptional()
    {
        $m = new Mapper();
        $m->connect('test/:year', ['controller' => 'post', 'action' => 'show',
            'year' => null, 'requirements' => ['year' => '\d{4}']]);

        $this->assertEquals(
            '/test',
            $m->generate(['controller' => 'post', 'action' => 'show'])
        );
        $this->assertEquals(
            '/test',
            $m->generate(['controller' => 'post', 'action' => 'show', 'year' => null])
        );
    }

    public function testSetToNilForgets()
    {
        $m = new Mapper();
        $m->connect(
            'pages/:year/:month/:day',
            ['controller' => 'content', 'action' => 'list_pages',
                'month' => null, 'day' => null]
        );
        $m->connect(':controller/:action/:id');

        $this->assertEquals(
            '/pages/2005',
            $m->generate(['controller' => 'content', 'action' => 'list_pages',
                'year' => 2005])
        );
        $this->assertEquals(
            '/pages/2005/6',
            $m->generate(['controller' => 'content', 'action' => 'list_pages',
                'year' => 2005, 'month' => 6])
        );
        $this->assertEquals(
            '/pages/2005/6/12',
            $m->generate(['controller' => 'content', 'action' => 'list_pages',
                'year' => 2005, 'month' => 6, 'day' => 12])
        );
    }

    public function testUrlWithNoActionSpecified()
    {
        $m = new Mapper();
        $m->connect('', ['controller' => 'content']);
        $m->connect(':controller/:action/:id');

        $this->assertEquals(
            '/',
            $m->generate(['controller' => 'content', 'action' => 'index'])
        );
        $this->assertEquals(
            '/',
            $m->generate(['controller' => 'content'])
        );
    }

    public function testUrlWithPrefix()
    {
        $m = new Mapper();
        $m->prefix = '/blog';
        $m->connect(':controller/:action/:id');
        $m->createRegs(['content', 'blog', 'admin/comments']);

        $this->assertEquals(
            '/blog/content/view',
            $m->generate(['controller' => 'content', 'action' => 'view'])
        );
        $this->assertEquals(
            '/blog/content',
            $m->generate(['controller' => 'content'])
        );
        $this->assertEquals(
            '/blog/admin/comments',
            $m->generate(['controller' => 'admin/comments'])
        );
    }

    public function testUrlWithPrefixDeeper()
    {
        $m = new Mapper();
        $m->prefix = '/blog/phil';
        $m->connect(':controller/:action/:id');
        $m->createRegs(['content', 'blog', 'admin/comments']);

        $this->assertEquals(
            '/blog/phil/content/view',
            $m->generate(['controller' => 'content', 'action' => 'view'])
        );
        $this->assertEquals(
            '/blog/phil/content',
            $m->generate(['controller' => 'content'])
        );
        $this->assertEquals(
            '/blog/phil/admin/comments',
            $m->generate(['controller' => 'admin/comments'])
        );
    }

    public function testUrlWithEnvironEmpty()
    {
        $m = new Mapper();
        $m->environ = ['SCRIPT_NAME' => ''];

        $m->connect(':controller/:action/:id');
        $m->createRegs(['content', 'blog', 'admin/comments']);

        $this->assertEquals(
            '/content/view',
            $m->generate(['controller' => 'content', 'action' => 'view'])
        );
        $this->assertEquals(
            '/content',
            $m->generate(['controller' => 'content'])
        );
        $this->assertEquals(
            '/admin/comments',
            $m->generate(['controller' => 'admin/comments'])
        );
    }

    public function testUrlWithEnviron()
    {
        $m = new Mapper();
        $m->environ = ['SCRIPT_NAME' => '/blog'];

        $m->connect(':controller/:action/:id');
        $m->createRegs(['content', 'blog', 'admin/comments']);

        $this->assertEquals(
            '/blog/content/view',
            $m->generate(['controller' => 'content', 'action' => 'view'])
        );
        $this->assertEquals(
            '/blog/content',
            $m->generate(['controller' => 'content'])
        );
        $this->assertEquals(
            '/blog/admin/comments',
            $m->generate(['controller' => 'admin/comments'])
        );

        $m->environ['SCRIPT_NAME'] = '/notblog';

        $this->assertEquals(
            '/notblog/content/view',
            $m->generate(['controller' => 'content', 'action' => 'view'])
        );
        $this->assertEquals(
            '/notblog/content',
            $m->generate(['controller' => 'content'])
        );
        $this->assertEquals(
            '/notblog/content',
            $m->generate(['controller' => 'content'])
        );
        $this->assertEquals(
            '/notblog/admin/comments',
            $m->generate(['controller' => 'admin/comments'])
        );
    }

    public function testUrlWithEnvironAndAbsolute()
    {
        $m = new Mapper();
        $m->environ = ['SCRIPT_NAME' => '/blog'];

        $utils = $m->utils;

        $m->connect('image', 'image/:name', ['_absolute' => true]);
        $m->connect(':controller/:action/:id');
        $m->createRegs(['content', 'blog', 'admin/comments']);

        $this->assertEquals(
            '/blog/content/view',
            $m->generate(['controller' => 'content', 'action' => 'view'])
        );
        $this->assertEquals(
            '/blog/content',
            $m->generate(['controller' => 'content'])
        );
        $this->assertEquals(
            '/blog/content',
            $m->generate(['controller' => 'content'])
        );
        $this->assertEquals(
            '/blog/admin/comments',
            $m->generate(['controller' => 'admin/comments'])
        );
        $this->assertEquals(
            '/image/topnav.jpg',
            $utils->urlFor('image', ['name' => 'topnav.jpg'])
        );
    }

    public function testRouteWithOddLeftovers()
    {
        $m = new Mapper();
        $m->environ = [];

        $m->connect(':controller/:(action)-:(id)');
        $m->createRegs(['content', 'blog', 'admin/comments']);

        $this->assertEquals(
            '/content/view-',
            $m->generate(['controller' => 'content', 'action' => 'view'])
        );
        $this->assertEquals(
            '/content/index-',
            $m->generate(['controller' => 'content'])
        );
    }

    public function testRouteWithEndExtension()
    {
        $m = new Mapper();
        $m->environ = [];

        $m->connect(':controller/:(action)-:(id).html');
        $m->createRegs(['content', 'blog', 'admin/comments']);

        $this->assertNull($m->generate(['controller' => 'content', 'action' => 'view']));
        $this->assertNull($m->generate(['controller' => 'content']));

        $this->assertEquals(
            '/content/view-3.html',
            $m->generate(['controller' => 'content', 'action' => 'view', 'id' => 3])
        );
        $this->assertEquals(
            '/content/index-2.html',
            $m->generate(['controller' => 'content', 'id' => 2])
        );
    }

    public function testResources()
    {
        $m = new Mapper();
        $m->environ = [];

        $utils = $m->utils;

        $m->resource('message', 'messages');
        $m->createRegs(['messages']);
        $options = ['controller' => 'messages'];

        $this->assertEquals(
            '/messages',
            $utils->urlFor('messages')
        );
        $this->assertEquals(
            '/messages.xml',
            $utils->urlFor('messages', ['format' => 'xml'])
        );
        $this->assertEquals(
            '/messages/1',
            $utils->urlFor('message', ['id' => 1])
        );
        $this->assertEquals(
            '/messages/1.xml',
            $utils->urlFor('message', ['id' => 1, 'format' => 'xml'])
        );
        $this->assertEquals(
            '/messages/new',
            $utils->urlFor('new_message')
        );
        $this->assertEquals(
            '/messages/1.xml',
            $utils->urlFor('message', ['id' => 1, 'format' => 'xml'])
        );
        $this->assertEquals(
            '/messages/1/edit',
            $utils->urlFor('edit_message', ['id' => 1])
        );
        $this->assertEquals(
            '/messages/1/edit.xml',
            $utils->urlFor('edit_message', ['id' => 1, 'format' => 'xml'])
        );

        $this->assertRestfulRoutes($m, $options);
    }

    public function testResourcesWithPathPrefix()
    {
        $m = new Mapper();
        $m->resource('message', 'messages', ['pathPrefix' => '/thread/:threadid']);
        $m->createRegs(['messages']);
        $options = ['controller' => 'messages', 'threadid' => '5'];
        $this->assertRestfulRoutes($m, $options, 'thread/5/');
    }

    public function testResourcesWithCollectionAction()
    {
        $m = new Mapper();
        $utils = $m->utils;
        $m->resource('message', 'messages', ['collection' => ['rss' => 'GET']]);
        $m->createRegs(['messages']);
        $options = ['controller' => 'messages'];
        $this->assertRestfulRoutes($m, $options);

        $this->assertEquals(
            '/messages/rss',
            $m->generate(['controller' => 'messages', 'action' => 'rss'])
        );
        $this->assertEquals(
            '/messages/rss',
            $utils->urlFor('rss_messages')
        );
        $this->assertEquals(
            '/messages/rss.xml',
            $m->generate(['controller' => 'messages', 'action' => 'rss',
                'format' => 'xml'])
        );
        $this->assertEquals(
            '/messages/rss.xml',
            $utils->urlFor('formatted_rss_messages', ['format' => 'xml'])
        );
    }

    public function testResourcesWithMemberAction()
    {
        foreach (['put', 'post'] as $method) {
            $m = new Mapper();
            $m->resource('message', 'messages', ['member' => ['mark' => $method]]);
            $m->createRegs(['messages']);

            $options = ['controller' => 'messages'];
            $this->assertRestfulRoutes($m, $options);

            $connectkargs = ['method' => $method, 'action' => 'mark', 'id' => '1'];
            $this->assertEquals(
                '/messages/1/mark',
                $m->generate(array_merge($connectkargs, $options))
            );

            $connectkargs = ['method' => $method, 'action' => 'mark',
                'id' => '1', 'format' => 'xml'];
            $this->assertEquals(
                '/messages/1/mark.xml',
                $m->generate(array_merge($connectkargs, $options))
            );
        }
    }

    public function testResourcesWithNewAction()
    {
        $m = new Mapper();
        $utils = $m->utils;
        $m->resource('message', 'messages/', ['new' => ['preview' => 'POST']]);
        $m->createRegs(['messages']);
        $this->assertRestfulRoutes($m, ['controller' => 'messages']);

        $this->assertEquals(
            '/messages/new/preview',
            $m->generate(['controller' => 'messages', 'action' => 'preview',
                'method' => 'post'])
        );
        $this->assertEquals(
            '/messages/new/preview',
            $utils->urlFor('preview_new_message')
        );
        $this->assertEquals(
            '/messages/new/preview.xml',
            $m->generate(['controller' => 'messages', 'action' => 'preview',
                'method' => 'post', 'format' => 'xml'])
        );
        $this->assertEquals(
            '/messages/new/preview.xml',
            $utils->urlFor('preview_new_message', ['format' => 'xml'])
        );
    }

    public function testResourcesWithNamePrefix()
    {
        $m = new Mapper();
        $utils = $m->utils;
        $m->resource('message', 'messages', ['namePrefix' => 'category_',
            'new'        => ['preview' => 'POST']]);
        $m->createRegs(['messages']);
        $options = ['controller' => 'messages'];
        $this->assertRestfulRoutes($m, $options);

        $this->assertEquals(
            '/messages/new/preview',
            $utils->urlFor('category_preview_new_message')
        );

        $this->assertNull($utils->urlFor('category_preview_new_message', ['method' => 'get']));
    }

    public function testOtherSpecialChars()
    {
        $m = new Mapper();
        $m->connect('/:year/:(slug).:(format),:(locale)', ['locale' => 'en', 'format' => 'html']);
        $m->createRegs(['content']);

        $this->assertEquals(
            '/2007/test',
            $m->generate(['year' => 2007, 'slug' => 'test'])
        );
        $this->assertEquals(
            '/2007/test.xml',
            $m->generate(['year' => 2007, 'slug' => 'test', 'format' => 'xml'])
        );
        $this->assertEquals(
            '/2007/test.xml,ja',
            $m->generate(['year' => 2007, 'slug' => 'test', 'format' => 'xml',
                'locale' => 'ja'])
        );
        $this->assertNull($m->generate(['year' => 2007, 'format' => 'html']));
    }

    // Test Helpers

    public function assertRestfulRoutes($m, $options, $pathPrefix = '')
    {
        $baseroute = '/' . $pathPrefix . $options['controller'];

        $this->assertEquals(
            $baseroute,
            $m->generate(array_merge($options, ['action' => 'index']))
        );

        $this->assertEquals(
            $baseroute . '.xml',
            $m->generate(array_merge($options, ['action' => 'index',
                'format' => 'xml']))
        );
        $this->assertEquals(
            $baseroute . '/new',
            $m->generate(array_merge($options, ['action' => 'new']))
        );
        $this->assertEquals(
            $baseroute . '/1',
            $m->generate(array_merge($options, ['action' => 'show',
                'id'     => '1']))
        );
        $this->assertEquals(
            $baseroute . '/1/edit',
            $m->generate(array_merge($options, ['action' => 'edit',
                'id'     => '1']))
        );
        $this->assertEquals(
            $baseroute . '/1.xml',
            $m->generate(array_merge($options, ['action' => 'show',
                'id'     => '1',
                'format' => 'xml']))
        );
        $this->assertEquals(
            $baseroute,
            $m->generate(array_merge($options, ['action' => 'create',
                'method' => 'post']))
        );

        $this->assertEquals(
            $baseroute . '/1',
            $m->generate(array_merge($options, ['action' => 'update',
                'method' => 'put',
                'id'     => '1']))
        );
        $this->assertEquals(
            $baseroute . '/1',
            $m->generate(array_merge($options, ['action' => 'delete',
                'method' => 'delete',
                'id'     => '1']))
        );
    }

    /**
     * Test UTF-8 path parameters encode and decode correctly
     */
    public function testUTF8PathParameters()
    {
        $m = new Mapper();
        $m->connect('users/:name', ['controller' => 'user', 'action' => 'show']);
        $m->createRegs([]);

        // Generate with UTF-8 characters
        $url = $m->generate(['controller' => 'user', 'action' => 'show', 'name' => 'José']);
        $this->assertEquals('/users/Jos%C3%A9', $url);

        // Match the encoded URL back
        $match = $m->match('/users/Jos%C3%A9');
        $this->assertEquals('José', $match['name']);

        // Test with emoji
        $url = $m->generate(['controller' => 'user', 'action' => 'show', 'name' => '😀']);
        $this->assertStringContainsString('%F0%9F%98%80', $url);
    }

    /**
     * Test UTF-8 query parameters encode correctly
     */
    public function testUTF8QueryParameters()
    {
        $utils = new \Horde\Routes\Utils(new Mapper());

        // Static route with UTF-8 query params
        $url = $utils->urlFor('/search', ['q' => 'café']);
        $this->assertStringContainsString('q=caf%C3%A9', $url);
    }

    /**
     * Test query string with special characters using http_build_query
     */
    public function testQueryStringWithSpecialCharacters()
    {
        $utils = new \Horde\Routes\Utils(new Mapper());

        // Test various special characters
        $url = $utils->urlFor('/search', [
            'q' => 'hello world',
            'filter' => 'a+b',
            'tag' => 'foo&bar',
        ]);

        // Verify proper encoding
        $this->assertStringContainsString('q=hello+world', $url);
        $this->assertStringContainsString('filter=a%2Bb', $url);
        $this->assertStringContainsString('tag=foo%26bar', $url);
    }

}
