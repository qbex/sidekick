<?php
/**
 * Created by JetBrains PhpStorm.
 * User: oke.ugwu
 * Date: 03/06/13
 * Time: 17:30
 * To change this template use File | Settings | File Templates.
 */

namespace Sidekick\Applications\Phuse\Controllers;

use Cubex\Core\Http\Response;
use Cubex\View\HtmlElement;
use Cubex\View\RenderGroup;
use Cubex\View\Templates\Errors\Error404;
use Sidekick\Applications\Phuse\Views\PackageResults;
use Sidekick\Applications\Phuse\Views\PackagesList;
use Sidekick\Applications\Phuse\Views\PackageView;
use Sidekick\Applications\Phuse\Views\PhuseIndex;
use Sidekick\Applications\Phuse\Views\RecentReleases;
use Sidekick\Applications\Phuse\Views\Sidebar;
use Sidekick\Components\Phuse\Mappers\Package;
use Sidekick\Components\Phuse\Mappers\Release;

class DefaultController extends PhuseController
{
  public function preRender()
  {
    parent::preRender();
    $this->requireJs('search');
    $this->nest(
      'sidebar',
      new Sidebar($this->request()->path(3), $this->appBaseUri())
    );
  }

  public function renderIndex()
  {
    return new PhuseIndex($this->config("project")->getStr("base_uri"));
  }

  public function renderViewPackage()
  {
    $packageId = $this->getInt('packageId');
    $package   = new Package($packageId);
    $releases  = Release::collection()->loadWhere(['package_id' => $packageId]);
    return new PackageView($package, $releases);
  }

  /**
   * This method is called by Ajax and this why we return a response instead
   * of the usual view.
   *
   * @return Response
   */
  public function ajaxSearch()
  {
    $query    = $this->getStr('query');
    $packages = Package::collection()->whereLike(
      'name',
      $query
    );
    return new Response($this->createView(new PackageResults($packages)));
  }

  public function renderSearch()
  {
    //Phuse search must be called only via Ajax;
    return new Error404();
  }

  public function renderNewPackages()
  {
    $recentDate = date('Y-m-d 00:00:00', strtotime('-2 months'));
    $packages   = Package::collection()->whereGreaterThan(
      'created_at',
      $recentDate
    )->setOrderBy('created_at', 'DESC');
    return $this->createView(new PackagesList($packages, 'New Packages'));
  }

  public function renderRecentReleases()
  {
    $releases = Release::collection()->setOrderBy('created_at', 'DESC')
                ->setLimit(0, 10);

    return new RecentReleases($releases);
  }

  public function renderAllPackages()
  {
    $perPage  = 30;
    $page     = $this->getStr('page');
    $packages = Package::collection()->setOrderBy('name', 'ASC');
    $filter   = $this->getStr('filter');
    if($filter !== null)
    {
      $packages = $packages->loadWhere(['vendor' => $filter]);
    }

    $totalCount   = $packages->count();
    $packagesList = new PackagesList($packages, 'All Packages', false);
    $pager        = $packagesList->pager($page, $totalCount, $perPage);
    $pager->getOffset();
    $packages->setLimit($pager->getOffset(), $perPage);
    $packagesList->showFilters();

    $list = $this->createView($packagesList);

    return new RenderGroup(
      $list,
      $pager->getPager()
    );
  }

  public function getRoutes()
  {
    return [
      'search/'                    => 'search',
      'search/(?<query>.*)/'       => 'search',
      'view/:packageId'            => 'viewPackage',
      'new-packages'               => 'newPackages',
      'recent-releases'            => 'recentReleases',
      'recent-releases/page/:page' => 'recentReleases',
      'all/page/:page'             => 'allPackages',
      'all/(.*)'                   => 'allPackages',
      ':filter/(.*)'               => 'allPackages',
    ];
  }
}
