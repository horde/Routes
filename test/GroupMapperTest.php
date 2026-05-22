<?php

declare(strict_types=1);

namespace Horde\Routes\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde\Routes\GroupMapper;
use Horde\Routes\GroupFluentRouteBuilder;
use Horde\Routes\Route;
use BadMethodCallException;

#[CoversClass(GroupMapper::class)]
#[CoversClass(GroupFluentRouteBuilder::class)]
class GroupMapperTest extends TestCase
{
    #[Test]
    public function buildRouteWithoutGroup(): void
    {
        $m = new GroupMapper();
        $m->buildRoute('/users/:id')
            ->withController('UserController')
            ->withAction('show')
            ->add();
        $m->compile();

        $result = $m->match('/users/42');
        $this->assertIsArray($result);
        $this->assertSame('UserController', $result['controller']);
        $this->assertSame('show', $result['action']);
        $this->assertSame('42', $result['id']);
    }

    #[Test]
    public function groupAppliesPrefix(): void
    {
        $m = new GroupMapper();
        $m->group(['prefix' => '/nag'], function (GroupMapper $m) {
            $m->buildRoute('/tasks/:id')
                ->withController('TaskController')
                ->withAction('view')
                ->add();
        });
        $m->compile();

        $result = $m->match('/nag/tasks/7');
        $this->assertIsArray($result);
        $this->assertSame('TaskController', $result['controller']);
        $this->assertSame('7', $result['id']);

        // Without prefix should NOT match
        $this->assertNull($m->match('/tasks/7'));
    }

    #[Test]
    public function groupAppliesDefaults(): void
    {
        $m = new GroupMapper();
        $m->group(['prefix' => '/nag', 'defaults' => ['app' => 'nag']], function (GroupMapper $m) {
            $m->buildRoute('/tasks/:id')
                ->withController('TaskController')
                ->withAction('view')
                ->add();
        });
        $m->compile();

        $result = $m->match('/nag/tasks/1');
        $this->assertSame('nag', $result['app']);
    }

    #[Test]
    public function groupAppliesStack(): void
    {
        $m = new GroupMapper();
        $m->group(['prefix' => '/app', 'stack' => ['Auth', 'Log']], function (GroupMapper $m) {
            $m->buildRoute('/page')
                ->withController('PageController')
                ->withAction('index')
                ->add();
        });
        $m->compile();

        $result = $m->match('/app/page');
        $this->assertSame(['Auth', 'Log'], $result['stack']);
    }

    #[Test]
    public function builderOverridesGroupStack(): void
    {
        $m = new GroupMapper();
        $m->group(['prefix' => '/app', 'stack' => ['Auth', 'Log']], function (GroupMapper $m) {
            $m->buildRoute('/api/data')
                ->withController('ApiController')
                ->withAction('get')
                ->withMiddleware(['ApiKey'])
                ->add();
        });
        $m->compile();

        $result = $m->match('/app/api/data');
        $this->assertSame(['ApiKey'], $result['stack']);
    }

    #[Test]
    public function noMiddlewareOverridesGroupStack(): void
    {
        $m = new GroupMapper();
        $m->group(['prefix' => '/app', 'stack' => ['Auth', 'Log']], function (GroupMapper $m) {
            $m->buildRoute('/health')
                ->withController('HealthController')
                ->withAction('check')
                ->noMiddleware()
                ->add();
        });
        $m->compile();

        $result = $m->match('/app/health');
        $this->assertSame([], $result['stack']);
    }

    #[Test]
    public function defaultStackAppliesWhenNoGroupStack(): void
    {
        $m = new GroupMapper();
        $m->setDefaultStack(['DefaultMw']);
        $m->buildRoute('/open')
            ->withController('OpenController')
            ->withAction('index')
            ->add();
        $m->compile();

        $result = $m->match('/open');
        $this->assertSame(['DefaultMw'], $result['stack']);
    }

    #[Test]
    public function nestedGroupsPrefixConcatenates(): void
    {
        $m = new GroupMapper();
        $m->group(['prefix' => '/nag'], function (GroupMapper $m) {
            $m->group(['prefix' => '/api'], function (GroupMapper $m) {
                $m->buildRoute('/tasks/:id')
                    ->withController('ApiTaskController')
                    ->withAction('get')
                    ->add();
            });
        });
        $m->compile();

        $result = $m->match('/nag/api/tasks/5');
        $this->assertIsArray($result);
        $this->assertSame('ApiTaskController', $result['controller']);
        $this->assertSame('5', $result['id']);
    }

    #[Test]
    public function nestedGroupInnerStackOverridesOuter(): void
    {
        $m = new GroupMapper();
        $m->group(['prefix' => '/app', 'stack' => ['Auth']], function (GroupMapper $m) {
            $m->group(['prefix' => '/api', 'stack' => ['ApiKey']], function (GroupMapper $m) {
                $m->buildRoute('/data')
                    ->withController('DataController')
                    ->withAction('list')
                    ->add();
            });
        });
        $m->compile();

        $result = $m->match('/app/api/data');
        $this->assertSame(['ApiKey'], $result['stack']);
    }

