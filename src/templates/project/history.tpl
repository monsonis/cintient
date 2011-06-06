{*
    Cintient, Continuous Integration made simple.
    Copyright (c) 2010, 2011, Pedro Mata-Mouros Fonseca

    This file is part of Cintient.

    Cintient is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Cintient is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Cintient. If not, see <http://www.gnu.org/licenses/>.

*}
{if !empty($project_buildList)}
  {$menuLinks="<span id=\"projectSectionsLinks\"><a href=\"#\" class=\"rawOutput\">raw</a> | <a href=\"#\" class=\"junitReport\">unit</a> | <a href=\"#\" class=\"quality\">quality</a></span>"}
{/if}
{include file='includes/header.inc.tpl'
  subSectionTitle="Build history"
  menuLinks=$menuLinks
  backLink="{UrlManager::getForProjectView()}"}
{$project_latestBuild=""}
{if !empty($project_buildList)}
  {$project_latestBuild=$project_buildList.0}
{/if}
{include file='includes/projectHeader.inc.tpl' project=$globals_project project_latestBuild=$project_latestBuild}
    <div id="buildsList">
{if !empty($project_buildList)}
      <div class="label">Choose a different build:</div>
<script type="text/javascript">
// <![CDATA[
$(document).ready(function() {
  $('#buildsListDropdown').change(function() {
    window.location.replace($(this).find("option:selected").attr('value'));
  });
});
//]]>
</script>
      <select class="dropdown" id="buildsListDropdown">
{foreach from=$project_buildList item=build}
        <option value="{UrlManager::getForProjectBuildView($globals_project, $build)}"{if $build->getId()==$project_build->getId()} selected{/if}>build {$build->getId()}, r{$build->getScmRevision()} {if $build->getStatus()!=Project_Build::STATUS_FAIL}built{else}failed{/if} on {$build->getDate()|date_format}
{/foreach}
      </select>
{/if}
    </div>
{if !empty($project_buildList)}
<script type="text/javascript">
// <![CDATA[
$(document).ready(function() {
	// Show the passed resultPane, hiding all others
	var activeResultPane = null;
	function showBuildResultPane(resultPane) {
		if (activeResultPane === null || $(activeResultPane).attr('id') !== $(resultPane).attr('id')) {
			// Hide the previous pane
      $(activeResultPane).hide(50);
			// Reset the previous link
      $('#projectSectionsLinks a.' + $(activeResultPane).attr('id')).css({
        "color" : "rgb(255,40,0)",
        "font-weight" : "bold",
        "text-decoration" : "none",
        "text-shadow" : "#303030 1px 1px 1px"
      });
			// Highlight the active link
			$('#projectSectionsLinks a.' + $(resultPane).attr('id')).css({
				"color" : "rgb(255,60,0)",
			  "text-shadow" : "0px 0px 6px rgba(255,40,0,1)",
			  "text-decoration" : "none"
      });
		  // Show the current pane
  	  resultPane.fadeIn(300);

  	  activeResultPane = resultPane;
		}
  }
	// Bind the click link events to their corresponding panes
	$('#projectSectionsLinks a').each(function() {
		$(this).click(function() {
			showBuildResultPane($('#projectViewContainer').find('#' + $(this).attr('class')));
    });
  });
	// Promptly show the default pane
	showBuildResultPane($('#projectViewContainer #junitReport'));
});
//]]>
</script>
    <div id="projectViewContainer">
      <div id="rawOutput" class="buildResultPane">{$project_build->getOutput()|nl2br}</div>
      <div id="junitReport" class="buildResultPane">
{if !empty($project_buildJunit)}
{foreach from=$project_buildJunit item=classTest}
        <div class="classTest">{$classTest->getName()}</div>
        <div class="chart"><img width="{$smarty.const.CHART_JUNIT_DEFAULT_WIDTH}" src="{UrlManager::getForAsset($classTest->getChartFilename(), ['bid' => $project_build->getId()])}"></div>
{/foreach}
{else}
Due to a build error, the unit tests chart could not be generated. Please check the raw output of the build for problems, such as a PHP Fatal error.
{/if}
      </div>
      <div id="quality" class="buildResultPane">
{if !isset($project_jdependChartFilename) && !isset($project_overviewPyramidFilename)}
No quality metrics were collected in this build. If you haven't enabled
this yet, please add a PHP_Depend task to this project's integration
builder, and configure it properly. If you already have this task enabled,
please check the raw output of this build for problems, such as a PHP Fatal error.
{else}
        <div id="jdependChart"><embed type="image/svg+xml" src="{UrlManager::getForAsset($project_overviewPyramidFilename, ['bid' => $project_build->getId()])}" width="392" height="270" /></div>
        <div id="overviewChart"><embed type="image/svg+xml" src="{UrlManager::getForAsset($project_jdependChartFilename, ['bid' => $project_build->getId()])}" width="392" height="270" /></div>
{/if}
    </div>
{/if}
{include file='includes/footer.inc.tpl'}