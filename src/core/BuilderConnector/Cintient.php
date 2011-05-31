<?php
/*
 *
 *  Cintient, Continuous Integration made simple.
 *  Copyright (c) 2010, 2011, Pedro Mata-Mouros Fonseca
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
 * What to consider:
 *  $GLOBALS['filesets'][<ID>]                      Holds all filesets
 *  $GLOBALS['filesets'][<ID>]['dir']               The fileset root dir
 *  $GLOBALS['filesets'][<ID>]['defaultExcludes']   Holds default exclusions (optional, default is use them)
 *  $GLOBALS['filesets'][<ID>]['exclude']           Holds files/dirs to exclude
 *  $GLOBALS['filesets'][<ID>]['include']           Holds files/dirs to include
 *  $GLOBALS['id']                     Holds an ID of the current builder
 *  $GLOBALS['properties'][]           Holds global project properties
 *  $GLOBALS['properties'][<TASK>][]   Holds local task related properties
 *  $GLOBALS['result']['ok']           Holds the success or failure of last task's execution
 *  $GLOBALS['result']['output']       Holds the output of the last task's execution, if any
 *  $GLOBALS['result']['stacktrace']   The stacktrace of the error
 *  $GLOBALS['result']['task']         Holds the last task executed
 *  $GLOBALS['targets'][]              0-index based array with all the targets in their actual execution order
 *  $GLOBALS['targetsDefault']         Holds the name of the default target to execute
 *  $GLOBALS['targetsDeps'][<ID>][]    Holds the names of the target's dependency targets
 */
class BuilderConnector_Cintient
{
  /**
   *
   * Mandatory attributes:
   * . targets
   * . defaultTarget
   *
   * @param BuilderElement_Project $o
   */
  static public function BuilderElement_Project(BuilderElement_Project $o)
  {
    $php = '';
    $context = array();
    $context['id'] = $o->getInternalId();
    $context['properties'] = array(); // User properties might be needed at builder code generation time (see the copy task, for instance)
    if (empty($context['id'])) {
      SystemEvent::raise(SystemEvent::ERROR, 'A unique identifier for the project is required.', __METHOD__);
      return false;
    }
    if (!$o->getTargets()) {
      SystemEvent::raise(SystemEvent::ERROR, 'No targets set for the project.', __METHOD__);
      return false;
    }
    if (!$o->getDefaultTarget()) {
      SystemEvent::raise(SystemEvent::ERROR, 'No default target set for the project.', __METHOD__);
      return false;
    }
    //
    // TODO: uncomment this in production
    //
    $php .= "
//error_reporting(0);
";
    if ($o->getBaseDir() !== null) {
      $php .= <<<EOT
set_include_path(get_include_path() . PATH_SEPARATOR . '{$o->getBaseDir()}');
EOT;
    }
    if ($o->getDefaultTarget() !== null) {
      $php .= <<<EOT
\$GLOBALS['targets'] = array();
\$GLOBALS['targetDefault'] = '{$o->getDefaultTarget()}_{$context['id']}';
\$GLOBALS['result'] = array();
\$GLOBALS['result']['ok'] = false;
\$GLOBALS['result']['output'] = '';
\$GLOBALS['result']['stacktrace'] = array();
EOT;
      //
      // The following because the internal cron emulation process runs
      // without exiting, and the second time around will redeclare
      // ilegally this function.
      //
      if (!function_exists('output')) {
        $php .= <<<EOT
function output(\$task, \$message)
{
  \$GLOBALS['result']['stacktrace'][] = "[" . date('H:i:s') . "] [{\$task}] {\$message}";
}
function expandStr(\$str)
{
  return preg_replace_callback('/\\$\{(\w*)\}/', function(\$matches) {
  	\$key = \$matches[1] . '_{$context['id']}';
    if (isset(\$GLOBALS['properties'][\$key])) {
      return \$GLOBALS['properties'][\$key];
    } else {
      return \$matches[1];
    }
  }, \$str);
}
EOT;
      }
    }
    $properties = $o->getProperties();
    if ($o->getProperties()) {
      foreach ($properties as $property) {
        $php .= self::BuilderElement_Type_Property($property, $context);
      }
    }
    $targets = $o->getTargets();
    if ($o->getTargets()) {
      foreach ($targets as $target) {
        $php .= self::BuilderElement_Target($target, $context);
      }
    }
    $php .= <<<EOT
foreach (\$GLOBALS['targets'] as \$target) {
  output('target', "Executing target \"\$target\"...");
  if (\$target() === false) {
    \$error = error_get_last();
    \$GLOBALS['result']['output'] = \$error['message'] . ', on line ' . \$error['line'] . '.';
    output('target', "Target \"\$target\" failed.");
    return false;
  } else {
    output('target', "Target \"\$target\" executed.");
  }
}
EOT;
    return $php;
  }