    #[Test]
    public function nestedGroupDefaultsMerge(): void
    {
        $m = new GroupMapper();
        $m->group(['prefix' => '/nag', 'defaults' => ['app' => 'nag']], function (GroupMapper $m) {
            $m->group(['defaults' => ['format' => 'json']], function (GroupMapper $m) {
                $m->buildRoute('/tasks')
                    ->withController('TaskController')
                    ->withAction('list')
                    ->add();
            });
        });
        $m->compile();

        $result = $m->match('/nag/tasks');
        $this->assertSame('nag', $result['app']);
        $this->assertSame('json', $result['format']);
    }

    #[Test]
    public function connectThrowsWithMessage(): void
    {
        $m = new GroupMapper();
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageMatches('/connect\(\) is not supported/');
        $m->connect('/users/:id', ['controller' => 'User']);
    }

    #[Test]
    public function groupAppliesHostSchemePort(): void
    {
        $m = new GroupMapper();
        $m->group([
            'prefix' => '/secure',
            'host' => 'admin.example.com',
            'scheme' => 'https',
            'port' => 8443,
        ], function (GroupMapper $m) {
            $m->buildRoute('/panel')
                ->withController('AdminController')
                ->withAction('index')
                ->add();
        });
        $m->compile();

        // Correct environ matches
        $m->environ = [
            'HTTP_HOST' => 'admin.example.com:8443',
            'HTTPS' => 'on',
            'REQUEST_METHOD' => 'GET',
        ];
        $result = $m->match('/secure/panel');
        $this->assertIsArray($result);
        $this->assertSame('AdminController', $result['controller']);

        // Wrong host doesn't match
        $m->environ = [
            'HTTP_HOST' => 'other.example.com:8443',
            'HTTPS' => 'on',
            'REQUEST_METHOD' => 'GET',
        ];
        $this->assertNull($m->match('/secure/panel'));
    }

    #[Test]
    public function routematchReturnsRouteObject(): void
    {
        $m = new GroupMapper();
        $m->buildRoute('/test', 'TestRoute')
            ->withController('TestController')
            ->withAction('index')
            ->add();
        $m->compile();

        $result = $m->routematch('/test');
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        [$dict, $route] = $result;
        $this->assertSame('TestController', $dict['controller']);
        $this->assertInstanceOf(Route::class, $route);
        $this->assertSame('TestRoute', $route->routeName);
    }

    #[Test]
    public function generateProducesUrl(): void
    {
        $m = new GroupMapper();
        $m->group(['prefix' => '/nag'], function (GroupMapper $m) {
            $m->buildRoute('/tasks/:id')
                ->withController('TaskController')
                ->withAction('view')
                ->add();
        });
        $m->compile();

        $url = $m->generate(['controller' => 'TaskController', 'action' => 'view', 'id' => '99']);
        $this->assertSame('/nag/tasks/99', $url);
    }

    #[Test]
    public function secondaryPathsPrefixedByGroup(): void
    {
        $m = new GroupMapper();
        $m->group(['prefix' => '/nag'], function (GroupMapper $m) {
            $m->buildRoute('/tasks/:id')
                ->withController('TaskController')
                ->withAction('view')
                ->withSecondaryRoute('/legacy/:id')
                ->add();
        });
        $m->compile();

        // Primary matches
        $this->assertIsArray($m->match('/nag/tasks/5'));

        // Secondary should be prefixed with /nag
        $result = $m->match('/nag/legacy/5');
        $this->assertIsArray($result);
        $this->assertSame('5', $result['id']);

        // Unprefixed secondary should NOT match
        $this->assertNull($m->match('/legacy/5'));
    }

    #[Test]
    public function dumpProducesArray(): void
    {
        $m = new GroupMapper();
        $m->setDefaultStack(['Mw1', 'Mw2']);
        $m->group(['prefix' => '/app', 'defaults' => ['app' => 'myapp']], function (GroupMapper $m) {
            $m->buildRoute('/page/:id', 'AppPage')
                ->withController('PageController')
                ->withAction('show')
                ->add();
        });

        $dumped = $m->dump();

        $this->assertArrayHasKey('matchList', $dumped);
        $this->assertArrayHasKey('routeNames', $dumped);
        $this->assertCount(1, $dumped['matchList']);
        $this->assertSame(0, $dumped['routeNames']['AppPage']);

        $route = $dumped['matchList'][0];
        $this->assertSame('/app/page/:id', $route['routePath']);
        $this->assertSame('PageController', $route['defaults']['controller']);
        $this->assertSame('myapp', $route['defaults']['app']);
        $this->assertSame(['Mw1', 'Mw2'], $route['stack']);
        $this->assertNotEmpty($route['regexp']);
    }

    #[Test]
    public function methodConditionsRespected(): void
    {
        $m = new GroupMapper();
        $m->buildRoute('/submit')
            ->withController('FormController')
            ->withAction('process')
            ->post()
            ->add();
        $m->compile();

        $m->environ = ['REQUEST_METHOD' => 'POST'];
        $this->assertIsArray($m->match('/submit'));

        $m->environ = ['REQUEST_METHOD' => 'GET'];
        $this->assertNull($m->match('/submit'));
    }
}
