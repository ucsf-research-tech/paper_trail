<?php
namespace ExternalModules;
use Exception;

require_once __DIR__ . '/../../redcap_connect.php';

// It's OK for any authenticated user to be able to reach this point and trigger this request.

$return_data['message'] = "success";

if (!isset($_POST) || empty($_POST) || empty($_POST['prefix']) || $_POST['prefix'] == "" || empty($_POST['version']) || $_POST['version'] == "" || !is_numeric($_GET['pid'])) {
    $return_data['error_message'] .= "No module information was passed to define the module.\n";
}
else {
    try {
		$config = ExternalModules::getConfig($_POST['prefix'], $_POST['version']); // Admins will get English module name.
		$module_name = strip_tags($config["name"]); // No HTML for display in email subjects.
		// Add to To-Do List
		$todo_type = "module activation";
		$action_url = APP_URL_EXTMOD . 'manager/activation-request.php?pid=' . $project_id . '&prefix=' . $_POST['prefix'];
		$project_url = APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION . '/index.php?pid=' . $project_id;
		$request_id = \ToDoList::insertAction(UI_ID, $project_contact_email, $todo_type, $action_url, $project_id);
		$action_url .= "&request_id=$request_id";
		// Set email to send
		if ($send_emails_admin_tasks)
		{
			$from = $user_email;
			$fromName = trim("$user_firstname $user_lastname");
			$to = [$project_contact_email];
			$subject = "[REDCap] \"" . USERID . "\" requests an External Module be activated";
			$message = "$user_firstname $user_lastname (" . USERID . ") requests that the External Module \"<b>{$module_name} ({$_POST['prefix']})</b>\" be activated for the project named \""
				. \RCView::a(array('href' => $project_url), strip_tags($app_title)) . "\".<br><br>"
				. \RCView::a(array('href' => $action_url), "Click here to approve the External Module activation request");
			$email = ExternalModules::sendBasicEmail($from, $to, $subject, $message, $fromName);
			if (!$email) {
				$return_data['error_message'] .= "Mail delivery was unable to be completed.\n";
			}
		}
    } catch (Throwable $e) {
        // The problem is likely due to loading the configuration.  Ignore this Exception.
        $return_data['error_message'] .= "Failure loading external module configuration: " . $e->getMessage() . "\n";
	} catch (Exception $e) {
		// The problem is likely due to loading the configuration.  Ignore this Exception.
		$return_data['error_message'] .= "Failure loading external module configuration: " . $e->getMessage() . "\n";
	}
}
echo json_encode($return_data);