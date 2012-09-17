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
 * The PhpUnit special task handles unit tests execution for PHP projects
 * and specifies interface logic.
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
class Build_BuilderElement_Task_Php_PhpUnitXml extends Build_BuilderElement
{
  protected $_executable;
  protected $_args;            // The arguments to the executable command, if any, a space separated string
  protected $_baseDir;         // The directory in which the command should be executed in
  protected $_outputProperty;  // Log the command's output to the variable with this name
  protected $_configfile;      // XML Config file
  protected $_groups;          // Groups
  protected $_codeCoverageXmlFile;
  protected $_codeCoverageHtmlDir;
  protected $_logJunitXmlFile;

  public function __construct()
  {
    parent::__construct();
    $this->_executable = null;
    $this->_args = null;
    $this->_baseDir = null;
    $this->_outputProperty = null;
    $this->_configfile = null;
    $this->_groups = null;
    $this->_specialTask = 'Build_SpecialTask_PhpUnit';
  }

	/**
   * Creates a new instance of this builder element, with default values.
   */
  static public function create()
  {
    $o = new self();
    $o->setExecutable('/usr/local/bin/phpunit');
    $o->setBaseDir('${sourcesDir}');
    $o->setConfigfile('fuel/core/phpunit.xml');
    $o->setLogJunitXmlFile($GLOBALS['project']->getReportsWorkingDir() . CINTIENT_JUNIT_REPORT_FILENAME);
    $o->setCodeCoverageXmlFile($GLOBALS['project']->getReportsWorkingDir() . CINTIENT_CODECOVERAGE_XML_REPORT_FILENAME);
    $o->setCodeCoverageHtmlDir($GLOBALS['project']->getReportsWorkingDir() . CINTIENT_CODECOVERAGE_HTML_DIR);
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
        'label' => 'Phpunit executable',
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
        'name' => 'configfile',
        'label' => 'Config file (xml)',
        'value' => $this->getConfigfile()
      ),
      array(
        'cb' => 'getHtmlInputText',
        'name' => 'groups',
        'label' => 'Groups',
        'value' => $this->getGroups()
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
    		'name' => 'outputProperty',
    		'label' => 'Output property',
    		'value' => $this->getOutputProperty()
    	),
    );
    parent::toHtml(array('title' => 'Php Unit XML'), $callbacks);
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
      SystemEvent::raise(SystemEvent::ERROR, 'Executable not set for exec task.', __METHOD__);
      return false;
    }
    $php .= "
\$GLOBALS['result']['task'] = 'phpunitxml';
\$getBaseDir = '';
";
    if ($this->getBaseDir()) {
      $php .= "
\$getBaseDir = \"cd \" . expandStr('{$this->getBaseDir()}') . \"; \";
";
    }
    $php .= "
\$args = '';
";
    if ($this->getConfigfile()) {
      $php .= "
\$args .= expandStr(' -c {$this->getConfigfile()}');
";
    }
    if ($this->getGroups()) {
      $php .= "
\$args .= expandStr(' --group {$this->getGroups()}');
";
    }
    if ($this->getArgs()) {
      $php .= "
\$args .= expandStr(' {$this->getArgs()}');
";
    }

    if ($this->getLogJunitXmlFile()) {
      $php .= "
\$args .= expandStr(' --log-junit {$this->getLogJunitXmlFile()}');
";
    }
    if ($this->getCodeCoverageXmlFile()) {
      $php .= "
if (!extension_loaded('xdebug')) {
  output('Code coverage only possible with the Xdebug extension loaded. Option \"--coverage-clover\" disabled.');
} else {
  \$args .= expandStr(' --coverage-clover {$this->getCodeCoverageXmlFile()}');
}
";
    }

    if ($this->getCodeCoverageHtmlDir()) {
      $php .= "
if (!extension_loaded('xdebug')) {
  output('Code coverage only possible with the Xdebug extension loaded. Option \"--coverage-html\" disabled.');
} else {
  \$args .= expandStr(' --coverage-html {$this->getCodeCoverageHtmlDir()}');
}
";
    }

    $php .= "
\$getExecutable = expandStr('{$this->getExecutable()}');
\$GLOBALS['result']['task'] = 'phpunitxml';
output(\"Executing '\$getBaseDir\$getExecutable\$args'.\");
\$lines = array();
\$ret = exec(\"\$getBaseDir\$getExecutable\$args\", \$lines, \$retval);
foreach (\$lines as \$line) {
  output(\$line);
}
";
    if ($this->getOutputProperty()) {
      $php .= "
\$GLOBALS['properties']['{$this->getOutputProperty()}_{$context['id']}'] = \$ret;
";
    }
    $php .= "
if (\$retval > 0) {
  output('Failed.');
  if ({$this->getFailOnError()}) {
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