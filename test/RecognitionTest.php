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
class RecognitionTest extends TestCase
{
    public function testRegexpCharEscaping()
    {
        $m = new Mapper();
        $m->connect(':controller/:(action).:(id)');
        $m->createRegs(['content']);

        $this->assertNull($m->match('/content/view#2'));

        $matchdata = ['action' => 'view', 'controller' => 'content', 'id' => '2'];
        $this->assertEquals($matchdata, $m->match('/content/view.2'));

        $m->connect(':controller/:action/:id');
        $m->createRegs(['content', 'find.all']);

        $matchdata = ['action' => 'view#2', 'controller' => 'content', 'id' => null];
        $this->assertEquals($matchdata, $m->match('/content/view#2'));

        $matchdata = ['action' => 'view', 'controller' => 'find.all', 'id' => null];
        $this->assertEquals($matchdata, $m->match('/find.all/view'));

        $this->assertNull($m->match('/findzall/view'));
    }

    public function testAllStatic()
    {
        $m = new Mapper();
        $m->connect('hello/world/how/are/you', ['controller' => 'content', 'action' => 'index']);
        $m->createRegs([]);

        $this->assertNull($m->match('/x'));
        $this->assertNull($m->match('/hello/world/how'));
        $this->assertNull($m->match('/hello/world/how/are'));
        $this->assertNull($m->match('/hello/world/how/are/you/today'));

        $matchdata = ['controller' => 'content', 'action' => 'index'];
        $this->assertEquals($matchdata, $m->match('/hello/world/how/are/you'));
    }

    public function testBasicDynamic()
    {
        foreach (['hi/:name', 'hi/:(name)'] as $path) {
            $m = new Mapper();
            $m->connect($path, ['controller' => 'content']);
            $m->createRegs();

            $this->assertNull($m->match('/boo'));
            $this->assertNull($m->match('/boo/blah'));
            $this->assertNull($m->match('/hi'));
            $this->assertNull($m->match('/hi/dude/what'));

            $matchdata = ['controller' => 'content', 'name' => 'dude', 'action' => 'index'];
            $this->assertEquals($matchdata, $m->match('/hi/dude'));
            $this->assertEquals($matchdata, $m->match('/hi/dude/'));
        }
    }

    public function testBasicDynamicBackwards()
    {
        foreach ([':name/hi', ':(name)/hi'] as $path) {
            $m = new Mapper();
            $m->connect($path);
            $m->createRegs();

            $this->assertNull($m->match('/'));
            $this->assertNull($m->match('/hi'));
            $this->assertNull($m->match('/boo'));
            $this->assertNull($m->match('/boo/blah'));
            $this->assertNull($m->match('/shop/walmart/hi'));

            $matchdata = ['name' => 'fred', 'action' => 'index', 'controller' => 'content'];
            $this->assertEquals($matchdata, $m->match('/fred/hi'));

            $matchdata = ['name' => 'index', 'action' => 'index', 'controller' => 'content'];
            $this->assertEquals($matchdata, $m->match('/index/hi'));
        }
    }

    public function testDynamicWithUnderscores()
    {
        $m = new Mapper();
        $m->connect('article/:small_page', ['small_page' => false]);
        $m->connect(':(controller)/:(action)/:(id)');
        $m->createRegs(['article', 'blog']);

        $matchdata = ['controller' => 'blog', 'action' => 'view', 'id' => '0'];
        $this->assertEquals($matchdata, $m->match('/blog/view/0'));

        $matchdata = ['controller' => 'blog', 'action' => 'view', 'id' => null];
        $this->assertEquals($matchdata, $m->match('/blog/view'));
    }

    public function testDynamicWithDefault()
    {
        foreach (['hi/:action', 'hi/:(action)'] as $path) {
            $m = new Mapper();
            $m->connect($path, ['controller' => 'content']);
            $m->createRegs();

            $this->assertNull($m->match('/boo'));
            $this->assertNull($m->match('/boo/blah'));
            $this->assertNull($m->match('/hi/dude/what'));

            $matchdata = ['controller' => 'content', 'action' => 'index'];
            $this->assertEquals($matchdata, $m->match('/hi'));

            $matchdata = ['controller' => 'content', 'action' => 'index'];
            $this->assertEquals($matchdata, $m->match('/hi/index'));

            $matchdata = ['controller' => 'content', 'action' => 'dude'];
            $this->assertEquals($matchdata, $m->match('/hi/dude'));
        }
    }

    public function testDynamicWithDefaultBackwards()
    {
        foreach ([':action/hi', ':(action)/hi'] as $path) {
            $m = new Mapper();
            $m->connect($path, ['controller' => 'content']);
            $m->createRegs();

            $this->assertNull($m->match('/'));
            $this->assertNull($m->match('/boo'));
            $this->assertNull($m->match('/boo/blah'));
            $this->assertNull($m->match('/hi'));

            $matchdata = ['controller' => 'content', 'action' => 'index'];
            $this->assertEquals($matchdata, $m->match('/index/hi'));
            $this->assertEquals($matchdata, $m->match('/index/hi/'));

            $matchdata = ['controller' => 'content', 'action' => 'dude'];
            $this->assertEquals($matchdata, $m->match('/dude/hi'));
        }
    }

