{extends file="parent:backend/_base/semknox_layout.tpl"}

{block name="content/semknox_layout/main"}
<nav class="navbar navbar-inverse navbar-fixed-top">
    <div class="container">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a id="test" class="navbar-brand" href="#">SEMKNOX</a>
        </div>
        <div id="navbar" class="navbar-collapse collapse">
            <ul class="nav navbar-nav">
                <li{if {controllerAction} === 'index'} class="active"{/if}><a href="{url controller="semknoxSearchBackendModule" action="index" __csrf_token=$csrfToken}">Home</a></li>
<!--                <li{if {controllerAction} === 'config'} class="active"{/if}><a href="{url controller="semknoxSearchBackendModule" action="config" __csrf_token=$csrfToken}">Konfiguration</a></li>-->
<!--                <li{if {controllerAction} === 'exinfo01'} class="active"{/if}><a href="{url controller="semknoxSearchBackendModule" action="exinfo01" __csrf_token=$csrfToken}">ext. Info 1</a></li>-->

            </ul>
        </div><!--/.nav-collapse -->
    </div>
</nav>
<!--    <p>Aktuelle Log-Informationen</p> -->
<div class="container theme-showcase" role="main">
    <div class="page-header">
        <h2>Aktuelle Log-Informationen</h2>
    </div>
<!--
    <p>
        <div class="btn-group" role="group" aria-label="...">
            <button type="button" class="btn btn-default btn-minimize">Minimize</button>
            <button type="button" class="btn btn-default btn-maximize">Maximize</button>
            <button type="button" class="btn btn-default btn-show">Show</button>
            <button type="button" class="btn btn-default btn-hide">Hide</button>
            <button type="button" class="btn btn-danger btn-destroy">Destroy</button>
            <button type="button" class="btn btn-default btn-subwindow">Test subwindow</button>
        </div>
    </p>
-->
    <div class="panel panel-default">
        <div class="panel-heading"><h3 class="panel-title">Aktualisiere SEMKNOX-Daten</h3><span id="semknoxSearchUpdTitle"></span></div>
        <div class="panel-body">

            <!--<form class="form-horizontal" action="{url controller="semknoxSearchBackendModule" action="index" __csrf_token=$csrfToken}">
            		<input type="hidden" name="semUpdate" value="05" />
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button type="submit" class="btn btn-primary" {if !$enableUpdateButton}disabled="disabled"{/if}>aktualisiere alle Artikel über Cronjob</button>
                    </div>
                </div>
            </form>-->

            <form class="form-horizontal" action="{url controller="semknoxSearchBackendModule" action="index" __csrf_token=$csrfToken}">
            		<input type="hidden" name="semUpdate" value="72" />
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button id="semknoxSearchUpdstart" type="submit" class="btn btn-primary" {if !$enableUpdateButton}disabled="disabled"{/if}>Backend-Update starten</button>
                    </div>
                </div>
            </form>

            <form class="form-horizontal" action="{url controller="semknoxSearchBackendModule" action="index" __csrf_token=$csrfToken}">
            		<input type="hidden" name="semUpdate" value="02" />
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button id="semknoxSearchUpdstop" type="submit" class="btn btn-primary" {if $enableUpdateButton}disabled="disabled"{/if}>Aktuelles Update abbrechen</button>
                    </div>
                </div>
            </form>

        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><h3 class="panel-title">Log-Infos</h3></div>
        <div class="panel-body">
			    <div class="table-responsive">
			        <table class="table table-striped">
			            <thead>
			                <tr>
			                    <th>Id</th>
			                    <th>Aktion</th>
			                    <th></th>
			                    <th></th>
			                    <th>Datum/Uhrzeit</th>
			                </tr>
			            </thead>
			            <tbody>
			                {foreach $semknoxLog as $log}
			                    <tr {if $log@index eq 0}id="semkUpdateRow"{/if} >
			                        <td>{$log.id}</td>
			                        <td>{$log.info}</td>
			                        <td>{$log.logtitle}</td>
			                        <td>{$log.logdescr}</td>
			                        <td>{$log.datumShow}</td>
			                    </tr>
			                {/foreach}			                
			            </tbody>
			        </table>
			    </div>
    
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><h3 class="panel-title">Products changed since last update</h3></div>
        <div class="panel-body">
			    <div class="table-responsive">
			                {foreach $semknoxSingleLog as $log}
			                {$log}, 
			                {/foreach}			                
			    </div>
    
        </div>
    </div>