  static public function BuilderElement_Target(BuilderElement_Target $o, array &$context = array())
  {
    $php = '';
    if (!$o->getName()) {
      SystemEvent::raise(SystemEvent::ERROR, 'No name set for the target.', __METHOD__);
      return false;
    }
    $targetName = "{$o->getName()}_{$context['id']}";
    $php .= <<<EOT
\$GLOBALS['targets']['{$targetName}'] = '{$targetName}';
EOT;
    if (!function_exists($targetName)) {
      $php .= <<<EOT
function {$targetName}() {
EOT;
      if ($o->getProperties()) {
        $properties = $o->getProperties();
        foreach ($properties as $property) {
          $php .= self::BuilderElement_Type_Property($property, $context);
        }
      }
      if ($o->getDependencies()) {
        $deps = $o->getDependencies();
        $php .= <<<EOT
  \$GLOBALS['targetDeps']['{$targetName}'] = array();
EOT;
        foreach ($deps as $dep) {
          $php .= <<<EOT
  \$GLOBALS['targetDeps']['{$targetName}'][] = '{$dep}';
EOT;
        }
      }
      if ($o->getTasks()) {
        $tasks = $o->getTasks();
        foreach ($tasks as $task) {
          $method = get_class($task);
          $taskOutput = self::$method($task, $context);
          $php .= <<<EOT
  {$taskOutput}
EOT;
        }
      }
      $php .= <<<EOT
}
EOT;
    } else {
      $php .= "
// Function {$targetName}() already declared.
";
    }
    return $php;
  }