    public function testDynamicWithStringCondition()
    {
        foreach ([':name/hi', ':(name)/hi'] as $path) {
            $m = new Mapper();
            $m->connect($path, ['controller'   => 'content',
                'requirements' => ['name' => 'index']]);
            $m->createRegs();

            $this->assertNull($m->match('/boo'));
            $this->assertNull($m->match('/boo/blah'));
            $this->assertNull($m->match('/hi'));
            $this->assertNull($m->match('/dude/what/hi'));

            $matchdata = ['controller' => 'content', 'name' => 'index', 'action' => 'index'];
            $this->assertEquals($matchdata, $m->match('/index/hi'));
            $this->assertNull($m->match('/dude/hi'));
        }
    }

    public function testDynamicWithStringConditionBackwards()
    {
        foreach (['hi/:name', 'hi/:(name)'] as $path) {
            $m = new Mapper();
            $m->connect($path, ['controller'   => 'content',
                'requirements' => ['name' => 'index']]);
            $m->createRegs();

            $this->assertNull($m->match('/boo'));
            $this->assertNull($m->match('/boo/blah'));
            $this->assertNull($m->match('/hi'));
            $this->assertNull($m->match('/hi/dude/what'));

            $matchdata = ['controller' => 'content', 'name' => 'index', 'action' => 'index'];
            $this->assertEquals($matchdata, $m->match('/hi/index'));

            $this->assertEquals($matchdata, $m->match('/hi/index'));

            $this->assertNull($m->match('/dude/hi'));
        }
    }

    public function testDynamicWithRegexpCondition()
    {
        foreach (['hi/:name', 'hi/:(name)'] as $path) {
            $m = new Mapper();
            $m->connect($path, ['controller'   => 'content',
                'requirements' => ['name' => '[a-z]+']]);
            $m->createRegs();

            $this->assertNull($m->match('/boo'));
            $this->assertNull($m->match('/boo/blah'));
            $this->assertNull($m->match('/hi'));
            $this->assertNull($m->match('/hi/FOXY'));
            $this->assertNull($m->match('/hi/138708jkhdf'));
            $this->assertNull($m->match('/hi/dkjfl8792343dfsf'));
            $this->assertNull($m->match('/hi/dude/what'));
            $this->assertNull($m->match('/hi/dude/what/'));

            $matchdata = ['controller' => 'content', 'name' => 'index', 'action' => 'index'];
            $this->assertEquals($matchdata, $m->match('/hi/index'));

            $matchdata = ['controller' => 'content', 'name' => 'dude', 'action' => 'index'];
            $this->assertEquals($matchdata, $m->match('/hi/dude'));
        }
    }

    public function testDynamicWithRegexpAndDefault()
    {
        foreach (['hi/:action', 'hi/:(action)'] as $path) {
            $m = new Mapper();
            $m->connect($path, ['controller'   => 'content',
                'requirements' => ['action' => '[a-z]+']]);
            $m->createRegs();

            $this->assertNull($m->match('/boo'));
            $this->assertNull($m->match('/boo/blah'));
            $this->assertNull($m->match('/hi/FOXY'));
            $this->assertNull($m->match('/hi/138708jkhdf'));
            $this->assertNull($m->match('/hi/dkjfl8792343dfsf'));
            $this->assertNull($m->match('/hi/dude/what/'));

            $matchdata = ['controller' => 'content', 'action' => 'index'];
            $this->assertEquals($matchdata, $m->match('/hi'));
            $this->assertEquals($matchdata, $m->match('/hi/index'));

            $matchdata = ['controller' => 'content', 'action' => 'dude'];
            $this->assertEquals($matchdata, $m->match('/hi/dude'));
        }
    }

    public function testDynamicWithDefaultAndStringConditionBackwards()
    {
        foreach ([':action/hi', ':(action)/hi'] as $path) {
            $m = new Mapper();
            $m->connect($path);
            $m->createRegs();

            $this->assertNull($m->match('/'));
            $this->assertNull($m->match('/boo'));
            $this->assertNull($m->match('/boo/blah'));
            $this->assertNull($m->match('/hi'));

            $matchdata = ['action' => 'index', 'controller' => 'content'];
            $this->assertEquals($matchdata, $m->match('/index/hi'));
        }
    }

