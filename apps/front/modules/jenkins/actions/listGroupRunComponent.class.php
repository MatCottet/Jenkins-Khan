<?php

class listGroupRunComponent extends sfComponent
{

  /**
   * @param sfWebRequest $request
   */
  public function execute($request)
  {
    $sharedView     = $request->getParameter('sharedView');
    $sharedViewMode = isset($sharedView);
    
    /** @var Jenkins $jenkins */
    $groupRunId = $this->getVar('group_run_id');
    $jenkins    = $this->getVar('jenkins');

    $currentGroup = JenkinsGroupRunPeer::retrieveByPK($groupRunId);

    if (null === $currentGroup)
    {
      return sfView::NONE;
    }

    $runs                  = $currentGroup->getJenkinsRuns();
    $dataRuns              = array();
    $isJenkinsAvailable    = $jenkins->isAvailable();
    
    foreach ($runs as $run)
    {
      $isCancelable = false;
      $progress     = null;
      $remaining    = null;
      $isRunning    = false;
      $urlBuild     = $run->getUrlBuild($jenkins);
      if (!$isJenkinsAvailable)
      {
        $isCancelable = false;
      }
      elseif (($build = $run->getJenkinsBuild($jenkins)) instanceof Jenkins_Build)
      {
        /** @var Jenkins_Build $build */
        $isCancelable = $isRunning = $build->isRunning();
        $progress     = $build->getProgress();
        $remaining    = $build->getRemainingExecutionTime();
      }
      elseif ($run->isInJenkinsQueue($jenkins))
      {
        $isCancelable = true;
      }

      /** @var JenkinsRun $run */
      $dataRuns[$run->getId()] = array(
        'job_name'            => $run->getJobName(),
        'start_time'          => $run->getStartTime($jenkins),
        'duration'            => $run->getDuration($jenkins),
        'scheduled_launch'    => $run->getLaunchDelayed(),
        'result'              => $run->getJenkinsResult($jenkins),
        'parameters'          => $run->getLaunched() && $isRunning ? $run->getJenkinsBuildCleanedParameter($jenkins) : $run->decodeParameters(),
        'is_running'          => $isRunning,
        'progress'            => -1 === $progress ? null : $progress,
        'remaining_time'      => $remaining,
        'url'                 => $urlBuild,
        'url_console_log'     => $urlBuild . '/console',
        'dropdown_links'      => $this->buildDropdownLinksJenkinsRun($run, $jenkins, $isRunning, $isJenkinsAvailable, $sharedViewMode),
        'title_url_rebuild'   => $isRunning ? "Cancel current build and relaunch" : (!$run->isRebuildable() ? "Launch build immediatly" : "Relaunch build")
      );
      
      if (!$sharedViewMode)
      {
        $moreDataRuns = array(
          'is_cancelable'   => $isCancelable,
          'url_rebuild'     => $this->generateUrl('run_rebuild', $run),
        );
        $dataRuns[$run->getId()] = array_merge($dataRuns[$run->getId()], $moreDataRuns);
      }
    }

    $currentGroupInfos = array(
      'id'              => $currentGroup->getId(),
      'git_branch'      => $currentGroup->getGitBranch(),
      'dropdown_links'  => $this->buildDropdownCurrentGroup($currentGroup, $isJenkinsAvailable),
    );
    if (!$sharedViewMode)
    {
      $url_add_build = array('url_add_build' => $isJenkinsAvailable ? 'jenkins/addBuild?group_run_id=' . $currentGroup->getId() : null);
      $currentGroupInfos = array_merge($currentGroupInfos, $url_add_build);
    }
    
    $this->setVar('runs', $dataRuns);
    $this->setVar('current_group_run', $currentGroupInfos);
    $this->setVar('duration_formatter', new durationFormatter());
    $this->setVar('sharedViewMode', $sharedViewMode);
  }

  /**
   * @param \JenkinsRun $run
   * @param \Jenkins    $jenkins
   * @param bool        $isRunning
   * @param bool        $isJenkinsAvailable
   * @param bool        $sharedViewMode
   *
   * @return array
   */
  private function buildDropdownLinksJenkinsRun(JenkinsRun $run, Jenkins $jenkins, $isRunning, $isJenkinsAvailable, $sharedViewMode = false)
  {
    $links    = array();
    $urlBuild = $run->getUrlBuild($jenkins);

    if ($isRunning || $run->isRebuildable() && !$sharedViewMode)
    {
      $links[] = array(
        'label' => 'Delay',
        'title' => 'Relaunch build (delayed)',
        'url'   => $this->generateUrl('run_rebuild_delayed', $run),
      );
    }

    if (!$sharedViewMode)
    {
      $links[] = array(
        'label' => 'Remove build',
        'title' => 'Remove build from build branch',
        'url'   => $this->generateUrl('run_remove', $run),
      );
    }

    $isJenkinsAvailable && $links[] = array(
      'label'     => 'Go to console log',
      'title'     => 'View Jenkins console log',
      'url'       => $urlBuild . '/console',
      'options'   => array(
        'class'  => 'jenkins',
        'target' => '_blank'
      ),
    );

    $isJenkinsAvailable && $links[] = array(
      'label'     => 'Go to test report',
      'url'       => $urlBuild . '/testReport',
      'options'   => array(
        'class'  => 'jenkins',
        'target' => '_blank'
      ),
    );

    return $links;
  }

  /**
   * @param \JenkinsGroupRun $currentGroup
   * @param boolean          $isJenkinsAvailable
   *
   * @return array
   */
  private function buildDropdownCurrentGroup(JenkinsGroupRun $currentGroup, $isJenkinsAvailable)
  {
    $links = array();
    $isJenkinsAvailable && $links[] = array(
      'label' => 'Relaunch all jobs',
      'url'   => $this->generateUrl('branch_rebuild', $currentGroup),
    );

    $links[] = array(
      'label' => 'Add all jobs in delayed list',
      'url'   => $this->generateUrl('branch_rebuild_delayed', $currentGroup),
    );

    $isJenkinsAvailable && $links[] = array(
      'label' => 'Add a job',
      'url'   => 'jenkins/addBuild?group_run_id=' . $currentGroup->getId(),
      'title' => 'Add a job to this build branch',
    );

    $isJenkinsAvailable && $links[] = array(
      'label' => 'Duplicate build branch',
      'url'   => 'jenkins/createGroupRun?from_group_run_id=' . $currentGroup->getId(),
    );

    $links[] = array(
      'label' => 'Delete build branch',
      'url'   => 'jenkins/deleteGroupRun?id=' . $currentGroup->getId(),
    );
    
    return $links;
  }
  
  
}