  /**
   *
   * !! BuilderConnector_Php has a direct dependency on this !!
   *
   */
  static public function BuilderElement_Task_Filesystem_Chmod(BuilderElement_Task_Filesystem_Chmod $o, array &$context = array())
  {
    $php = '';
    if (!$o->getFile() && !$o->getFilesets()) {
      SystemEvent::raise(SystemEvent::ERROR, 'No files not set for task chmod.', __METHOD__);
      return false;
    }
    $mode = $o->getMode();
    if (empty($mode) || !preg_match('/^(?:\d{3}|\$\{\w*\})$/', $mode)) { // It must be a 3 digit decimal or a property
      SystemEvent::raise(SystemEvent::ERROR, 'No mode set for chmod.', __METHOD__);
      return false;
    }

    $php .= "
\$callback = function (\$entry) {
  \$getModeInt = expandStr('{$o->getMode()}');
  \$getModeOctal = intval(\$getModeInt, 8); // Casts the decimal string representation into an octal (8 is for base 8 conversion)
  \$ret = @chmod(\$entry, \$getModeOctal);
  if (!\$ret) {
    output('chmod', \"Failed setting \$getModeInt on \$entry.\");
  } else {
    output('chmod', \"Ok setting \$getModeInt on \$entry.\");
    //echo \"\\n  Ok setting \$getModeInt on \$entry.\";
  }
  return \$ret;
};";
    if ($o->getFile()) {
      $php .= "
\$getFile = expandStr('{$o->getFile()}');
if (!\$callback(\$getFile) && {$o->getFailOnError()}) { // failonerror
  return false;
}
";
    } elseif ($o->getFilesets()) { // If file exists, it takes precedence over filesets
      $filesets = $o->getFilesets();
      foreach ($filesets as $fileset) {
        $php .= "
" . self::BuilderElement_Type_Fileset($fileset, $context) . "
if (!fileset{$fileset->getId()}_{$context['id']}(\$callback)) {
  \$GLOBALS['result']['ok'] = false;
  if ({$o->getFailOnError()}) { // failonerror
    return false;
  }
}
";
      }
    }
    return $php;
  }

  /**
   *
   * !! BuilderConnector_Php has a direct dependency on this !!
   *
   */
  static public function BuilderElement_Task_Filesystem_Chown(BuilderElement_Task_Filesystem_Chown $o, array &$context = array())
  {
    $php = '';
    if (!$o->getFile() && !$o->getFilesets()) {
      SystemEvent::raise(SystemEvent::ERROR, 'No files not set for task chown.', __METHOD__);
      return false;
    }
    if (!$o->getUser()) {
      SystemEvent::raise(SystemEvent::ERROR, 'No user set for chown.', __METHOD__);
      return false;
    }
    $php .= "
\$callback = function (\$entry) {
  \$getUser = expandStr('{$o->getUser()}');
  \$ret = @chown(\$entry, \$getUser);
  if (!\$ret) {
    output('chown', \"Failed setting \$getUser on \$entry.\");
  } else {
    output('chown', \"Ok setting \$getUser on \$entry.\");
    //echo \"\\n  Ok setting \$getUser on \$entry.\";
  }
  return \$ret;
};";
    if ($o->getFile()) {
      $php .= "
\$getFile = expandStr('{$o->getFile()}');
if (!\$callback(\$getFile) && {$o->getFailOnError()}) { // failonerror
  return false;
}
";
    } elseif ($o->getFilesets()) { // If file exists, it takes precedence over filesets
      $filesets = $o->getFilesets();
      foreach ($filesets as $fileset) {
        $php .= "
" . self::BuilderElement_Type_Fileset($fileset, $context) . "
if (!fileset{$fileset->getId()}_{$context['id']}(\$callback)) {
  \$GLOBALS['result']['ok'] = false;
  if ({$o->getFailOnError()}) { // failonerror
    return false;
  }
}
";
      }
    }
    return $php;
  }

  /**
   *
   * !! BuilderConnector_Php has a direct dependency on this !!
   *
   */
  static public function BuilderElement_Task_Filesystem_Copy(BuilderElement_Task_Filesystem_Copy $o, array &$context = array())
  {
    $php = '';
    if (!$o->getFile() && !$o->getFilesets()) {
      SystemEvent::raise(SystemEvent::ERROR, 'No source files set for task copy.', __METHOD__);
      return false;
    }
    if ($filesets = $o->getFilesets()) {
      if (!$filesets[0]->getDir() || !$filesets[0]->getInclude()) {
        SystemEvent::raise(SystemEvent::ERROR, 'No source files set for task copy.', __METHOD__);
        return false;
      }
    }
    if (!$o->getToFile() && !$o->getToDir()) {
      SystemEvent::raise(SystemEvent::ERROR, 'No destination set for task copy.', __METHOD__);
      return false;
    }

    if ($o->getToFile()) {
      $php .= "
\$path = pathinfo(expandStr('{$o->getToFile()}'));
\$baseToDir = \$path['dirname'];
";
    } elseif ($o->getToDir()) {
      $php .= "
\$baseToDir = expandStr('{$o->getToDir()}');
";
    }

    $php .= "
if (!file_exists(\$baseToDir) && !@mkdir(\$baseToDir, 0755, true)) {
	output('copy', \"Failed creating dir \$baseToDir.\");
  return false;
}";

    //
    // Internally treat $o->getFile() as a fileset.
    //
    $filesets = array();
    if ($o->getFile()) {
      $pathFrom = pathinfo(self::_expandStr($o->getFile(), $context));
      $fileset = new BuilderElement_Type_Fileset();
      $fileset->addInclude($pathFrom['basename']);
      $fileset->setDir($pathFrom['dirname']);
      $fileset->setType(BuilderElement_Type_Fileset::BOTH); // Very important default!!!
      $filesets[] = $fileset;
      $php .= "
\$baseFromDir = '{$pathFrom['dirname']}';
";
    } elseif ($o->getFilesets()) { // If file exists, it takes precedence over filesets
      $filesets = $o->getFilesets();
      $includes = $filesets[0]->getInclude();
      // Iterator mode for copy() must enforce parent dirs before their children,
      // so that we can mkdir the parent without first trying to copy in the children
      // on a non-existing dir.
      $pathFrom = pathinfo(self::_expandStr($filesets[0]->getDir(), $context));
      $filesets[0]->setDir(self::_expandStr($filesets[0]->getDir(), $context));
      $filesets[0]->setInclude(explode(' ', self::_expandStr(implode(' ', $filesets[0]->getInclude()), $context)));
      $filesets[0]->setExclude(explode(' ', self::_expandStr(implode(' ', $filesets[0]->getExclude()), $context)));
      $php .= "
\$baseFromDir = '{$pathFrom['dirname']}';
";
    }

    $php .= "
\$callback = function (\$entry) use (\$baseToDir, \$baseFromDir) {
  \$dest = \$baseToDir . substr(\$entry, strlen(\$baseFromDir));
  if (is_file(\$entry)) {
    \$ret = @copy(\$entry, \$dest);
  } elseif (is_dir(\$entry)) {
  	if (!file_exists(\$dest) && !@mkdir(\$dest, 0755, true)) {
  	  \$ret = false;
  	} else {
  	  \$ret = true;
    }
  } else {
    \$ret = false;
  }
  if (!\$ret) {
    output('copy', \"Failed copy of \$entry to \$dest.\");
  } else {
    output('copy', \"Copied \$entry to \$dest.\");
    //echo \"\\n  Copied \$entry to \$dest.\";
  }
  return \$ret;
};
";

    $context['iteratorMode'] = RecursiveIteratorIterator::SELF_FIRST; // Make sure dirs come before their children, in order to be created first
    foreach ($filesets as $fileset) {
      $php .= "
" . self::BuilderElement_Type_Fileset($fileset, $context) . "
if (!fileset{$fileset->getId()}_{$context['id']}(\$callback)) {
  \$GLOBALS['result']['ok'] = false;
  if ({$o->getFailOnError()}) { // failonerror
    return false;
  }
}
";
    }
    return $php;
  }

  /**
   *
   * !! BuilderConnector_Php has a direct dependency on this !!
   *
   */
  static public function BuilderElement_Task_Filesystem_Delete(BuilderElement_Task_Filesystem_Delete $o, array &$context = array())
  {
    $php = '';
    if (!$o->getFilesets()) {
      SystemEvent::raise(SystemEvent::ERROR, 'No files not set for task delete.', __METHOD__);
      return false;
    }
    if ($o->getFilesets()) {
      $filesets = $o->getFilesets();
      foreach ($filesets as $fileset) {
        $php .= "
" . self::BuilderElement_Type_Fileset($fileset, $context) . "
";
        //
        // In the following callback we assume that the fileset returns a
        // directory only *after* all it's content.
        //
        $php .= "
\$callback = function (\$entry) {
  \$ret = true;
  if (is_file(\$entry) || ({$o->getIncludeEmptyDirs()} && is_dir(\$entry))) { // includeemptydirs
    //\$ret = unlink(\$entry);
    echo \"\\n  Removing \$entry.\";
  }
  if (!\$ret) {
    output('delete', 'Failed deleting \$entry.');
  } else {
    output('delete', 'Deleted \$entry.');
  }
  return \$ret;
};
if (!fileset{$fileset->getId()}_{$context['id']}(\$callback)) {
  \$GLOBALS['result']['ok'] = false;
  if ({$o->getFailOnError()}) { // failonerror
    return false;
  }
}
";
      }
    }
    return $php;
  }

  /**
   *
   * !! BuilderConnector_Php has a direct dependency on this !!
   *
   */
  static public function BuilderElement_Task_Echo(BuilderElement_Task_Echo $o, array &$context = array())
  {
    $php = '';
    if (!$o->getMessage()) {
      SystemEvent::raise(SystemEvent::ERROR, 'Message not set for echo task.', __METHOD__);
      return false;
    }
    $msg = addslashes($o->getMessage());
    $php .= "
\$getMessage = expandStr('{$msg}');
";
    if ($o->getFile()) {
      $append = 'w'; // the same as append == false (default for Ant and Phing)
      if ($o->getAppend()) {
        $append = 'a';
      }
      $php .= <<<EOT
\$getFile = expandStr('{$o->getFile()}');
\$fp = fopen(\$getFile, '{$append}');
\$GLOBALS['result']['ok'] = (fwrite(\$fp, \$getMessage) === false ?:true);
fclose(\$fp);
EOT;
    } else {
      $php .= <<<EOT
\$GLOBALS['result']['ok'] = true;
output('echo', \$getMessage);
EOT;
    }
    return $php;
  }

  /**
   *
   * !! BuilderConnector_Php has a direct dependency on this !!
   *
   */
  static public function BuilderElement_Task_Exec(BuilderElement_Task_Exec $o, array &$context = array())
  {
    $php = '';
    if (!$o->getExecutable()) {
      SystemEvent::raise(SystemEvent::ERROR, 'Executable not set for exec task.', __METHOD__);
      return false;
    }
    $php .= "
\$getBaseDir = '';
";
    if ($o->getBaseDir()) {
      $php .= "
\$getBaseDir = \"cd \" . expandStr('{$o->getBaseDir()}') . \"; \";
";
    }
    $php .= "
\$args = '';
";
    if ($o->getArgs()) {
      $implodedArgs = implode(' ', $o->getArgs());
      $php .= "
\$getArgs = expandStr(' {$implodedArgs}');
";
    }
    $php .= "
\$getExecutable = expandStr('{$o->getExecutable()}');
\$GLOBALS['result']['task'] = '" . __FUNCTION__ . "';
output('exec', \"Executing '\$getBaseDir\$getExecutable\$getArgs'.\");
\$ret = exec(\"\$getBaseDir\$getExecutable\$getArgs\", \$lines, \$retval);
foreach (\$lines as \$line) {
  output('exec', \$line);
}
";
    if ($o->getOutputProperty()) {
      $php .= "
\$GLOBALS['properties']['{$o->getOutputProperty()}'] = \$ret;
";
    }
    $php .= "
if (\$retval > 0) {
  output('exec', 'Failed.');
} else {
  output('exec', 'Success.');
}
";
    //TODO: bullet proof this for boolean falses (they're not showing up)
    /*
    $php .= "if ({$o->getFailOnError()} && !\$ret) {
  \$GLOBALS['result']['ok'] = false;
  return false;
}
\$GLOBALS['result']['ok'] = true;
return true;
";*/
    return $php;
  }

  /**
   *
   * !! BuilderConnector_Php has a direct dependency on this !!
   *
   */
  static public function BuilderElement_Task_Filesystem_Mkdir(BuilderElement_Task_Filesystem_Mkdir $o, array &$context = array())
  {
    $php = '';
    if (!$o->getDir()) {
      SystemEvent::raise(SystemEvent::ERROR, 'Dir not set for mkdir task.', __METHOD__);
      return false;
    }
    $php .= "
\$GLOBALS['result']['task'] = '" . __FUNCTION__ . "';
\$getDir = expandStr('{$o->getDir()}');
if (!file_exists(\$getDir)) {
  if (mkdir(\$getDir, " . DEFAULT_DIR_MASK . ", true) === false) {
    \$GLOBALS['result']['ok'] = false;
    output('mkdir', 'Could not create ' . \$getDir . '.');
    return false;
  } else {
    output('mkdir', 'Created ' . \$getDir . '.');
  }
} else {
  output('mkdir', \$getDir . ' already exists.');
}
";
    return $php;
  }

  /**
   *
   * !! BuilderConnector_Php has a direct dependency on this !!
   *
   */
  static public function BuilderElement_Task_PhpLint(BuilderElement_Task_PhpLint $o, array &$context = array())
  {
    $php = '';
    if (!$o->getFilesets()) {
      SystemEvent::raise(SystemEvent::ERROR, 'No files not set for task PHP lint.', __METHOD__);
      return false;
    }
    $php .= "
\$GLOBALS['result']['task'] = '" . __FUNCTION__ . "';
output('phplint', 'Starting...');
";
    if ($o->getFilesets()) {
      $filesets = $o->getFilesets();
      foreach ($filesets as $fileset) {
        $php .= "
" . self::BuilderElement_Type_Fileset($fileset, $context) . "
";
        //
        // In the following callback we assume that the fileset returns a
        // directory only *after* all it's content.
        //
        $php .= "
\$callback = function (\$entry) {
  if (is_file(\$entry)) {
    \$ret = null;
    \$output = array();
    exec(\"" . CINTIENT_PHP_BINARY . " -l \$entry\", \$output, \$ret);
    if (\$ret > 0) {
      \$GLOBALS['result']['ok'] = false;
      output('phplint', 'Errors parsing ' . \$entry . '.');
      return false;
    } else {
      \$GLOBALS['result']['ok'] = true;
      output('phplint', 'No syntax errors detected in ' . \$entry . '.');
    }
  }
  return true;
};
if (!fileset{$fileset->getId()}_{$context['id']}(\$callback)) {
  output('phplint', 'Failed.');
  if ({$o->getFailOnError()}) { // failonerror
    return false;
  }
} else {
  output('phplint', 'Done.');
}
";
      }
    }
    return $php;
  }

  /**
   *
   * !! BuilderConnector_Php has a direct dependency on this !!
   *
   */
  static public function BuilderElement_Task_PhpUnit(BuilderElement_Task_PhpUnit $o, array &$context = array())
  {
    $php = '';
    if (!$o->getFilesets()) {
      SystemEvent::raise(SystemEvent::ERROR, 'No files not set for task PHPUnit.', __METHOD__);
      return false;
    }
    $php .= "
\$GLOBALS['result']['task'] = 'phpunit';
output('phpunit', 'Starting unit tests...');
";
    $logJunitXmlFile = '';
    if ($o->getLogJunitXmlFile()) {
      $logJunitXmlFile = ' --log-junit ' . $o->getLogJunitXmlFile();
    }
    $codeCoverageXmlFile = '';
    if ($o->getCodeCoverageXmlFile()) {
      if (!extension_loaded('xdebug')) {
        $php .= "
output('phpunit', 'Code coverage only possible with the Xdebug extension loaded. Option \"--coverage-clover\" disabled.');
";
      } else {
        $codeCoverageXmlFile = ' --coverage-clover ' . $o->getCodeCoverageXmlFile();
      }
    }
    $codeCoverageHtmlFile = '';
    if ($o->getCodeCoverageHtmlFile()) {
      if (!extension_loaded('xdebug')) {
        $php .= "
output('phpunit', 'Code coverage only possible with the Xdebug extension loaded. Option \"--coverage-html\" disabled.');
";
      } else {
        $codeCoverageHtmlFile = ' --coverage-html ' . $o->getCodeCoverageHtmlFile();
      }
    }
    if ($o->getFilesets()) {
      $filesets = $o->getFilesets();
      foreach ($filesets as $fileset) {
        $php .= "
" . self::BuilderElement_Type_Fileset($fileset, $context) . "
";
        //
        // In the following callback we assume that the fileset returns a
        // directory only *after* all it's content.
        //
        $php .= "
\$callback = function (\$entry) {
  if (is_file(\$entry)) {
    \$ret = null;
    \$output = array();
    exec(\"" . CINTIENT_PHPUNIT_BINARY . "{$logJunitXmlFile}{$codeCoverageXmlFile}{$codeCoverageHtmlFile} \$entry\", \$output, \$ret);
    output('phpunit', \$entry . ': ' . array_pop(\$output));
    if (\$ret > 0) {
      \$GLOBALS['result']['ok'] = false;
      return false;
    } else {
      \$GLOBALS['result']['ok'] = true;
    }
  }
  return true;
};
if (!fileset{$fileset->getId()}_{$context['id']}(\$callback)) {
  output('phpunit', 'Tests failed.');
  if ({$o->getFailOnError()}) { // failonerror
    return false;
  }
} else {
  output('phpunit', 'All tests ok.');
}
";
      }
    }
    return $php;
  }

  /**
   *
   * !! BuilderConnector_Php has a direct dependency on this !!
   *
   * Loosely based on phing's SelectorUtils::matchPath.
   *
   * @param BuilderElement_Type_Fileset $o
   */
  static public function BuilderElement_Type_Fileset(BuilderElement_Type_Fileset $o, array &$context = array())
  {
    $php = '';
    //
    // Generic class to process the includes/excludes filters
    //
    //TODO: Implement $isCaseSensitive!!!!
    //TODO: Implement only a single top level class for this

    $php = "
if (!class_exists('FilesetFilterIterator', false)) {
  class FilesetFilterIterator extends FilterIterator
  {
    private \$_filesetId;
    private \$_type;

    public function __construct(\$o, \$filesetId, \$type = " . BuilderElement_Type_Fileset::FILE . ")
    {
      \$this->_filesetId = \$filesetId;
      \$this->_type = \$type;
      parent::__construct(\$o);
    }

    public function accept()
    {
      // Check for type, first of all
      if (\$this->_type == " . BuilderElement_Type_Fileset::FILE . " && !is_file(\$this->current()) ||
      		\$this->_type == " . BuilderElement_Type_Fileset::DIR . " && !is_dir(\$this->current()))
      {
        return false;
      }

      // if it is default excluded promptly return false
      foreach (\$GLOBALS['filesets'][\$this->_filesetId]['defaultExcludes'] as \$exclude) {
        if (\$this->_isMatch(\$exclude)) {
          return false;
        }
      }
      // if it is excluded promptly return false
      foreach (\$GLOBALS['filesets'][\$this->_filesetId]['exclude'] as \$exclude) {
        if (\$this->_isMatch(\$exclude)) {
          return false;
        }
      }
      // if it is included promptly return true
      foreach (\$GLOBALS['filesets'][\$this->_filesetId]['include'] as \$include) {
        if (\$this->_isMatch(\$include)) {
          return true;
        }
      }
    }

    private function _isMatch(\$pattern)
    {
      \$current = \$this->current();
      \$dir = \$GLOBALS['filesets'][\$this->_filesetId]['dir'];
      /*if (substr(\$dir, -1) != DIRECTORY_SEPARATOR) {
        \$dir .= DIRECTORY_SEPARATOR;
      }
      \$current = \$dir . \$current;*/
      \$isCaseSensitive = true;
      \$rePattern = preg_quote(\$GLOBALS['filesets'][\$this->_filesetId]['dir'] . \$pattern, '/');
      \$dirSep = preg_quote(DIRECTORY_SEPARATOR, '/');
      \$patternReplacements = array(
        \$dirSep.'\*\*' => '\/?.*',
        '\*\*'.\$dirSep => '.*',
        '\*\*' => '.*',
        '\*' => '[^'.\$dirSep.']*',
        '\?' => '[^'.\$dirSep.']'
      );
      \$rePattern = str_replace(array_keys(\$patternReplacements), array_values(\$patternReplacements), \$rePattern);
      \$rePattern = '/^'.\$rePattern.'$/'.(\$isCaseSensitive ? '' : 'i');
      return (bool) preg_match(\$rePattern, \$current);
    }
  }
}
";
    if (!$o->getDir()) {
      SystemEvent::raise(SystemEvent::ERROR, 'Root dir not set for type fileset.', __METHOD__);
      return false;
    }
    $php .= "
\$GLOBALS['filesets']['{$o->getId()}_{$context['id']}'] = array();
\$GLOBALS['filesets']['{$o->getId()}_{$context['id']}']['dir'] = '';
\$GLOBALS['filesets']['{$o->getId()}_{$context['id']}']['defaultExcludes'] = array(
  '**/*~',
  '**/#*#',
  '**/.#*',
  '**/%*%',
  '**/._*',
  '**/CVS',
  '**/CVS/**',
  '**/.cvsignore',
  '**/SCCS',
  '**/SCCS/**',
  '**/vssver.scc',
  '**/.svn',
  '**/.svn/**',
  '**/.DS_Store',
  '**/.git',
  '**/.git/**',
  '**/.gitattributes',
  '**/.gitignore',
  '**/.gitmodules',
  '**/.hg',
  '**/.hg/**',
  '**/.hgignore',
  '**/.hgsub',
  '**/.hgsubstate',
  '**/.hgtags',
  '**/.bzr',
  '**/.bzr/**',
  '**/.bzrignore',
);
\$GLOBALS['filesets']['{$o->getId()}_{$context['id']}']['exclude'] = array();
\$GLOBALS['filesets']['{$o->getId()}_{$context['id']}']['include'] = array();
";
    if ($o->getDir()) {
      $php .= "
\$GLOBALS['filesets']['{$o->getId()}_{$context['id']}']['dir'] = expandStr('{$o->getDir()}');
";
    }
    if ($o->getDefaultExcludes() === false) {
      $php .= "
\$GLOBALS['filesets']['{$o->getId()}_{$context['id']}']['defaultExcludes'] = array();
";
    }
    if ($o->getInclude()) {
      $includes = $o->getInclude();
      foreach ($includes as $include) {
        $php .= "
\$GLOBALS['filesets']['{$o->getId()}_{$context['id']}']['include'][] = expandStr('{$include}');
";
      }
    }
    if ($o->getExclude()) {
      $excludes = $o->getExclude();
      foreach ($excludes as $exclude) {
        $php .= "
\$GLOBALS['filesets']['{$o->getId()}_{$context['id']}']['exclude'][] = expandStr('{$exclude}');
";
      }
    }

    $php .= "
if (!function_exists('fileset{$o->getId()}_{$context['id']}')) {
  function fileset{$o->getId()}_{$context['id']}(\$callback)
  {
    \$recursiveIt = false;
    \$dirIt = 'DirectoryIterator';
    \$itIt = 'IteratorIterator';
    foreach (\$GLOBALS['filesets']['{$o->getId()}_{$context['id']}']['include'] as \$include) {
      /*if (strpos(\$include, '**') !== false ||
         (substr_count(\$include, '/') > 1 && substr_count(\$include, '//') === 0) ||
          substr_count(\$include, '/') == 1 && strpos(\$include, '/') !== 0)
      {*/
        \$recursiveIt = true;
        \$dirIt = 'Recursive' . \$dirIt;
        \$itIt = 'Recursive' . \$itIt;
        break;
      /*}*/
    }
    try {
      foreach (new FilesetFilterIterator(new \$itIt(new \$dirIt(\$GLOBALS['filesets']['{$o->getId()}_{$context['id']}']['dir']), (!\$recursiveIt?:" . (!empty($context['iteratorMode'])?:"\$itIt::CHILD_FIRST") . "), (!\$recursiveIt?:\$itIt::CATCH_GET_CHILD)), '{$o->getId()}_{$context['id']}', {$o->getType()}) as \$entry) {
        if (!\$callback(\$entry, \$GLOBALS['filesets']['{$o->getId()}_{$context['id']}']['dir'])) {
          \$GLOBALS['result']['ok'] = false;
          \$msg = 'Callback applied to fileset returned false [CALLBACK=\$callback] [FILESET={$o->getId()}_{$context['id']}]';
          \$GLOBALS['result']['output'] = \$msg;
          //output(__METHOD__, \$msg);
          return false;
        }
      }
    } catch (UnexpectedValueException \$e) { // Typical permission denied
      \$GLOBALS['result']['ok'] = false;
      \$GLOBALS['result']['output'] = \$e->getMessage();
      output(__METHOD__, \$e->getMessage());
      return false;
    }
    return true;
  }
}
";
    return $php;
  }

  /**
   *
   * !! BuilderConnector_Php has a direct dependency on this !!
   *
   */
  static public function BuilderElement_Type_Properties(BuilderElement_Type_Properties $o, array &$context = array())
  {
    $php = '';
    if (!$o->getText()) {
      SystemEvent::raise(SystemEvent::ERROR, 'Empty properties text.', __METHOD__);
      return false;
    }
    $properties = parse_ini_string($o->getText());
    foreach ($properties as $key => $value) {
      $context['properties'][self::_expandStr($key, $context)] = self::_expandStr($value, $context);
      $php .= <<<EOT
\$key = expandStr('{$key}') . '_{$context['id']}';
\$GLOBALS['properties'][\$key] = expandStr('{$value}');
EOT;
    }
    return $php;
  }

  /**
   *
   * !! BuilderConnector_Php has a direct dependency on this !!
   *
   */
  static public function BuilderElement_Type_Property(BuilderElement_Type_Property $o, array &$context = array())
  {
    $php = '';
    if (!$o->getName() || !$o->getValue()) {
      SystemEvent::raise(SystemEvent::ERROR, 'Name and value not set for type property.', __METHOD__);
      return false;
    }
    $context['properties'][self::_expandStr($o->getName(), $context)] = self::_expandStr($o->getValue(), $context);
    $php .= <<<EOT
\$key = expandStr('{$o->getName()}') . '_{$context['id']}';
\$GLOBALS['properties'][\$key] = expandStr('{$o->getValue()}');
EOT;
    return $php;
  }

  /**
   *
   * !! BuilderConnector_Php has a direct dependency on this !!
   *
   */
  static public function execute($code)
  {
    SystemEvent::raise(SystemEvent::DEBUG, "Code: " . print_r($code, true), __METHOD__);
    eval ($code);
    if ($GLOBALS['result']['ok'] !== true) {
      if (!empty($GLOBALS['result']['task'])) {
        SystemEvent::raise(SystemEvent::INFO, "Failed on specific task. [TASK={$GLOBALS['result']['task']}] [OUTPUT={$GLOBALS['result']['output']}]", __METHOD__);
        SystemEvent::raise(SystemEvent::DEBUG, "Stacktrace: " . print_r($GLOBALS['result'], true), __METHOD__);
      } else {
        SystemEvent::raise(SystemEvent::INFO, "Failed for unknown reasons. [OUTPUT={$GLOBALS['result']['output']}]", __METHOD__);
      }
      return false;
    }
    return true;
  }

  static private function _expandStr($str, Array &$context = array())
  {
    return preg_replace_callback('/\$\{(\w*)\}/', function($matches) use (&$context) {
      if (isset($context['properties'][$matches[1]])) {

        SystemEvent::raise(SystemEvent::DEBUG, "Expanded ". $context['properties'][$matches[1]], __METHOD__);

        return $context['properties'][$matches[1]];
      } else {
        SystemEvent::raise(SystemEvent::DEBUG, "Didn't expand ". $matches[1], __METHOD__);

        return $matches[1];
      }
    }, $str);
  }
}