    public function testDynamicAndControllerWithStringAndDefaultBackwards()
    {
        foreach ([':controller/:action/hi', ':(controller)/:(action)/hi'] as $path) {
            $m = new Mapper();

            $m->connect($path, ['controller' => 'content']);
            $m->createRegs(['content', 'admin/user']);

            $this->assertNull($m->match('/'));
            $this->assertNull($m->match('/fred'));
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
        $m->createRegs(['post','blog','admin/user']);

        $this->assertNull($m->match('/'));
        $this->assertNull($m->match('/archive'));
        $this->assertNull($m->match('/archive/2004/ab'));

        $matchdata = ['controller' => 'blog', 'action' => 'view', 'id' => null];
        $this->assertEquals($matchdata, $m->match('/blog/view'));

        $matchdata = ['controller' => 'blog', 'action' => 'view',
            'month' => null, 'day' => null, 'year' => '2004'];
        $this->assertEquals($matchdata, $m->match('/archive/2004'));

        $matchdata = ['controller' => 'blog', 'action' => 'view',
            'month' => '4', 'day' => null, 'year' => '2004'];
        $this->assertEquals($matchdata, $m->match('/archive/2004/4'));
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
        $m->createRegs(['post','blog','admin/user']);

        $this->assertNull($m->match('/'));
        $this->assertNull($m->match('/archive'));
        $this->assertNull($m->match('/archive/2004/ab'));

        $matchdata = ['controller' => 'blog', 'action' => 'view', 'id' => null];
        $this->assertEquals($matchdata, $m->match('/blog/view'));

        $matchdata = ['controller' => 'blog', 'action' => 'view',
            'month' => null, 'day' => null, 'year' => '2004'];
        $this->assertEquals($matchdata, $m->match('/archive/2004'));

        $matchdata = ['controller' => 'blog', 'action' => 'view',
            'month' => '4', 'day' => null, 'year' => '2004'];
        $this->assertEquals($matchdata, $m->match('/archive/2004/4'));
    }

    public function testDynamicWithRegexpDefaultsAndGaps()
    {
        $m = new Mapper();
        $m->connect('archive/:year/:month/:day', ['controller' => 'blog', 'action' => 'view',
            'month' => null, 'day' => null,
            'requirements' => ['month' => '\d{1,2}']]);
        $m->connect('view/:id/:controller', ['controller' => 'blog', 'action' => 'view',
            'id' => 2, 'requirements' => ['id' => '\d{1,2}']]);
        $m->createRegs(['post','blog','admin/user']);

        $this->assertNull($m->match('/'));
        $this->assertNull($m->match('/archive'));
        $this->assertNull($m->match('/archive/2004/haha'));
        $this->assertNull($m->match('/view/blog'));

        $matchdata = ['controller' => 'blog', 'action' => 'view', 'id' => '2'];
        $this->assertEquals($matchdata, $m->match('/view'));

        $matchdata = ['controller' => 'blog', 'action' => 'view',
            'month' => null, 'day' => null, 'year' => '2004'];
        $this->assertEquals($matchdata, $m->match('/archive/2004'));
    }

    public function testDynamicWithRegexpDefaultsAndGapsAndSplits()
    {
        $m = new Mapper();
        $m->connect('archive/:(year)/:(month)/:(day)', ['controller' => 'blog', 'action' => 'view',
            'month' => null, 'day' => null,
            'requirements' => ['month' => '\d{1,2}']]);
        $m->connect('view/:(id)/:(controller)', ['controller' => 'blog', 'action' => 'view',
            'id' => 2, 'requirements' => ['id' => '\d{1,2}']]);
        $m->createRegs(['post','blog','admin/user']);

        $this->assertNull($m->match('/'));
        $this->assertNull($m->match('/archive'));
        $this->assertNull($m->match('/archive/2004/haha'));
        $this->assertNull($m->match('/view/blog'));

        $matchdata = ['controller' => 'blog', 'action' => 'view', 'id' => '2'];
        $this->assertEquals($matchdata, $m->match('/view'));

        $matchdata = ['controller' => 'blog', 'action' => 'view',
            'month' => null, 'day' => null, 'year' => '2004'];
        $this->assertEquals($matchdata, $m->match('/archive/2004'));
    }

    public function testDynamicWithRegexpGapsControllers()
    {
        foreach (['view/:id/:controller', 'view/:(id)/:(controller)'] as $path) {
            $m = new Mapper();
            $m->connect($path, ['id' => 2, 'action' => 'view', 'requirements' => ['id' => '\d{1,2}']]);
            $m->createRegs(['post', 'blog', 'admin/user']);

            $this->assertNull($m->match('/'));
            $this->assertNull($m->match('/view'));
            $this->assertNull($m->match('/view/blog'));
            $this->assertNull($m->match('/view/3'));
            $this->assertNull($m->match('/view/4/honker'));

            $matchdata = ['controller' => 'blog', 'action' => 'view', 'id' => '2'];
            $this->assertEquals($matchdata, $m->match('/view/2/blog'));
        }
    }

    public function testDynamicWithTrailingStrings()
    {
        foreach (['view/:id/:controller/super', 'view/:(id)/:(controller)/super'] as $path) {
            $m = new Mapper();
            $m->connect($path, ['controller' => 'blog', 'action' => 'view',
                'id' => 2, 'requirements' => ['id' => '\d{1,2}']]);
            $m->createRegs(['post', 'blog', 'admin/user']);

            $this->assertNull($m->match('/'));
            $this->assertNull($m->match('/view'));
            $this->assertNull($m->match('/view/blah/blog/super'));
            $this->assertNull($m->match('/view/ha/super'));
            $this->assertNull($m->match('/view/super'));
            $this->assertNull($m->match('/view/4/super'));

            $matchdata = ['controller' => 'blog', 'action' => 'view', 'id' => '2'];
            $this->assertEquals($matchdata, $m->match('/view/2/blog/super'));

            $matchdata = ['controller' => 'admin/user', 'action' => 'view', 'id' => '4'];
            $this->assertEquals($matchdata, $m->match('/view/4/admin/user/super'));
        }
    }

    public function testDynamicWithTrailingNonKeywordStrings()
    {
        $m = new Mapper();
        $m->connect('somewhere/:over/rainbow', ['controller' => 'blog']);
        $m->connect('somewhere/:over', ['controller' => 'post']);
        $m->createRegs(['post', 'blog', 'admin/user']);

        $this->assertNull($m->match('/'));
        $this->assertNull($m->match('/somewhere'));

        $matchdata = ['controller' => 'blog', 'action' => 'index', 'over' => 'near'];
        $this->assertEquals($matchdata, $m->match('/somewhere/near/rainbow'));

        $matchdata = ['controller' => 'post', 'action' => 'index', 'over' => 'tomorrow'];
        $this->assertEquals($matchdata, $m->match('/somewhere/tomorrow'));
    }

    public function testDynamicWithTrailingDynamicDefaults()
    {
        foreach (['archives/:action/:article', 'archives/:(action)/:(article)'] as $path) {
            $m = new Mapper();
            $m->connect($path, ['controller' => 'blog']);
            $m->createRegs(['blog']);

            $this->assertNull($m->match('/'));
            $this->assertNull($m->match('/archives'));
            $this->assertNull($m->match('/archives/introduction'));
            $this->assertNull($m->match('/archives/sample'));
            $this->assertNull($m->match('/view/super'));
            $this->assertNull($m->match('/view/4/super'));

            $matchdata = ['controller' => 'blog', 'action' => 'view', 'article' => 'introduction'];
            $this->assertEquals($matchdata, $m->match('/archives/view/introduction'));

            $matchdata = ['controller' => 'blog', 'action' => 'edit', 'article' => 'recipes'];
            $this->assertEquals($matchdata, $m->match('/archives/edit/recipes'));
        }
    }

    public function testPath()
    {
        foreach (['hi/*file', 'hi/*(file)'] as $path) {
            $m = new Mapper();
            $m->connect($path, ['controller' => 'content', 'action' => 'download']);
            $m->createRegs();

            $this->assertNull($m->match('/boo'));
            $this->assertNull($m->match('/boo/blah'));
            $this->assertNull($m->match('/hi'));

            $matchdata = ['controller' => 'content', 'action' => 'download',
                'file' => 'books/learning_python.pdf'];
            $this->assertEquals($matchdata, $m->match('/hi/books/learning_python.pdf'));

            $matchdata = ['controller' => 'content', 'action' => 'download',
                'file' => 'dude'];
            $this->assertEquals($matchdata, $m->match('/hi/dude'));

            $matchdata = ['controller' => 'content', 'action' => 'download',
                'file' => 'dude/what'];
            $this->assertEquals($matchdata, $m->match('/hi/dude/what'));
        }
    }

    public function testDynamicWithPath()
    {
        foreach ([':controller/:action/*url', ':(controller)/:(action)/*(url)'] as $path) {
            $m = new Mapper();
            $m->connect($path);
            $m->createRegs(['content', 'admin/user']);

            $this->assertNull($m->match('/'));
            $this->assertNull($m->match('/blog'));
            $this->assertNull($m->match('/content'));
            $this->assertNull($m->match('/content/view'));

            $matchdata = ['controller' => 'content', 'action' => 'view', 'url' => 'blob'];
            $this->assertEquals($matchdata, $m->match('/content/view/blob'));

            $this->assertNull($m->match('/admin/user'));
            $this->assertNull($m->match('/admin/user/view'));

            $matchdata = ['controller' => 'admin/user', 'action' => 'view',
                'url' => 'blob/check'];
            $this->assertEquals($matchdata, $m->match('/admin/user/view/blob/check'));
        }
    }

    public function testPathWithDynamicAndDefault()
    {
        foreach ([':controller/:action/*url', ':(controller)/:(action)/*(url)'] as $path) {
            $m = new Mapper();
            $m->connect($path, ['controller' => 'content', 'action' => 'view', 'url' => null]);
            $m->createRegs(['content', 'admin/user']);

            $this->assertNull($m->match('/goober/view/here'));

            $matchdata = ['controller' => 'content', 'action' => 'view', 'url' => null];
            $this->assertEquals($matchdata, $m->match('/content'));
            $this->assertEquals($matchdata, $m->match('/content/'));
            $this->assertEquals($matchdata, $m->match('/content/view'));

            $matchdata = ['controller' => 'content', 'action' => 'view', 'url' => 'fred'];
            $this->assertEquals($matchdata, $m->match('/content/view/fred'));

            $matchdata = ['controller' => 'admin/user', 'action' => 'view', 'url' => null];
            $this->assertEquals($matchdata, $m->match('/admin/user'));
            $this->assertEquals($matchdata, $m->match('/admin/user/view'));
        }
    }

    public function testPathWithDynamicAndDefaultBackwards()
    {
        foreach (['*file/login', '*(file)/login'] as $path) {
            $m = new Mapper();
            $m->connect($path, ['controller' => 'content', 'action' => 'download', 'file' => null]);
            $m->createRegs();

            $this->assertNull($m->match('/boo'));
            $this->assertNull($m->match('/boo/blah'));

            $matchdata = ['controller' => 'content', 'action' => 'download', 'file' => ''];
            $this->assertEquals($matchdata, $m->match('//login'));

            $matchdata = ['controller' => 'content', 'action' => 'download',
                'file' => 'books/learning_python.pdf'];
            $this->assertEquals($matchdata, $m->match('/books/learning_python.pdf/login'));

            $matchdata = ['controller' => 'content', 'action' => 'download', 'file' => 'dude'];
            $this->assertEquals($matchdata, $m->match('/dude/login'));

            $matchdata = ['controller' => 'content', 'action' => 'download', 'file' => 'dude/what'];
            $this->assertEquals($matchdata, $m->match('/dude/what/login'));
        }
    }

    public function testPathBackwards()
    {
        foreach (['*file/login', '*(file)/login'] as $path) {
            $m = new Mapper();
            $m->connect($path, ['controller' => 'content', 'action' => 'download']);
            $m->createRegs();

            $this->assertNull($m->match('/boo'));
            $this->assertNull($m->match('/boo/blah'));
            $this->assertNull($m->match('/login'));

            $matchdata = ['controller' => 'content', 'action' => 'download',
                'file' => 'books/learning_python.pdf'];
            $this->assertEquals($matchdata, $m->match('/books/learning_python.pdf/login'));

            $matchdata = ['controller' => 'content', 'action' => 'download', 'file' => 'dude'];
            $this->assertEquals($matchdata, $m->match('/dude/login'));

            $matchdata = ['controller' => 'content', 'action' => 'download', 'file' => 'dude/what'];
            $this->assertEquals($matchdata, $m->match('/dude/what/login'));
        }
    }

    public function testPathBackwardsWithController()
    {
        $m = new Mapper();
        $m->connect('*url/login', ['controller' => 'content', 'action' => 'check_access']);
        $m->connect('*url/:controller', ['action' => 'view']);
        $m->createRegs(['content', 'admin/user']);

        $this->assertNull($m->match('/boo'));
        $this->assertNull($m->match('/boo/blah'));
        $this->assertNull($m->match('/login'));

        $matchdata = ['controller' => 'content', 'action' => 'check_access',
            'url' => 'books/learning_python.pdf'];
        $this->assertEquals($matchdata, $m->match('/books/learning_python.pdf/login'));

        $matchdata = ['controller' => 'content', 'action' => 'check_access', 'url' => 'dude'];
        $this->assertEquals($matchdata, $m->match('/dude/login'));

        $matchdata = ['controller' => 'content', 'action' => 'check_access', 'url' => 'dude/what'];
        $this->assertEquals($matchdata, $m->match('/dude/what/login'));

        $this->assertNull($m->match('/admin/user'));

        $matchdata = ['controller' => 'admin/user', 'action' => 'view',
            'url' => 'books/learning_python.pdf'];
        $this->assertEquals($matchdata, $m->match('/books/learning_python.pdf/admin/user'));

        $matchdata = ['controller' => 'admin/user', 'action' => 'view', 'url' => 'dude'];
        $this->assertEquals($matchdata, $m->match('/dude/admin/user'));

        $matchdata = ['controller' => 'admin/user', 'action' => 'view', 'url' => 'dude/what'];
        $this->assertEquals($matchdata, $m->match('/dude/what/admin/user'));
    }

    public function testPathBackwardsWithControllerAndSplits()
    {
        $m = new Mapper();
        $m->connect('*(url)/login', ['controller' => 'content', 'action' => 'check_access']);
        $m->connect('*(url)/:(controller)', ['action' => 'view']);
        $m->createRegs(['content', 'admin/user']);

        $this->assertNull($m->match('/boo'));
        $this->assertNull($m->match('/boo/blah'));
        $this->assertNull($m->match('/login'));

        $matchdata = ['controller' => 'content', 'action' => 'check_access',
            'url' => 'books/learning_python.pdf'];
        $this->assertEquals($matchdata, $m->match('/books/learning_python.pdf/login'));

        $matchdata = ['controller' => 'content', 'action' => 'check_access', 'url' => 'dude'];
        $this->assertEquals($matchdata, $m->match('/dude/login'));

        $matchdata = ['controller' => 'content', 'action' => 'check_access', 'url' => 'dude/what'];
        $this->assertEquals($matchdata, $m->match('/dude/what/login'));

        $this->assertNull($m->match('/admin/user'));

        $matchdata = ['controller' => 'admin/user', 'action' => 'view',
            'url' => 'books/learning_python.pdf'];
        $this->assertEquals($matchdata, $m->match('/books/learning_python.pdf/admin/user'));

        $matchdata = ['controller' => 'admin/user', 'action' => 'view', 'url' => 'dude'];
        $this->assertEquals($matchdata, $m->match('/dude/admin/user'));

        $matchdata = ['controller' => 'admin/user', 'action' => 'view', 'url' => 'dude/what'];
        $this->assertEquals($matchdata, $m->match('/dude/what/admin/user'));
    }

    public function testController()
    {
        $m = new Mapper();
        $m->connect('hi/:controller', ['action' => 'hi']);
        $m->createRegs(['content', 'admin/user']);

        $this->assertNull($m->match('/boo'));
        $this->assertNull($m->match('/boo/blah'));
        $this->assertNull($m->match('/hi/13870948'));
        $this->assertNull($m->match('/hi/content/dog'));
        $this->assertNull($m->match('/hi/admin/user/foo'));
        $this->assertNull($m->match('/hi/admin/user/foo/'));

        $matchdata = ['controller' => 'content', 'action' => 'hi'];
        $this->assertEquals($matchdata, $m->match('/hi/content'));

        $matchdata = ['controller' => 'admin/user', 'action' => 'hi'];
        $this->assertEquals($matchdata, $m->match('/hi/admin/user'));
    }

    public function testStandardRoute()
    {
        $m = new Mapper();
        $m->connect(':controller/:action/:id');
        $m->createRegs(['content', 'admin/user']);

        $matchdata = ['controller' => 'content', 'action' => 'index', 'id' => null];
        $this->assertEquals($matchdata, $m->match('/content'));

        $matchdata = ['controller' => 'content', 'action' => 'list', 'id' => null];
        $this->assertEquals($matchdata, $m->match('/content/list'));

        $matchdata = ['controller' => 'content', 'action' => 'show', 'id' => '10'];
        $this->assertEquals($matchdata, $m->match('/content/show/10'));

        $matchdata = ['controller' => 'admin/user', 'action' => 'index', 'id' => null];
        $this->assertEquals($matchdata, $m->match('/admin/user'));

        $matchdata = ['controller' => 'admin/user', 'action' => 'list', 'id' => null];
        $this->assertEquals($matchdata, $m->match('/admin/user/list'));

        $matchdata = ['controller' => 'admin/user', 'action' => 'show', 'id' => 'bbangert'];
        $this->assertEquals($matchdata, $m->match('/admin/user/show/bbangert'));

        $this->assertNull($m->match('/content/show/10/20'));
        $this->assertNull($m->match('/food'));
    }

    public function testStandardRouteWithGaps()
    {
        $m = new Mapper();
        $m->connect(':controller/:action/:(id).py');
        $m->createRegs(['content', 'admin/user']);

        $matchdata = ['controller' => 'content', 'action' => 'index', 'id' => 'None'];
        $this->assertEquals($matchdata, $m->match('/content/index/None.py'));

        $matchdata = ['controller' => 'content', 'action' => 'list', 'id' => 'None'];
        $this->assertEquals($matchdata, $m->match('/content/list/None.py'));

        $matchdata = ['controller' => 'content', 'action' => 'show', 'id' => '10'];
        $this->assertEquals($matchdata, $m->match('/content/show/10.py'));
    }

    public function testStandardRouteWithGapsAndDomains()
    {
        $m = new Mapper();
        $m->connect('manage/:domain.:ext', ['controller' => 'admin/user', 'action' => 'view',
            'ext' => 'html']);
        $m->connect(':controller/:action/:id');
        $m->createRegs(['content', 'admin/user']);

        $matchdata = ['controller' => 'content', 'action' => 'index', 'id' => 'None.py'];
        $this->assertEquals($matchdata, $m->match('/content/index/None.py'));

        $matchdata = ['controller' => 'content', 'action' => 'list', 'id' => 'None.py'];
        $this->assertEquals($matchdata, $m->match('/content/list/None.py'));

        $matchdata = ['controller' => 'content', 'action' => 'show', 'id' => '10.py'];
        $this->assertEquals($matchdata, $m->match('/content/show/10.py'));

        $matchdata = ['controller' => 'content', 'action' => 'show.all', 'id' => '10.py'];
        $this->assertEquals($matchdata, $m->match('/content/show.all/10.py'));

        $matchdata = ['controller' => 'content', 'action' => 'show', 'id' => 'www.groovie.org'];
        $this->assertEquals($matchdata, $m->match('/content/show/www.groovie.org'));

        $matchdata = ['controller' => 'admin/user', 'action' => 'view',
            'ext' => 'html', 'domain' => 'groovie'];
        $this->assertEquals($matchdata, $m->match('/manage/groovie'));

        $matchdata = ['controller' => 'admin/user', 'action' => 'view',
            'ext' => 'xml', 'domain' => 'groovie'];
        $this->assertEquals($matchdata, $m->match('/manage/groovie.xml'));
    }

    public function testStandardWithDomains()
    {
        $m = new Mapper();
        $m->connect('manage/:domain', ['controller' => 'domains', 'action' => 'view']);
        $m->createRegs(['domains']);

        $matchdata = ['controller' => 'domains', 'action' => 'view', 'domain' => 'www.groovie.org'];
        $this->assertEquals($matchdata, $m->match('/manage/www.groovie.org'));
    }

    public function testDefaultRoute()
    {
        $m = new Mapper();
        $m->connect('', ['controller' => 'content', 'action' => 'index']);
        $m->createRegs(['content']);

        $this->assertNull($m->match('/x'));
        $this->assertNull($m->match('/hello/world'));
        $this->assertNull($m->match('/hello/world/how/are'));
        $this->assertNull($m->match('/hello/world/how/are/you/today'));

        $matchdata = ['controller' => 'content', 'action' => 'index'];
        $this->assertEquals($matchdata, $m->match('/'));
    }

    public function testDynamicWithPrefix()
    {
        $m = new Mapper();
        $m->prefix = '/blog';
        $m->connect(':controller/:action/:id');
        $m->connect('', ['controller' => 'content', 'action' => 'index']);
        $m->createRegs(['content', 'archive', 'admin/comments']);

        $this->assertNull($m->match('/x'));
        $this->assertNull($m->match('/admin/comments'));
        $this->assertNull($m->match('/content/view'));
        $this->assertNull($m->match('/archive/view/4'));

        $matchdata = ['controller' => 'content', 'action' => 'index'];
        $this->assertEquals($matchdata, $m->match('/blog'));

        $matchdata = ['controller' => 'content', 'action' => 'index', 'id' => null];
        $this->assertEquals($matchdata, $m->match('/blog/content'));

        $matchdata = ['controller' => 'admin/comments', 'action' => 'view', 'id' => null];
        $this->assertEquals($matchdata, $m->match('/blog/admin/comments/view'));

        $matchdata = ['controller' => 'archive', 'action' => 'index', 'id' => null];
        $this->assertEquals($matchdata, $m->match('/blog/archive'));

        $matchdata = ['controller' => 'archive', 'action' => 'view', 'id' => '4'];
        $this->assertEquals($matchdata, $m->match('/blog/archive/view/4'));
    }

    public function testDynamicWithMultipleAndPrefix()
    {
        $m = new Mapper();
        $m->prefix = '/blog';
        $m->connect(':controller/:action/:id');
        $m->connect('home/:action', ['controller' => 'archive']);
        $m->connect('', ['controller' => 'content']);
        $m->createRegs(['content', 'archive', 'admin/comments']);

        $this->assertNull($m->match('/x'));
        $this->assertNull($m->match('/admin/comments'));
        $this->assertNull($m->match('/content/view'));
        $this->assertNull($m->match('/archive/view/4'));

        $matchdata = ['controller' => 'content', 'action' => 'index'];
        $this->assertEquals($matchdata, $m->match('/blog/'));

        $matchdata = ['controller' => 'archive', 'action' => 'view'];
        $this->assertEquals($matchdata, $m->match('/blog/home/view'));

        $matchdata = ['controller' => 'content', 'action' => 'index', 'id' => null];
        $this->assertEquals($matchdata, $m->match('/blog/content'));

        $matchdata = ['controller' => 'admin/comments', 'action' => 'view', 'id' => null];
        $this->assertEquals($matchdata, $m->match('/blog/admin/comments/view'));

        $matchdata = ['controller' => 'archive', 'action' => 'index', 'id' => null];
        $this->assertEquals($matchdata, $m->match('/blog/archive'));

        $matchdata = ['controller' => 'archive', 'action' => 'view', 'id' => '4'];
        $this->assertEquals($matchdata, $m->match('/blog/archive/view/4'));
    }

    public function testSplitsWithExtension()
    {
        $m = new Mapper();
        $m->connect('hi/:(action).html', ['controller' => 'content']);
        $m->createRegs();

        $this->assertNull($m->match('/boo'));
        $this->assertNull($m->match('/boo/blah'));
        $this->assertNull($m->match('/hi/dude/what'));
        $this->assertNull($m->match('/hi'));

        $matchdata = ['controller' => 'content', 'action' => 'index'];
        $this->assertEquals($matchdata, $m->match('/hi/index.html'));

        $matchdata = ['controller' => 'content', 'action' => 'dude'];
        $this->assertEquals($matchdata, $m->match('/hi/dude.html'));
    }

    public function testSplitsWithDashes()
    {
        $m = new Mapper();
        $m->connect(
            'archives/:(year)-:(month)-:(day).html',
            ['controller' => 'archives', 'action' => 'view']
        );
        $m->createRegs();

        $this->assertNull($m->match('/boo'));
        $this->assertNull($m->match('/archives'));

        $matchdata = ['controller' => 'archives', 'action' => 'view',
            'year' => '2004', 'month' => '12', 'day' => '4'];
        $this->assertEquals($matchdata, $m->match('/archives/2004-12-4.html'));

        $matchdata = ['controller' => 'archives', 'action' => 'view',
            'year' => '04', 'month' => '10', 'day' => '4'];
        $this->assertEquals($matchdata, $m->match('/archives/04-10-4.html'));

        $matchdata = ['controller' => 'archives', 'action' => 'view',
            'year' => '04', 'month' => '1', 'day' => '1'];
        $this->assertEquals($matchdata, $m->match('/archives/04-1-1.html'));
    }

    public function testSplitsPackedWithRegexps()
    {
        $m = new Mapper();
        $m->connect(
            'archives/:(year):(month):(day).html',
            ['controller' => 'archives', 'action' => 'view',
                'requirements' => ['year' => '\d{4}', 'month' => '\d{2}',
                    'day'  => '\d{2}']]
        );
        $m->createRegs();

        $this->assertNull($m->match('/boo'));
        $this->assertNull($m->match('/archives'));
        $this->assertNull($m->match('/archives/2004020.html'));
        $this->assertNull($m->match('/archives/200502.html'));

        $matchdata = ['controller' => 'archives', 'action' => 'view',
            'year' => '2004', 'month' => '12', 'day' => '04'];
        $this->assertEquals($matchdata, $m->match('/archives/20041204.html'));

        $matchdata = ['controller' => 'archives', 'action' => 'view',
            'year' => '2005', 'month' => '10', 'day' => '04'];
        $this->assertEquals($matchdata, $m->match('/archives/20051004.html'));

        $matchdata = ['controller' => 'archives', 'action' => 'view',
            'year' => '2006', 'month' => '01', 'day' => '01'];
        $this->assertEquals($matchdata, $m->match('/archives/20060101.html'));
    }

    public function testSplitsWithSlashes()
    {
        $m = new Mapper();
        $m->connect(':name/:(action)-:(day)', ['controller' => 'content']);

        $this->assertNull($m->match('/something'));
        $this->assertNull($m->match('/something/is-'));

        $matchdata = ['controller' => 'content', 'action' => 'view',
            'day' => '3', 'name' => 'group'];
        $this->assertEquals($matchdata, $m->match('/group/view-3'));

        $matchdata = ['controller' => 'content', 'action' => 'view',
            'day' => '5', 'name' => 'group'];
        $this->assertEquals($matchdata, $m->match('/group/view-5'));
    }

    public function testSplitsWithSlashesAndDefault()
    {
        $m = new Mapper();
        $m->connect(':name/:(action)-:(id)', ['controller' => 'content']);
        $m->createRegs();

        $this->assertNull($m->match('/something'));
        $this->assertNull($m->match('/something/is'));

        $matchdata = ['controller' => 'content', 'action' => 'view',
            'id' => '3', 'name' => 'group'];
        $this->assertEquals($matchdata, $m->match('/group/view-3'));

        $matchdata = ['controller' => 'content', 'action' => 'view',
            'id' => null, 'name' => 'group'];
        $this->assertEquals($matchdata, $m->match('/group/view-'));
    }

    public function testNoRegMake()
    {
        $m = new Mapper();
        $m->connect(':name/:(action)-:(id)', ['controller' => 'content']);
        $m->controllerScan = false;

        $this->expectException('Exception');
        $m->match('/group/view-3');
        $this->assertRegExp('/must generate the regular expressions/i', $e->getMessage());
    }

    public function testRoutematch()
    {
        $m = new Mapper();
        $m->connect(':controller/:action/:id');
        $m->createRegs(['content']);
        $route = $m->matchList[0];

        [$resultdict, $resultObj] = $m->routematch('/content');

        $this->assertEquals(
            ['controller' => 'content', 'action' => 'index', 'id' => null],
            $resultdict
        );
        $this->assertSame($route, $resultObj);
        $this->assertNull($m->routematch('/nowhere'));
    }

    public function testRoutematchDebug()
    {
        $m = new Mapper();
        $m->connect(':controller/:action/:id');
        $m->debug = true;
        $m->createRegs(['content']);
        $route = $m->matchList[0];

        [$resultdict, $resultObj, $debug] = $m->routematch('/content');

        $this->assertEquals(
            ['controller' => 'content', 'action' => 'index', 'id' => null],
            $resultdict
        );
        $this->assertSame($route, $resultObj);

        [$resultdict, $resultObj, $debug] = $m->routematch('/nowhere');
        $this->assertNull($resultdict);
        $this->assertNull($resultObj);
        $this->assertEquals(1, count($debug));
    }

    public function testMatchDebug()
    {
        $m = new Mapper();
        $m->connect('nowhere', 'http://nowhere.com/', ['_static' => true]);
        $m->connect(':controller/:action/:id');
        $m->debug = true;
        $m->createRegs(['content']);
        $route = $m->matchList[1];

        [$resultdict, $resultObj, $debug] = $m->match('/content');
        $this->assertEquals(
            ['controller' => 'content', 'action' => 'index', 'id' => null],
            $resultdict
        );

        $this->assertSame($route, $resultObj);

        [$resultdict, $resultObj, $debug] = $m->match('/nowhere');
        $this->assertNull($resultdict);
        $this->assertNull($resultObj);
        $this->assertEquals(2, count($debug));
    }

    public function testResourceCollection()
    {
        $m = new Mapper();
        $m->resource('message', 'messages');
        $m->createRegs(['messages']);

        $path = '/messages';

        $m->environ = ['REQUEST_METHOD' => 'GET'];
        $this->assertEquals(
            ['controller' => 'messages', 'action' => 'index'],
            $m->match($path)
        );

        $m->environ = ['REQUEST_METHOD' => 'POST'];
        $this->assertEquals(
            ['controller' => 'messages', 'action' => 'create'],
            $m->match($path)
        );
    }

    public function testFormattedResourceCollection()
    {
        $m = new Mapper();
        $m->resource('message', 'messages');
        $m->createRegs(['messages']);

        $path = '/messages.xml';

        $m->environ = ['REQUEST_METHOD' => 'GET'];
        $this->assertEquals(
            ['controller' => 'messages', 'action' => 'index',
                'format' => 'xml'],
            $m->match($path)
        );

        $m->environ = ['REQUEST_METHOD' => 'POST'];
        $this->assertEquals(
            ['controller' => 'messages', 'action' => 'create',
                'format' => 'xml'],
            $m->match($path)
        );
    }

    public function testResourceMember()
    {
        $m = new Mapper();
        $m->resource('message', 'messages');
        $m->createRegs(['messages']);

        $path = '/messages/42';

        $m->environ = ['REQUEST_METHOD' => 'GET'];
        $this->assertEquals(
            ['controller' => 'messages', 'action' => 'show',
                'id' => 42],
            $m->match($path)
        );

        $m->environ = ['REQUEST_METHOD' => 'POST'];
        $this->assertNull($m->match($path));

        $m->environ = ['REQUEST_METHOD' => 'PUT'];
        $this->assertEquals(
            ['controller' => 'messages', 'action' => 'update',
                'id' => 42],
            $m->match($path)
        );

        $m->environ = ['REQUEST_METHOD' => 'DELETE'];
        $this->assertEquals(
            ['controller' => 'messages', 'action' => 'delete',
                'id' => 42],
            $m->match($path)
        );
    }

    public function testFormattedResourceMember()
    {
        $m = new Mapper();
        $m->resource('message', 'messages');
        $m->createRegs(['messages']);

        $path = '/messages/42.xml';

        $m->environ = ['REQUEST_METHOD' => 'GET'];
        $this->assertEquals(
            ['controller' => 'messages', 'action' => 'show',
                'id' => 42, 'format' => 'xml'],
            $m->match($path)
        );

        $m->environ = ['REQUEST_METHOD' => 'POST'];
        $this->assertNull($m->match($path));

        $m->environ = ['REQUEST_METHOD' => 'PUT'];
        $this->assertEquals(
            ['controller' => 'messages', 'action' => 'update',
                'id' => 42, 'format' => 'xml'],
            $m->match($path)
        );

        $m->environ = ['REQUEST_METHOD' => 'DELETE'];
        $this->assertEquals(
            ['controller' => 'messages', 'action' => 'delete',
                'id' => 42, 'format' => 'xml'],
            $m->match($path)
        );
    }

}
