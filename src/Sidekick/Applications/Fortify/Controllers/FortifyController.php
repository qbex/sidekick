<?php
/**
 * @author  brooke.bryan
 */

namespace Sidekick\Applications\Fortify\Controllers;

use Cubex\Facade\Queue;
use Cubex\Facade\Redirect;
use Cubex\Form\Form;
use Cubex\Queue\StdQueue;
use Cubex\Routing\StdRoute;
use Cubex\View\HtmlElement;
use Cubex\View\RenderGroup;
use Cubex\View\TemplatedView;
use Cubex\View\TemplatedViewModel;
use Sidekick\Applications\BaseApp\Controllers\BaseControl;
use Sidekick\Applications\BaseApp\Views\Sidebar;
use Sidekick\Applications\Fortify\Views\BuildDetailsView;
use Sidekick\Applications\Fortify\Views\BuildLogView;
use Sidekick\Applications\Fortify\Views\BuildsPage;
use Sidekick\Applications\Fortify\Views\FortifyRepositoryLink;
use Sidekick\Applications\Fortify\Views\PhpCsReport;
use Sidekick\Applications\Fortify\Views\PhpLocReport;
use Sidekick\Applications\Fortify\Views\PhpMdReport;
use Sidekick\Applications\Fortify\Views\PhpUnitReport;
use Sidekick\Applications\Fortify\Views\ReportsButtonGroup;
use Sidekick\Components\Fortify\Mappers\Build;
use Sidekick\Components\Fortify\Mappers\BuildLog;
use Sidekick\Components\Fortify\Mappers\BuildRun;
use Sidekick\Components\Fortify\Mappers\BuildsProjects;
use Sidekick\Components\Fortify\Mappers\Command;
use Sidekick\Components\Projects\Mappers\Project;
use Sidekick\Components\Repository\Mappers\Source;

class FortifyController extends BaseControl
{

  public function getSidebar()
  {
    $projects    = Project::collection()->loadAll()->setOrderBy('name');
    $sidebarMenu = [];
    foreach($projects as $project)
    {
      $sidebarMenu['/fortify/' . $project->id] = $project->name;
    }

    $main = new Sidebar(
      $this->request()->path(2),
      [
      '/fortify/builds'   => 'Manage Builds',
      '/fortify/commands' => 'Manage Commands'
      ]
    );

    return new RenderGroup(
      $main,
      '<hr>',
      new Sidebar($this->request()->path(2), $sidebarMenu)
    );
  }

  public function renderIndex()
  {
    return new TemplatedView("Index", $this);
  }

  public function renderFortify()
  {
    $projectId    = $this->getInt('projectId');
    $buildType    = $this->getInt('buildType', 1);
    $resultFilter = $this->getStr('result');

    $collectionFilter = ['build_id' => $buildType, 'project_id' => $projectId];
    if($resultFilter !== null)
    {
      $collectionFilter['result'] = $resultFilter;
    }

    $builds = Build::collection()->loadAll();
    //list all build runs
    $allBuilds = BuildRun::collection($collectionFilter)->setOrderBy(
      'created_at',
      'DESC'
    );

    return $this->createView(
      new BuildsPage(
        $projectId, $buildType, $builds, $allBuilds
      )
    );
  }

  public function renderBuildLog()
  {
    $runId    = $this->getInt('runId');
    $buildRun = new BuildRun($runId);

    $view = new BuildLogView();
    $view = $this->_addCommandToView($buildRun->commands, $runId, $view);

    $this->requireJs('buildLog');
    return $view;
  }

  public function renderRepo()
  {
    $projectId = $this->getInt('projectId');
    $buildId   = $this->getInt('buildType');

    $buildRepo = BuildsProjects::collection()->loadOneWhere(
      ['project_id' => $projectId, 'build_id' => $buildId]
    );

    $repos       = Source::collection()->loadAll()->getKeyedArray(
      'id',
      ['name', 'branch']
    );
    $repoOptions = [];
    foreach($repos as $id => $info)
    {
      $repoOptions[$id] = $info['name'] . ' - ' . $info['branch'];
    }

    $project = new Project($projectId);
    $repo    = new Source($buildRepo->buildSourceId);
    $build   = new Build($buildId);

    return new FortifyRepositoryLink($project, $repo, $build, $repoOptions);
  }

