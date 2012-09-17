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
 * @subpackage  Task
 * @author      Vicente Gonzalez <monsonis@gmail.com>
 * @copyright   2012, Vicente Gonzalez
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU GPLv3 or later.
 * @version     $LastChangedRevision$
 * @link        $HeadURL$
 * Changed by   $LastChangedBy$
 * Changed on   $LastChangedDate$
 */
class Build_BuilderElement_Task_Php_PhpMessDetector extends Build_BuilderElement
{
  protected $_executable;
  protected $_args;            // The arguments to the executable command, if any, a space separated string
  protected $_baseDir;         // The directory in which the command should be executed in
  protected $_outputProperty;  // Log the command's output to the variable with this name
  protected $_rules;
  protected $_format;
  protected $_reportFullFile; // The file to which we should write the text report to (for direct showing the user)

  public function __construct()
  {
    parent::__construct();
    $this->_executable = null;
    $this->_args = null;
    $this->_baseDir = null;
    $this->_outputProperty = null;
    $this->_rules = null;
    $this->_format = null;
    $this->_specialTask = 'Build_SpecialTask_PhpMessDetector';
  }

  /**
   * Creates a new instance of this builder element, with default values.
   */
  static public function create()
  {
    $o = new self();
    $o->setExecutable('/usr/local/bin/phpmd');
    $o->setBaseDir('${sourcesDir}');
    $o->setRules('naming,design,codesize,unusedcode');
    $o->setFormat('html');
    $o->setFailOnError(false);
    $o->setReportFullFile($GLOBALS['project']->getReportsWorkingDir() . CINTIENT_PHPMESSDETECTOR_REPORT_FULL_FILE);
    return $o;
  }

  /**
   * Setter. Makes sure <code>$dir</code> always ends in a valid
   * <code>DIRECTORY_SEPARATOR</code> token.
   *
   * @param string $dir
   */
  public function setBaseDir($dir)
  {
    if (!empty($dir) && strpos($dir, DIRECTORY_SEPARATOR, (strlen($dir)-1)) === false) {
      $dir .= DIRECTORY_SEPARATOR;
    }
    $this->_baseDir = $dir;
  }

  public function toAnt()
  {
    if (!$this->isActive()) {
      return true;
    }
  }

  public function toHtml(Array $_ = array(), Array $__ = array())
  {
    if (!$this->isVisible()) {
      return true;
    }
    $callbacks = array(
      array('cb' => 'getHtmlFailOnError'),
      array(
        'cb' => 'getHtmlInputText',
        'name' => 'executable',
        'label' => 'Php Mess Detector executable',
        'value' => $this->getExecutable()
      ),
      array(
        'cb' => 'getHtmlInputText',
        'name' => 'args',
        'label' => 'Extra arguments',
        'value' => $this->getArgs(),
        'help' => 'Space separated.'
      ),
      array(
        'cb' => 'getHtmlInputText',
        'name' => 'basedir',
        'label' =>
        'Base dir',
        'value' => $this->getBaseDir()
      ),
      array(
        'cb' => 'getHtmlInputText',
        'name' => 'format',
        'label' => 'Format (text/html/xml)',
        'value' => $this->getFormat()
      ),
      array(
        'cb' => 'getHtmlInputText',
        'name' => 'rules',
        'label' => 'Rules',
        'value' => $this->getRules()
      ),
      array(
        'cb' => 'getHtmlInputText',
        'name' => 'outputProperty',
        'label' => 'Output property',
        'value' => $this->getOutputProperty()
      ),
    );
    parent::toHtml(array('title' => 'PHP Mess Detector'), $callbacks);
  }

  public function toPhing()
  {
    if (!$this->isActive()) {
      return '';
    }
  }

  public function toPhp(Array &$context = array())
  {
    if (!$this->isActive()) {
      return true;
    }
    $php = '';
    if (!$this->getExecutable()) {
      SystemEvent::raise(SystemEvent::ERROR, 'Executable not set for PHP Mess Detector task.', __METHOD__);
      return false;
    }
    if (!$this->getFormat()) {
      SystemEvent::raise(SystemEvent::ERROR, 'Format not set for PHP Mess Detector task.', __METHOD__);
      return false;
    }
    if (!$this->getRules()) {
      SystemEvent::raise(SystemEvent::ERROR, 'Rules not set for Mess Detector task', __METHOD__);
      return false;
    }

    $php .= "
\$GLOBALS['result']['task'] = 'phpmd';
";
    $php .= "
\$args = expandStr(' {$this->getBaseDir()} {$this->getFormat()} {$this->getRules()}');
";
    if ($this->getArgs()) {
      $php .= "
\$args .= expandStr(' {$this->getArgs()}');
";
    }

    if ($this->getReportFullFile()) {
      $php .= "
\$args .= expandStr(' --reportfile {$this->getReportFullFile()}');
";
    }

    $php .= "
\$getExecutable = expandStr('{$this->getExecutable()}');
\$GLOBALS['result']['task'] = 'phpmd';
output(\"Executing '\$getExecutable\$args'.\");
\$lines = array();
\$ret = exec(\"\$getExecutable\$args\", \$lines, \$retval);
foreach (\$lines as \$line) {
  output(\$line);
}
";
    if ($this->getOutputProperty()) {
      $php .= "
\$GLOBALS['properties']['{$this->getOutputProperty()}_{$context['id']}'] = \$ret;
";
    }
    $failOnError = $this->getFailOnError() ? 'true' : 'false';
    $php .= "
if (\$retval > 0) {
  output('Failed '.\$retval);
  if ({$failOnError}) {
    \$GLOBALS['result']['ok'] = false;
    return false;
  } else {
    \$GLOBALS['result']['ok'] = \$GLOBALS['result']['ok'] & true;
  }
} else {
  \$GLOBALS['result']['ok'] = \$GLOBALS['result']['ok'] & true;
  output('Success.');
}
";
    return $php;
  }
}