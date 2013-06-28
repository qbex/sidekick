<?php
/**
 * Created by JetBrains PhpStorm.
 * User: oke.ugwu
 * Date: 31/05/13
 * Time: 17:31
 * To change this template use File | Settings | File Templates.
 */

namespace Sidekick\Applications\Repository\Controllers;

use Sidekick\Applications\Repository\Views\CommitFiles;
use Sidekick\Applications\Repository\Views\CommitsIndex;
use Sidekick\Components\Repository\Mappers\Commit;
use Sidekick\Components\Repository\Mappers\CommitFile;
use Sidekick\Components\Repository\Mappers\Source;

class CommitsController extends RepositoryController
{
  public function renderIndex()
  {
    $repoId  = $this->getInt('repoId');
    $repo    = new Source($repoId);
    $commits = Commit::collection(['repository_id' => $repoId])
               ->setOrderBy('committed_at', 'DESC')
               ->setLimit(0, 50);
    return $this->createView(new CommitsIndex($repo, $commits));
  }

  public function renderCommitSrc()
  {
    return 'Source Code to be displayed here. Maybe a diff';
  }

  public function renderCommitFiles()
  {
    $commitId = $this->getInt('commitId');
    $runId = $this->getInt('runId');
    $commitFiles = CommitFile::collection(['commit_id' => $commitId]);
    return new CommitFiles($commitFiles, $runId);
  }

  public function getRoutes()
  {
    return [
      '/'              => 'index',
      '/:repoId'       => 'index',
      '/:repoId/:runId/:commitId' => 'commitFiles'
    ];
  }
}