</div>

{/block}

{block name="content/semknox_layout/javascript"}
    <script type="text/javascript">
        var url = "{url controller="semknoxSearchBackendModule" action="getUpdateInfo" __csrf_token=$csrfToken}";
        var url2 = "{url controller="semknoxSearchBackendModule" action="doUpdate" __csrf_token=$csrfToken}";
        var timer=0;var timer2=0;var alrunning=0;var reqTime=10000;
        {literal}
	    	function semknoxUpdateInfo() {
        	$.ajax({
            url: url,
            success: function(response) {
            	var h = JSON.parse(response);
           		var tds=$('#semkUpdateRow').children('td');
           		tds[2].innerHTML=h.erg.logtitle;
           		tds[3].innerHTML=h.erg.logdescr;
           		if (alrunning==1) { return; }
           		$('#semknoxSearchUpdTitle').html(h.updateTitle);
							if (h.updateRunning>=0) {
									reqTime=2000;
									if (h.updateRunning==0) {
										$('#semknoxSearchUpdTitle').css('color','#FF3333');
										if ( (timer2==0) ) {
											timer2 = window.setTimeout(semknoxUpdate, 600);										
										}
									}
							} else {
								reqTime=10000;
								$('#semknoxSearchUpdTitle').css('color','inherit');
								if (timer2!=0) {
									clearTimeout(timer2);
									timer2=0;
								}
							}
							if (h.updateRunning>-1) {
								 $("#semknoxSearchUpdstart").addClass("disabled");
								 $("#semknoxSearchUpdstart").prop( "disabled", true );
								 $("#semknoxSearchUpdstop").removeClass("disabled");
								 $("#semknoxSearchUpdstop").prop( "disabled", false );
							} else {
								 $("#semknoxSearchUpdstop").addClass("disabled");
								 $("#semknoxSearchUpdstop").prop( "disabled", true );
								 $("#semknoxSearchUpdstart").removeClass("disabled");								
								 $("#semknoxSearchUpdstart").prop( "disabled", false );
							}
            }
        	});
        	timer = window.setTimeout(semknoxUpdateInfo, reqTime);
				}
				timer = window.setTimeout(semknoxUpdateInfo, 2000);
				
	    	function semknoxUpdate() {
	    		alrunning=1;
        	$.ajax({
            url: url2,
            error : function(response) {
            	var h = JSON.parse(response);
           		if (h.success==false) {
					if (timer2!=0) {
						clearTimeout(timer2);
						alrunning=0;
						timer2=0;reqTime=10000;
					}
           			
           			alert('Das Update wurde abgebrochen!<br><br>'.h.error);
           			if (h.wosoError==1) {
           			alert('Das Update wurde abgebrochen, weil die Ressourcen für den Serverprozess nicht ausreichen!<br>Es kann helfen, den Wert "Max. Anzahl Artikel für den Update-Aufbau" in der Plugin-Konfiguration zu verkleineren.<br><br>Falls dies nicht hilft, nutzen Sie bitte den CRON-Job für das Daten-Update!');
           			}
           		}
            },            
            success: function(response) {
            	alrunning=0;timer2=0;
            	var h = JSON.parse(response);
           		if (h.success==false) {
					alrunning=0;
					timer2=0;reqTime=10000;
           			alert("Das Update wurde abgebrochen!\n\n"+h.error);
           			if (h.wosoError==1) {
           			alert("Das Update wurde abgebrochen, weil die Ressourcen für den Serverprozess nicht ausreichen!\nEs kann helfen, den Wert 'Max. Anzahl Artikel für den Update-Aufbau' in der Plugin-Konfiguration zu verkleineren.\n\nFalls dies nicht hilft, nutzen Sie bitte den CRON-Job für das Daten-Update!");
           			}
           		}
            }
        	});
				}
        
      	{/literal}
    </script>
{/block}