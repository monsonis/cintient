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
{$menuLinks="<a href=\"{UrlManager::getForProjectBuildHistory()}\">build history</a>"}
{if $globals_project->userHasAccessLevel($globals_user, Access::WRITE) || $globals_user->hasCos(UserCos::ROOT)}
  {$menuLinks="$menuLinks | <a href=\"{UrlManager::getForProjectEdit()}\">edit</a>"}
{/if}
{include file='includes/header.inc.tpl'
  subSectionTitle="Project"
  menuLinks=$menuLinks
  jsIncludes=['/js/lib/highcharts-2.1.6.js', '/js/lib/cintientHighcharts.theme.js']}
{include file='includes/projectHeader.inc.tpl' project=$globals_project project_latestBuild=$project_latestBuild}

<div class="whiteBoard">
<div id="projectLog">
{if !empty($project_log)}
<table>
  <tbody>
{foreach from=$project_log item=log}
{$currentDate=$log->getDate()|date_format:"%b %e, %Y"}
{if $currentDate != $lastDate}
    <tr>
      <th colspan="3">{$currentDate}</th>
    </tr>
{/if}
    <tr>
      <td>{$log->getDate()|date_format:"%R"}</td>
      <td>{$log->getMessage()}</td>
      <td>{$log->getUsername()}</td>
    </tr>
{$lastDate=$log->getDate()|date_format:"%b %e, %Y"}
{/foreach}
  </tbody>
</table>
{/if}
</div>
</div>

{if !empty($project_build)}
<script type="text/javascript">
// <![CDATA[
var chartBuildOutcomes;
var chartBuildTimeline;
$(document).ready(function() {
  //
  // Build outcomes
  //
	chartBuildOutcomes = new Highcharts.Chart({
    chart: {
      renderTo: 'chartBuildOutcomesContainer',
      type: 'pie'
    },
    title: {
      text: 'Build outcomes'
    },
    tooltip: {
      formatter: function() {
        slice = Math.round(this.y * 100 / {math equation="x+y" x=$project_buildStats.buildOutcomes.0 y=$project_buildStats.buildOutcomes.1});
        return this.point.name +': '+ slice + '%';
      }
    },
    plotOptions: {
      pie: {
        allowPointSelect: false,
        cursor: 'pointer',
        dataLabels: {
          enabled: true,
          formatter: function() {
            return this.point.name +': '+ this.y;
          }
        }
      }
    },
    series: [{
      type: 'pie',
      name: 'Build outcomes',
      data: [
        ['Ok', {$project_buildStats.buildOutcomes.1}],
        {
          name: 'Failed',
          y: {$project_buildStats.buildOutcomes.0},
          sliced: true,
          selected: true
        }
      ]
    }]
  });
  $('#chartBuildOutcomesContainer').fadeIn(600);

  //
  // Build timeline
  //
  chartBuildTimeline = new Highcharts.Chart({
    chart: {
      renderTo: 'chartBuildTimelineContainer',
      defaultSeriesType: 'scatter',
      zoomType: 'xy',
    },
    title: {
      text: 'Build timeline'
    },
    subtitle: {
      text: ''
    },
    xAxis: {
      title: {
        text: '' // Day
      },
      startOnTick: true,
      endOnTick: true,
      showLastLabel: true
    },
    yAxis: {
      title: {
        text: '' // Hour
      }
    },
    tooltip: {
      formatter: function() {
        return '' + this.x + ' ' + this.y;
      }
    },
    legend: {
      layout: 'vertical',
      align: 'left',
      verticalAlign: 'top',
      x: 30,
      y: 40,
      floating: true,
      backgroundColor: {
        linearGradient: [0, 0, 0, 50],
        stops: [
          [0, 'rgba(96, 96, 96, .1)'],
          [1, 'rgba(16, 16, 16, .1)']
        ]
      },
      borderWidth: 1
    },
    plotOptions: {
      scatter: {
        marker: {
          radius: 5,
          states: {
            hover: {
              enabled: true,
              //lineColor: 'rgb(100,100,100)'
            }
          }
        },
        states: {
          hover: {
            marker: {
              enabled: false
            }
          }
        }
      }
    },
    series: [{
      type: 'scatter',
      name: 'Ok',
      color: 'rgba(124,196,0, .4)',
      data: [
{foreach from=$project_buildStats.buildTimeline.ok item=ok}
{if !$ok@first}
,
{/if}
[{$ok.1}, {$ok.0}]
{/foreach}
      ]},
      {
        name: 'Failed',
        color: 'rgba(255,40,0, .4)',
        data: [
{foreach from=$project_buildStats.buildTimeline.failed item=failed}
{if !$failed@first}
,
{/if}
[{$failed.1}, {$failed.0}]
{/foreach}
        ]
      }
    ]
  });
  $('#chartBuildTimelineContainer').fadeIn(600);
});

// ]]>
</script>
<div id="chartBuildOutcomesContainer" style="display: none;"></div>
<div id="chartBuildTimelineContainer" style="display: none;"></div>
{/if}

{include file='includes/footer.inc.tpl'}