  /*
   * Run build process. Does not actually run the build, it only puts
   * the request into a queue, which gets processed by cron script
   */
  public function Build()
  {
    $projectId = $this->getInt('projectId');
    $buildId   = $this->getInt('buildType');

    try
    {
      $buildRepo = BuildsProjects::collection()->loadOneWhere(
        ['project_id' => $projectId, 'build_id' => $buildId]
      );

      if($buildRepo !== null)
      {
        $queue = new StdQueue('buildRequest');
        Queue::push(
          $queue,
          ['respositoryId' => $buildRepo->buildSourceId, 'buildId' => $buildId]
        );

        $msg       = new \stdClass();
        $msg->type = 'success';
        $msg->text = 'Your Build Request has been queued up!';
      }
      else
      {
        $msg       = new \stdClass();
        $msg->type = 'error';
        $msg->text = 'Your Build Request could not be processed.' .
          'No Repository is linked to this build type';
      }
    }
    catch(\Exception $e)
    {
      /*
       * By the way, I think getting to this point is impossible, because
       * BuildsProject Mapper has projectId and buildId as primary key, so
       * any combination of these two keys should always return one result.
       * The only case this will happen is if the primary keys got changed
       * and this is very unlikely.
       */
      $msg       = new \stdClass();
      $msg->type = 'error';
      $msg->text = 'Your Build Request could not be processed.' .
        'More than one Repository is linked to this build type';
    }

    Redirect::to($this->baseUri() . '/' . $projectId . '/' . $buildId)->with(
      'msg',
      $msg
    )->now();
  }

  public function renderReportType()
  {
    $reportType = $this->getStr('reportType');
    $file       = realpath($_SERVER['DOCUMENT_ROOT'] . '/..');

    $filter   = $this->getStr('filter');
    $runId    = $this->getInt('runId');
    $basePath = $this->request()->path(5);

    $report = '';
    switch($reportType)
    {
      case 'phploc':
        $file .= "/builds/$runId/logs/phploc.csv";
        $report = new PhpLocReport($file);
        break;
      case 'phpmd':
        $file .= "/builds/$runId/logs/pmd.report.xml";
        $report = new PhpMdReport($file, $filter, $basePath);
        break;
      case 'phpcs':
        $file .= "/builds/$runId/logs/checkstyle.xml";
        $report = new PhpCsReport($file, $filter);
        break;
      case 'phpunit':
        $file .= "/builds/$runId/logs/junit.xml";
        $report = new PhpUnitReport($file);
        break;
      default:
        $report = '';
    }

    return new RenderGroup(
      new ReportsButtonGroup(),
      $report
    );
  }

  public function buildDetails()
  {
    $runId    = $this->getInt('runId');
    $buildRun = new BuildRun($runId);
    $basePath = $this->request()->path(4);
    $view     = new BuildDetailsView($buildRun, $basePath);
    $view     = $this->_addCommandToView($buildRun->commands, $runId, $view);

    return $view;
  }

  private function _addCommandToView(
    $commands, $runId, TemplatedViewModel $view
  )
  {
    foreach($commands as $c)
    {
      $command       = new Command($c);
      $commandRun    = BuildLog::cf()->get(
        "$runId-$c",
        ['exit_code', 'start_time', 'end_time']
      );
      $commandOutput = BuildLog::cf()->getSlice(
        "$runId-$c",
        'output:0',
        '',
        false,
        1000
      );

      $view->addCommand(
        new Command($c),
        $commandRun,
        in_array($commandRun['exit_code'], $command->successExitCodes),
        $commandOutput
      );
    }

    return $view;
  }

  public function getRoutes()
  {
    //extending ResourceTemplate routes
    $routes = parent::getRoutes();

    //put overrides on top of routes so they take priority
    array_unshift(
      $routes,
      new StdRoute('/:projectId', 'fortify'),
      new StdRoute('/:projectId/:buildType', 'fortify'),
      new StdRoute('/:projectId/:buildType/repository', 'Repo'),
      new StdRoute('/:projectId/:buildType/build', 'Build'),
      new StdRoute('/:projectId/:buildType/:runId@num/', 'buildDetails'),
      new StdRoute('/:projectId/:buildType/:runId@num/buildlog', 'buildLog'),
      new StdRoute(
        '/:projectId/:buildType/:runId@num/:reportType',
        'reportType'
      ),
      new StdRoute(
        '/:projectId/:buildType/:runId@num/:reportType/:filter',
        'reportType'
      ),
      new StdRoute(
        '/:projectId/:buildType/(?<result>(pass|fail|running))/',
        'fortify'
      )
    );

    return $routes;
  }
}
