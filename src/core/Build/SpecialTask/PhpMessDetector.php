<?php
/*
 *
 *  Cintient, Continuous Integration made simple.
 *  Copyright (c) 2010-2012, Pedro Mata-Mouros <pedro.matamouros@gmail.com>
 *
 *  This file is part of Cintient.
 *
 *  Cintient is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  Cintient is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with Cintient. If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * The PHPMessDetector task handles is used to detect problems in your code
 *
 * @package     Build
 * @subpackage  SpecialTask
 * @author      Vicente Gonzalez <monsonis@gmail.com>
 * @copyright   2012, Vicente Gonzalez
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU GPLv3 or later.
 * @version     $LastChangedRevision$
 * @link        $HeadURL$
 * Changed by   $LastChangedBy$
 * Changed on   $LastChangedDate$
 */
class Build_SpecialTask_PhpMessDetector extends Framework_DatabaseObjectAbstract implements Build_SpecialTaskInterface
{
  protected $_ptrProjectBuild; // Redundant but necessary for save()
  protected $_buildId;         // The project build ID serves as this instance's ID
  protected $_date;            // Should practically coincide with the build's date
  protected $_version;

  public function __construct(Project_Build $build)
  {
    parent::__construct();
    $this->_ptrProjectBuild = $build;
    $this->_buildId = $build->getId();
    $this->_date = null;
    $this->_version = '';
  }

  public function __destruct()
  {
    parent::__destruct();
  }

  public function preBuild()
  {
    SystemEvent::raise(SystemEvent::DEBUG, "Called.", __METHOD__);
    return true;
  }

  public function postBuild()
  {
    SystemEvent::raise(SystemEvent::DEBUG, "Called.", __METHOD__);

    $reportFullFile = $this->getPtrProjectBuild()->getBuildDir() . $this->getReportHtmlFilename();
    if (!@copy($this->getPtrProjectBuild()->getPtrProject()->getReportsWorkingDir() . CINTIENT_PHPMESSDETECTOR_REPORT_FULL_FILE, $reportFullFile)) {
      SystemEvent::raise(SystemEvent::ERROR, "Could not backup original Full report file. [PID={$this->getProjectId()}] [BUILD={$this->getProjectBuildId()}]", __METHOD__);
    }
    // Clean up the direct to user report file, so we don't have to do it
    // each time on getViewData()
    $fd = fopen($reportFullFile, 'r');
    $originalFile = fread($fd, filesize($reportFullFile));
    fclose($fd);
    // Pretty dummy replacement of the path before the sources dir, trying
    // to hide as much as possible from the user (readibility purposes)
    $treatedFile = str_replace($this->getPtrProjectBuild()->getPtrProject()->getScmLocalWorkingCopy(), '', $originalFile);
    file_put_contents($reportFullFile, $treatedFile);

    return true;
  }

  public function getReportHtmlFilename()
  {
    //return md5($this->getProjectId() . $this->getProjectBuildId() . CINTIENT_PHPMESSDETECTOR_REPORT_FULL_FILE) . '.htm';
    return CINTIENT_PHPMESSDETECTOR_REPORT_FULL_FILE;
  }

  public function getViewData(Array $params = array())
  {
    $ret = array();
    $reportFullFile = $this->getPtrProjectBuild()->getBuildDir() . $this->getReportHtmlFilename();
    if ($this->getReportHtmlFilename() && file_exists($reportFullFile)) {
      // [relevantly] faster than file_get_contents?
      $fd = fopen($reportFullFile, 'r');
      $ret['project_phpmdFullReport'] = fread($fd, filesize($reportFullFile));
      fclose($fd);
    }
    return $ret;
  }

  /**
   * A slightly different version of the base _getCurrentSignature() is
   * needed, i.e., pointer to Project_Build is not to be considered.
   */
  protected function _getCurrentSignature(array $exclusions = array())
  {
    return parent::_getCurrentSignature(array('_ptrProjectBuild'));
  }

  /**
   * Getter for the project build ID
   */
  public function getProjectBuildId()
  {
    return $this->_ptrProjectBuild->getId();
  }

	/**
   * Getter for the project ID
   */
  public function getProjectId()
  {
    return $this->_ptrProjectBuild->getPtrProject()->getId();
  }

  public function init()
  {
  }

  protected function _save($force = false)
  {
    if (!$this->hasChanged()) {
      if (!$force) {
        return false;
      }
      SystemEvent::raise(SystemEvent::DEBUG, "Forced object save.", __METHOD__);
    }
    return true;
  }

  static private function _getObject(Resultset $rs, Project_Build $build)
  {
    return new self($build);
  }

  static public function install(Project $project)
  {
    return true;
  }

  static public function uninstall(Project $project)
  {
    return true;
  }

  static public function getById(Project_Build $build, User $user, $access = Access::READ, array $options = array())
  {
    return new self($build);
  }
}
