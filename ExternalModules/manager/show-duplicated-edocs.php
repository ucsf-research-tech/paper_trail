<?php
namespace ExternalModules;
require_once __DIR__ . '/../redcap_connect.php';
require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';

?>
<style>
	table.unsafe-edocs button{
		margin: 2px;
		min-width: 100px;
	}
</style>
<h5 style='margin:10px'>Unsafe Module File References</h5>
<p>
	In previous versions of REDCap, eDoc ID's were copied as-is along with other module settings when projects were copied.
	If an eDoc referenced by multiple projects was deleted in any of them, it would then unexpectedly no longer exist for the other projects referencing it.
	Newer reversions of REDCap automatically make new copies of eDocs referenced in module settings when projects are copied.
	Below is a list of eDocs unsafely referenced in module settings outside the projects for which they were uploaded.
	It is recommended that you go through and manually re-upload each file referenced below.
	The "Copy EDocs" feature below attempts to automate this, but is not feasible to test in every possible configuration scenario.
	Only use this feature at your own risk, and not without a recent backup of the <b>redcap_external_module_settings</b> database table that you know how to restore manually in case this feature corrupts module settings.
</p>
<?php

$referencesByProject = ExternalModules::getUnsafeEDocReferences();
if(empty($referencesByProject)){
	echo "<br><h6>Congratulations, no unsafe references exist!</h6>";
}
else{
	$getProjectNames = function($pids){
		$projectNames = [];
		
		$query = ExternalModules::createQuery();
		$query->add("
			select
				cast(project_id as char) as project_id,
				app_title
			from redcap_projects
			where
		");

		$query->addInClause('project_id', $pids);

		$result = $query->execute();

		while($row = $result->fetch_assoc()){
			$projectNames[$row['project_id']] = $row['app_title'];
		}

		return $projectNames;
	};

	$projectNames = $getProjectNames(array_keys($referencesByProject));

	echo "<table class='table unsafe-edocs'>";

	foreach(['Project ID', 'Project Name', 'Actions'] as $value){
		echo "<th>$value</th>";
	}

	foreach($referencesByProject as $referencePid=>$references){
		?>
		<tr data-pid="<?=$referencePid?>">
			<td>
				<?=$referencePid?>
			</td>
			<td>
				<a href='<?=APP_PATH_WEBROOT_FULL . APP_PATH_WEBROOT . "index.php?pid=$referencePid"?>' style='text-decoration: underline'><?=$projectNames[$referencePid]?></a>
			</td>
			<td>
				<button class="show-details">Show Details</button>
				<button class="copy-edocs">Copy EDocs</button>
			</td>
		</tr>
		<?php
	}

	?>
	</table>
	<script>
		$(function(){
			var referencesByProject = <?=json_encode($referencesByProject)?>;
			var table = $('table.unsafe-edocs')

			var getPID = function(element){
				return $(element).closest('tr').data('pid')
			}

			var copyEdocs = function(pid){
				var loadingOverlay = $('<div class="modal-backdrop" style="opacity: .4;"></div>');
				$('body').append(loadingOverlay)
				$.post('ajax/copy-edocs.php', {pid: pid}, function(data){
					if(data === 'success'){
						location.reload()
					}
					else{
						loadingOverlay.fadeOut(200)
						console.log("Copy EDoc Response:", data)
						simpleDialog('An error occurred while copying edocs for this project.  See the browser console for details.')
					}
				})
			};

			table.find('button.show-details').click(function(){
				simpleDialog("<pre style='max-height: 50vh'>" + JSON.stringify(referencesByProject[getPID(this)], null, 2) + "</pre>")
			})

			table.find('button.copy-edocs').click(function(){
				var pid = getPID(this)

				var warningMessage = "<b>WARNING</b>: By continuing you are agreeing to the following:<br>"
					+ "<ul>"
					+ "<li>I accept the risk that this action may corrupt this project's module settings</li>"
					+ "<li>I have a recent backup of all <b>redcap_external_module_settings</b> table rows for this project</li>"
					+ "<li>I know how to safely use SQL queries to remove the module settings for this project restore them from my backup</li>"
					+ "</ul>"

				simpleDialog(
					warningMessage,
					null,
					null,
					700,
					null,
					'Cancel',
					function(){
						copyEdocs(pid)
					},
					'Continue'
				)
			})
		})
	</script>
	<?php
}

require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php';