<?php

declare(strict_types=1);

namespace Horde\Routes\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde\Routes\GroupMapper;
use Horde\Routes\CompiledMatcher;
use Horde\Routes\CompiledGenerator;

#[CoversClass(CompiledMatcher::class)]
#[CoversClass(CompiledGenerator::class)]
class CompiledRouteTest extends TestCase
{
    private array $compiled;

    protected function setUp(): void
    {
        $m = new GroupMapper();
        $m->setDefaultStack(['Auth', 'Log']);

        $m->group(['prefix' => '/nag', 'defaults' => ['app' => 'nag']], function (GroupMapper $m) {
            $m->buildRoute('/tasks', 'NagTaskList')
                ->withController('TaskController')
                ->withAction('list')
                ->get()
                ->add();
            $m->buildRoute('/tasks', 'NagTaskCreate')
                ->withController('TaskController')
                ->withAction('create')
                ->post()
                ->add();
            $m->buildRoute('/tasks/:id', 'NagTaskView')
                ->withController('TaskController')
                ->withAction('view')
                ->add();
        });

        $m->group(['prefix' => '/api', 'stack' => ['ApiKey'], 'defaults' => ['app' => 'api']], function (GroupMapper $m) {
            $m->buildRoute('/users/:id', 'ApiUserShow')
                ->withController('ApiUserController')
                ->withAction('show')
                ->add();
        });

        $m->buildRoute('/health', 'Health')
            ->withController('HealthController')
            ->withAction('check')
            ->noMiddleware()
            ->add();

        $this->compiled = $m->dump();
    }

    #[Test]
    public function matcherFindsRoute(): void
    {
        $matcher = new CompiledMatcher($this->compiled);
        $result = $matcher->match('/nag/tasks/42');

        $this->assertIsArray($result);
        $this->assertSame('TaskController', $result['controller']);
        $this->assertSame('view', $result['action']);
        $this->assertSame('42', $result['id']);
        $this->assertSame('nag', $result['app']);
        $this->assertSame(['Auth', 'Log'], $result['stack']);
    }

    #[Test]
    public function matcherRespectsMethodCondition(): void
    {
        $matcher = new CompiledMatcher($this->compiled);

        // GET matches list
        $result = $matcher->match('/nag/tasks', ['REQUEST_METHOD' => 'GET']);
        $this->assertIsArray($result);
        $this->assertSame('list', $result['action']);

        // POST matches create
        $result = $matcher->match('/nag/tasks', ['REQUEST_METHOD' => 'POST']);
        $this->assertIsArray($result);
        $this->assertSame('create', $result['action']);
    }

    #[Test]
    public function matcherReturnsCustomStack(): void
    {
        $matcher = new CompiledMatcher($this->compiled);
        $result = $matcher->match('/api/users/5');

        $this->assertSame(['ApiKey'], $result['stack']);
        $this->assertSame('api', $result['app']);
    }

    #[Test]
    public function matcherReturnsEmptyStackForNoMiddleware(): void
    {
        $matcher = new CompiledMatcher($this->compiled);
        $result = $matcher->match('/health');

        $this->assertSame([], $result['stack']);
    }

    #[Test]
    public function matcherReturnsNullForNoMatch(): void
    {
        $matcher = new CompiledMatcher($this->compiled);
        $this->assertNull($matcher->match('/nonexistent'));
    }

    #[Test]
    public function routematchReturnsRouteData(): void
    {
        $matcher = new CompiledMatcher($this->compiled);
        $result = $matcher->routematch('/nag/tasks/1');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        [$dict, $routeData] = $result;
        $this->assertSame('TaskController', $dict['controller']);
        $this->assertSame('NagTaskView', $routeData['routeName']);
    }

    #[Test]
    public function generatorByName(): void
    {
        $gen = new CompiledGenerator($this->compiled);
        $url = $gen->generateNamed('NagTaskView', ['id' => '99']);

        $this->assertSame('/nag/tasks/99', $url);
    }

    #[Test]
    public function generatorByParams(): void
    {
        $gen = new CompiledGenerator($this->compiled);
        $url = $gen->generate([
            'controller' => 'ApiUserController',
            'action' => 'show',
            'id' => '7',
        ]);

        $this->assertSame('/api/users/7', $url);
    }

    #[Test]
    public function generatorReturnsNullForUnknownName(): void
    {
        $gen = new CompiledGenerator($this->compiled);
        $this->assertNull($gen->generateNamed('NonexistentRoute'));
    }

    #[Test]
    public function generatorReturnsNullForUnmatchableParams(): void
    {
        $gen = new CompiledGenerator($this->compiled);
        $this->assertNull($gen->generate([
            'controller' => 'Unknown',
            'action' => 'missing',
        ]));
    }

    #[Test]
    public function roundTripMatchAndGenerate(): void
    {
        $matcher = new CompiledMatcher($this->compiled);
        $gen = new CompiledGenerator($this->compiled);

        // Match a URL
        $result = $matcher->match('/nag/tasks/55');
        $this->assertSame('55', $result['id']);

        // Generate it back
        $url = $gen->generate([
            'controller' => $result['controller'],
            'action' => $result['action'],
            'id' => $result['id'],
        ]);
        $this->assertSame('/nag/tasks/55', $url);
    }
}
