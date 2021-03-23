<?php

class PDF
{
	// Main endpoint function
	public static function output()
	{
		extract($GLOBALS);

		// Check if coming from survey or authenticated form
		if (isset($_GET['s']) && !empty($_GET['s']))
		{
			// Call config_functions before config file in this case since we need some setup before calling config
			require_once dirname(dirname(__FILE__)) . '/Config/init_functions.php';
			// Validate and clean the survey hash, while also returning if a legacy hash
			$hash = $_GET['s'] = Survey::checkSurveyHash();
			// Set all survey attributes as global variables
			Survey::setSurveyVals($hash);
			// Now set $_GET['pid'] before calling config
			$_GET['pid'] = $project_id;
			// Set flag for no authentication for survey pages
			define("NOAUTH", true);
		}

		require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

		// GLOBAL VAR WORKAROUND: If we're including this file from INSIDE a method (e.g., REDCap::getPDF),
		// the global variables used in that file will not be defined, so we need to loop through ALL global variables to make them
		// local variables in this scope. Use crude method to detect if in global scope.
		if (isset($GLOBALS['system_offline']) && (!isset($system_offline) || $GLOBALS['system_offline'] != $system_offline)) {
			foreach (array_keys($GLOBALS) as $key) {
				if (strpos($key, '_') === 0 || $key == 'GLOBALS') continue;
				$$key = $GLOBALS[$key];
			}
		}

		// If a survey response, get record, event, form instance
		if (isset($_GET['s']) & isset($_GET['return_code']))
		{
			// Obtain required variables
			$participant_id = Survey::getParticipantIdFromHash($hash);
			$partArray = Survey::getRecordFromPartId(array($participant_id));
			$_GET['id'] = $partArray[$participant_id];
			$_GET['event_id'] = $event_id;
			$_GET['page'] = $form_name;
			$return_code = Survey::getSurveyReturnCode($_GET['id'], $_GET['page'], $_GET['event_id'], $_GET['instance'], true);
			// Verify the return code
			if ($_GET['return_code'] != $return_code) exit("ERROR!");
			// Download the PDF!
		}

		// Must have PHP extention "mbstring" installed in order to render UTF-8 characters properly AND also the PDF unicode fonts installed
		$pathToPdfUtf8Fonts = APP_PATH_WEBTOOLS . "pdf" . DS . "font" . DS . "unifont" . DS;
		if (function_exists('mb_convert_encoding') && is_dir($pathToPdfUtf8Fonts)) {
			// Define the UTF-8 PDF fonts' path
			define("FPDF_FONTPATH",   APP_PATH_WEBTOOLS . "pdf" . DS . "font" . DS);
			define("_SYSTEM_TTFONTS", APP_PATH_WEBTOOLS . "pdf" . DS . "font" . DS);
			// Set contant
			define("USE_UTF8", true);
			// Use tFPDF class for UTF-8 by default
			if ($project_encoding == 'chinese_utf8' || $project_encoding == 'chinese_utf8_traditional') {
				require_once APP_PATH_LIBRARIES . "PDF_Unicode.php";
			} else {
				require_once APP_PATH_LIBRARIES . "tFPDF.php";
			}
		} else {
			// Set contant
			define("USE_UTF8", false);
			// Use normal FPDF class
			require_once APP_PATH_LIBRARIES . "FPDF.php";
		}
		// If using language 'Japanese', then use MBFPDF class for multi-byte string rendering
		if ($project_encoding == 'japanese_sjis')
		{
			require_once APP_PATH_LIBRARIES . "MBFPDF.php"; // Japanese
			// Make sure mbstring is installed
			if (!function_exists('mb_convert_encoding'))
			{
				exit("ERROR: In order for multi-byte encoded text to render correctly in the PDF, you must have the PHP extention \"mbstring\" installed on your web server.");
			}
		}

		// Save fields into metadata array
		$draftMode = false;
		if (isset($_GET['page'])) {
			// Check if we should get metadata for draft mode or not
			$draftMode = ($status > 0 && isset($_GET['draftmode']));
			$metadata_table = ($draftMode) ? "redcap_metadata_temp" : "redcap_metadata";
			// Make sure form exists first
			if ((!$draftMode && !isset($Proj->forms[$_GET['page']])) || ($draftMode && !isset($Proj->forms_temp[$_GET['page']]))) {
				exit('ERROR!');
			}
			$Query = "select * from $metadata_table where project_id = $project_id and ((form_name = '{$_GET['page']}'
				  and field_name != concat(form_name,'_complete')) or field_name = '$table_pk') order by field_order";
		} else {
			$Query = "select * from redcap_metadata where project_id = $project_id and
				  (field_name != concat(form_name,'_complete') or field_name = '$table_pk') order by field_order";
		}
		$QQuery = db_query($Query);
		$metadata = array();
		while ($row = db_fetch_assoc($QQuery))
		{
			// If field is an "sql" field type, then retrieve enum from query result
			if ($row['element_type'] == "sql") {
				$row['element_enum'] = getSqlFieldEnum($row['element_enum'], PROJECT_ID, $_GET['id'], $_GET['event_id'], $_GET['instance'], null, null, $_GET['page']);
			}
			// If PK field...
			if ($row['field_name'] == $table_pk) {
				// Ensure PK field is a text field
				$row['element_type'] = 'text';
				// When pulling a single form other than the first form, change PK form_name to prevent it being on its own page
				if (isset($_GET['page'])) {
					$row['form_name'] = $_GET['page'];
				}
			}
			// Store metadata in array
			$metadata[] = $row;
		}


		// In case we need to output the Draft Mode version of the PDF, set $Proj object attributes as global vars
		global $ProjMetadata, $ProjForms;
		if ($draftMode) {
			$ProjMetadata = $Proj->metadata_temp;
			$ProjForms = $Proj->forms_temp;
			$ProjMatrixGroupNames = $Proj->matrixGroupNamesTemp;
		} else {
			$ProjMetadata = $Proj->metadata;
			$ProjForms = $Proj->forms;
			$ProjMatrixGroupNames = $Proj->matrixGroupNames;
		}

		// Initialize values
		$data = array();
		$study_id_event = "";
		$logging_description = "Download data entry form as PDF" . (isset($_GET['id']) ? " (with data)" : "");


		// Check export rights
		if ((isset($_GET['id']) || isset($_GET['allrecords'])) && $user_rights['data_export_tool'] == '0') {
			exit($lang['data_entry_233']);
		}


		// GET SINGLE RECORD'S DATA (ALL FORMS for ALL EVENTS or SINGLE EVENT if event_id provided)
		if (isset($_GET['id']) && !isset($_GET['page']))
		{
			// Set logging description
			$logging_description = "Download all data entry forms as PDF (with data)";
			// Get all data for this record
			$params = array('records'=>$_GET['id'], 'events'=>(isset($_GET['event_id']) ? $_GET['event_id'] : array()), 'groups'=>$user_rights['group_id'], 'removeMissingDataCodes'=>true);
			$data = Records::getData($params);
			if (!isset($data[$_GET['id']])) $data = array();
		}

		// GET SINGLE RECORD'S DATA (SINGLE FORM ONLY)
		elseif (isset($_GET['id']) && isset($_GET['page']))
		{
			$id = trim($_GET['id']);
			// Ensure the event_id belongs to this project, and additionally if longitudinal, can be used with this form
			if (isset($_GET['event_id'])) {
				if (!$Proj->validateEventId($_GET['event_id'])
					// Check if form has been designated for this event
					|| !$Proj->validateFormEvent($_GET['page'], $_GET['event_id'])
					|| ($id == "") )
				{
					if ($longitudinal) {
						redirect(APP_PATH_WEBROOT . "DataEntry/record_home.php?pid=" . PROJECT_ID);
					} else {
						redirect(APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . PROJECT_ID . "&page=" . $_GET['page']);
					}
				}
			}
			// Get all data for this record
			$params = array('records'=>$id, 'fields'=>array_merge(array($table_pk), array_keys($Proj->forms[$_GET['page']]['fields'])),
				'events'=>(isset($_GET['event_id']) ? $_GET['event_id'] : array()), 'groups'=>$user_rights['group_id'], 'removeMissingDataCodes'=>true);
			$data = Records::getData($params);
			if (!isset($data[$id])) $data = array();
			// Repeating forms only: Remove all other instances of this form to leave only the current instance
			if (isset($data[$id]['repeat_instances']) && (
					($Proj->isRepeatingForm($_GET['event_id'], $_GET['page']) && count($data[$id]['repeat_instances'][$_GET['event_id']][$_GET['page']]) > 1)
					|| ($Proj->isRepeatingEvent($_GET['event_id']) && count($data[$id]['repeat_instances'][$_GET['event_id']]['']) > 1)
				)) {
				$repeatingFormName = $Proj->isRepeatingEvent($_GET['event_id']) ? '' : $_GET['page'];
				foreach (array_keys($data[$id]['repeat_instances'][$_GET['event_id']][$repeatingFormName]) as $repeat_instance) {
					if ($repeat_instance == $_GET['instance']) continue;
					unset($data[$id]['repeat_instances'][$_GET['event_id']][$repeatingFormName][$repeat_instance]);
				}
			}
			// Append e-Consent footer info?
			if (isset($_GET['appendEconsentFooter']) && isset($Proj->forms[$_GET['page']]['survey_id'])
				&& $Proj->surveys[$Proj->forms[$_GET['page']]['survey_id']]['pdf_auto_archive'] == '2')
			{
				list ($nameDobText, $versionText, $typeText) = Survey::getEconsentOptionsData($Proj->project_id, $_GET['id'], $_GET['page']);
				$versionTypeTextArray = array();
				if ($nameDobText != '')	$versionTypeTextArray[] = $nameDobText;
				if ($versionText != '')	$versionTypeTextArray[] = $lang['data_entry_428']." ".$versionText;
				if ($typeText != '') 	$versionTypeTextArray[] = $lang['data_entry_429']." ".$typeText;
				// Set as flag to add this text to footer
				$_GET['appendToFooter'] = implode(', ', $versionTypeTextArray);
			}

		}

		// GET ALL RECORDS' DATA
		elseif (isset($_GET['allrecords']))
		{
			// Set logging description
			$logging_description = "Download all data entry forms as PDF (all records)";
			// Get all data for this record
			$params = array('groups'=>$user_rights['group_id'], 'removeMissingDataCodes'=>true);
			$data = Records::getData($params);
			// If project contains zero records, then the PDF will be blank. So return a message to user about this.
			if (empty($data)) {
				include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
				print  $lang['data_export_tool_220'];
				print 	RCView::div(array('style'=>'padding:20px 0;'),
					renderPrevPageBtn("DataExport/index.php?other_export_options=1&pid=$project_id",$lang['global_77'],false)
				);
				include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
				exit;
			}
		}

		// BLANK PDF FOR SINGLE FORM OR ALL FORMS
		else
		{
			$data[''][''] = null;
			// Set logging description
			if (isset($_GET['page'])) {
				$logging_description = "Download data entry form as PDF";
			} else {
				$logging_description = "Download all data entry forms as PDF";
			}
		}

		## REFORMAT DATES AND/OR REMOVE DATA VALUES FOR DE-ID RIGHTS.
		## ALSO, ONTOLOGY AUTO-SUGGEST: Obtain labels for the raw notation values.
		if (!isset($data['']) && !empty($data))
		{
			// Get all validation types to use for converting DMY and MDY date formats
			$valTypes = getValTypes();
			$dateTimeFields = $dateTimeValTypes = array();
			foreach ($valTypes as $valtype=>$attr) {
				if (in_array($attr['data_type'], array('date', 'datetime', 'datetime_seconds'))) {
					$dateTimeValTypes[] = $valtype;
				}
			}

			// Create array of MDY and DMY date/time fields
			// and also create array of fields used for ontology auto-suggest
			$field_names = array();
			$ontology_auto_suggest_fields = $ontology_auto_suggest_cats = $ontology_auto_suggest_labels = array();
			foreach ($metadata as $attr) {
				$field_names[] = $attr['field_name'];
				$this_field_enum = $attr['element_enum'];
				// If Text field with ontology auto-suggest
				if ($attr['element_type'] == 'text' && $this_field_enum != '' && strpos($this_field_enum, ":") !== false) {
					// Get the name of the name of the web service API and the category (ontology) name
					list ($this_autosuggest_service, $this_autosuggest_cat) = explode(":", $this_field_enum, 2);
					// Add to arrays
					$ontology_auto_suggest_fields[$attr['field_name']] = array('service'=>$this_autosuggest_service, 'category'=>$this_autosuggest_cat);
					$ontology_auto_suggest_cats[$this_autosuggest_service][$this_autosuggest_cat] = true;
				}
				// If has date/time validation
				elseif (in_array($attr['element_validation_type'], $dateTimeValTypes)) {
					$dateFormat = substr($attr['element_validation_type'], -3);
					if ($dateFormat == 'mdy' || $dateFormat == 'dmy') {
						$dateTimeFields[$attr['field_name']] = $dateFormat;
					}
				}
			}

			// GET CACHED LABELS AUTO-SUGGEST ONTOLOGIES
			if (!empty($ontology_auto_suggest_fields)) {
				// Obtain all the cached labels for these ontologies used
				$subsql = array();
				foreach ($ontology_auto_suggest_cats as $this_service=>$these_cats) {
					$subsql[] = "(service = '".db_escape($this_service)."' and category in (".prep_implode(array_keys($these_cats))."))";
				}
				$sql = "select service, category, value, label from redcap_web_service_cache
					where project_id = $project_id and (" . implode(" or ", $subsql) . ")";
				$q = db_query($sql);
				while ($row = db_fetch_assoc($q)) {
					$ontology_auto_suggest_labels[$row['service']][$row['category']][$row['value']] = $row['label'];
				}
				// Remove unneeded variable
				unset($ontology_auto_suggest_cats);
			}

			// If user has de-id rights, then get list of fields
			$deidFieldsToRemove = ($user_rights['data_export_tool'] > 1)
				? DataExport::deidFieldsToRemove($field_names, ($user_rights['data_export_tool'] == '3'))
				: array();
			$deidFieldsToRemove = array_fill_keys($deidFieldsToRemove, true);
			unset($field_names);
			// Set flags
			$checkDateTimeFields = !empty($dateTimeFields);
			$checkDeidFieldsToRemove = !empty($deidFieldsToRemove);

			// LOOP THROUGH ALL DATA VALUES
			if (!empty($ontology_auto_suggest_fields) || $checkDateTimeFields || $checkDeidFieldsToRemove) {
				foreach ($data as $this_record=>&$event_data) {
					foreach ($event_data as $this_event_id1=>&$field_data) {
						if ($this_event_id1 == 'repeat_instances') {
							$eventNormalized = $event_data['repeat_instances'];
						} else {
							$eventNormalized = array();
							$eventNormalized[$this_event_id1][""][0] = $event_data[$this_event_id1];
						}
						foreach ($eventNormalized as $this_event_id=>$data1) {
							foreach ($data1 as $repeat_instrument=>$data2) {
								foreach ($data2 as $instance=>$data3) {
									foreach ($data3 as $this_field=>$this_value) {
										// If value is not blank
										if ($this_value != '') {
											// When outputting labels for TEXT fields with ONTOLOGY AUTO-SUGGEST, replace value with cached label
											if (isset($ontology_auto_suggest_fields[$this_field])) {
												// Replace value with label
												if ($ontology_auto_suggest_labels[$ontology_auto_suggest_fields[$this_field]['service']][$ontology_auto_suggest_fields[$this_field]['category']][$this_value]) {
													$this_value = $ontology_auto_suggest_labels[$ontology_auto_suggest_fields[$this_field]['service']][$ontology_auto_suggest_fields[$this_field]['category']][$this_value] . " ($this_value)";
												}
												if ($instance == '0') {
													$data[$this_record][$this_event_id][$this_field] = $this_value;
												} else {
													$data[$this_record][$this_event_id1][$this_event_id][$repeat_instrument][$instance][$this_field] = $this_value;
												}
											}
											// If a DMY or MDY datetime field, then convert value
											elseif ($checkDeidFieldsToRemove && isset($deidFieldsToRemove[$this_field])) {
												// If this is the Record ID field, then merely hash it IF the user has de-id or remove identifiers export rights
												if ($this_field == $Proj->table_pk) {
													if ($Proj->table_pk_phi) {
														if ($instance == '0') {
															$data[$this_record][$this_event_id][$this_field] = md5($salt . $this_record . $__SALT__);
														} else {
															$data[$this_record][$this_event_id1][$this_event_id][$repeat_instrument][$instance][$this_field] = md5($salt . $this_record . $__SALT__);
														}
													}
												} else {
													if ($instance == '0') {
														$data[$this_record][$this_event_id][$this_field] = DEID_TEXT;
													} else {
														$data[$this_record][$this_event_id1][$this_event_id][$repeat_instrument][$instance][$this_field] = DEID_TEXT;
													}
												}
											}
											// If a DMY or MDY datetime field, then convert value
											elseif ($checkDateTimeFields && isset($dateTimeFields[$this_field])) {
												if ($instance == '0') {
													$data[$this_record][$this_event_id][$this_field] = DateTimeRC::datetimeConvert($this_value, 'ymd', $dateTimeFields[$this_field]);;
												} else {
													$data[$this_record][$this_event_id1][$this_event_id][$repeat_instrument][$instance][$this_field] = DateTimeRC::datetimeConvert($this_value, 'ymd', $dateTimeFields[$this_field]);;
												}
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}

		// If form was downloaded from Shared Library and has an Acknowledgement, render it here
		$acknowledgement = SharedLibrary::getAcknowledgement($project_id, isset($_GET['page']) ? $_GET['page'] : '');

		// Loop through metadata and replace any &nbsp; character codes with spaces.
		$pdfHasData = !isset($data['']);
		foreach ($metadata as &$attr) {
			$attr['element_label'] = str_replace('&nbsp;', ' ', $attr['element_label']);
			$attr['element_enum'] = str_replace('&nbsp;', ' ', $attr['element_enum']);
			$attr['element_note'] = str_replace('&nbsp;', ' ', $attr['element_note']);
			$attr['element_preceding_header'] = str_replace('&nbsp;', ' ', $attr['element_preceding_header']);
			// Also replace any embedded fields {field} and {field:icons} with square bracket counterpart [field] to force data to be piped to simulate Field Embedding.
			$attr['element_label'] = Piping::replaceEmbedVariablesInLabel($attr['element_label'], $project_id, $attr['form_name'], $pdfHasData, !$pdfHasData);
			$attr['element_enum'] = Piping::replaceEmbedVariablesInLabel($attr['element_enum'], $project_id, $attr['form_name'], $pdfHasData, !$pdfHasData);
			$attr['element_note'] = Piping::replaceEmbedVariablesInLabel($attr['element_note'], $project_id, $attr['form_name'], $pdfHasData, !$pdfHasData);
			$attr['element_preceding_header'] = Piping::replaceEmbedVariablesInLabel($attr['element_preceding_header'], $project_id, $attr['form_name'], $pdfHasData, !$pdfHasData);
		}

		// Logging (but don't do it if this script is being called via the API or via a plugin)
		if (!defined("API") && !defined("PLUGIN") && !(isset($_GET['s']) && !empty($_GET['s'])) && !isset($_GET['__noLogPDFSave'])) {
			$page = isset($_GET['page']) ? $_GET['page'] : '';
			Logging::logEvent("", "redcap_metadata", "MANAGE", (isset($_GET['id']) ? $_GET['id'] : ""), "form_name = $page", $logging_description);
		}

		// Call the PDF hook
		$hookReturn = Hooks::call('redcap_pdf', array($project_id, $metadata, $data, (isset($_GET['page']) ? $_GET['page'] : ""), (isset($_GET['id']) ? $_GET['id'] : ""), (isset($_GET['event_id']) ? $_GET['event_id'] : ""), $_GET['instance']));
		if (isset($hookReturn['metadata']) && is_array($hookReturn['metadata'])) {
			// Overwrite $metadata if hook manipulated it
			$metadata = $hookReturn['metadata'];
		}
		if (isset($hookReturn['data']) && is_array($hookReturn['data'])) {
			// Overwrite $data if hook manipulated it
			$data = $hookReturn['data'];
		}

		// Render the PDF
		PDF::renderPDF($metadata, $acknowledgement, strip_tags(label_decode($app_title)), $data, isset($_GET['compact']));
	}

	// Append custom text to header
	public static function appendToHeader($pdf)
	{
		if (isset($_GET['appendToHeader'])) {
			$pdf->SetFont(FONT,'B',8);
			$pdf->Cell(0,2,rawurldecode(urldecode($_GET['appendToHeader'])),0,1,'R');
			$pdf->Ln();
		}
		return $pdf;
	}

	//Check if need to start a new page with this question
	public static function new_page_check($num_lines, $pdf, $y_units_per_line, $bottom_of_page, $study_id_event)
	{
		if (($y_units_per_line * $num_lines) + $pdf->GetY() > $bottom_of_page) {
			$pdf->AddPage();
			// Set logo at bottom
			PDF::setFooterImage($pdf);
			// Set "Confidential" text at top
			$pdf = PDF::confidentialText($pdf);
			// If appending text to header
			$pdf = PDF::appendToHeader($pdf);
			// Add page number
			if ($study_id_event != "" && !isset($_GET['s'])) {
				$pdf->SetFont(FONT,'BI',8);
				$pdf->Cell(0,2,$study_id_event,0,1,'R');
				$pdf->Ln();
			}
			$pdf->SetFont(FONT,'I',8);
			$pdf->Cell(0,5,$GLOBALS['lang']['survey_132'].' '.$pdf->PageNo(),0,1,'R');
			// Line break and reset font
			$pdf->Ln();
			$pdf->SetFont(FONT,'',10);
		}
		return $pdf;
	}

	// Add survey custom question number, if applicable
	public static function addQuestionNumber($pdf, $row_height, $question_num, $isSurvey, $customQuesNum, $num)
	{
		if ($isSurvey)
		{
			if ($customQuesNum && $question_num != "") {
				// Custom numbered
				$currentXPos = $pdf->GetX();
				$pdf->SetX(2);
				$pdf->Cell(0,$row_height,$question_num);
				$pdf->SetX($currentXPos);
			} elseif (!$customQuesNum && is_numeric($num)) {
				// Auto numbered
				$currentXPos = $pdf->GetX();
				$pdf->SetX(2);
				$pdf->Cell(0,$row_height,$num.")");
				$pdf->SetX($currentXPos);
			}
		}
		return $pdf;
	}

	// Set "Confidential" text at top
	public static function confidentialText($pdf)
	{
		global $Proj, $project_encoding;
		// Do not display this for survey respondents
		if (isset($_GET['s']) && !(isset($Proj->project['pdf_custom_header_text']) && $Proj->project['pdf_custom_header_text'] !== null)) {
			return $pdf;
		}
		// Set header text to display
		$headerText = (isset($Proj->project['pdf_custom_header_text']) && $Proj->project['pdf_custom_header_text'] !== null) ? $Proj->project['pdf_custom_header_text'] : System::confidential;
		if ($project_encoding == 'japanese_sjis') {
			$headerText = mb_convert_encoding($headerText, "SJIS", "UTF-8"); //Japanese
		}
		// Get current position, so we can reset it back later
		$y = $pdf->GetY();
		$x = $pdf->GetX();
		// Set new position
		$pdf->SetY(3);
		$pdf->SetX(0);
		// Add text
		$pdf->SetFont(FONT,'I',12);
		$pdf->Cell(0,0,$headerText,0,1,'L');
		// Reset font and positions
		$pdf->SetFont(FONT,'',10);
		$pdf->SetY($y);
		$pdf->SetX($x);
		return $pdf;
	}

	//Set the footer with the URL for the consortium website and the REDCap logo
	public static function setFooterImage($pdf)
	{
		global $Proj;
		// Determine if we should display REDCap logo and URL
		$displayLogoUrl = !(isset($Proj->project['pdf_show_logo_url']) && $Proj->project['pdf_show_logo_url'] == '0');
		// Set position and font
		$pdf->SetY(-4);
		$pdf->SetFont(FONT,'',8);
		// Set the current date/time as the left-hand footer
		$pdf->Cell(40,0,DateTimeRC::format_ts_from_ymd(NOW));
		// Append custom text to footer
		$buffer = 0;
		if (isset($_GET['appendToFooter'])) {
			$pdf->Cell(40,0,trim(rawurldecode(urldecode(ltrim($_GET['appendToFooter'],',')))));
			$pdf->Cell(10,0,'');
			$buffer = -50;
		}
		if ($displayLogoUrl) {
			// Set REDCap Consortium URL as right-hand footer
			$pdf->Cell(95+$buffer,0,'');
			$pdf->Cell(0,0,'projectredcap.org',0,0,'L',false,'https://projectredcap.org');
			$pdf->Image(LOGO_PATH . "redcap-logo-small.png", 176, 289, 24, 7);
		} else {
			// Set "Powered by REDCap" text
			$pdf->Cell(123+$buffer,0,'');
			$pdf->SetTextColor(128,128,128);
			$pdf->Cell(0,0,System::powered_by_redcap);
			$pdf->SetTextColor(0,0,0);
		}
		//Reset position to begin the page
		$pdf->SetY(6);
	}

	// Format the min, mid, and max labels for Sliders
	public static function slider_label($this_text,$char_limit_slider) {
		global $project_encoding;
		$this_text .= " ";
		$slider_lines = array();
		$start_pos = 0;
		// Deal with 2-byte characters in strings
		if ($project_encoding != '') {
			if ($project_encoding == 'japanese_sjis') {
				$this_text = mb_convert_encoding($this_text, "SJIS", "UTF-8"); //Japanese
			}
			foreach (str_split(trim($this_text), $char_limit_slider) as $newline) {
				if ($newline == '') continue;
				$slider_lines[] = trim($newline);
			}
			return $slider_lines;
		}
		// Normal processing
		do {
			$this_line = substr($this_text,$start_pos,$char_limit_slider);
			$end_pos = strrpos($this_line," ");
			$slider_lines[] = substr($this_line,0,$end_pos);
			$start_pos = $start_pos + $end_pos + 1;
		} while ($start_pos < strlen($this_text));
		return $slider_lines;
	}

	//Format question text for questions with vertically-rendered answers
	public static function qtext_vertical($row, $char_limit_q) {
		$this_string = $row['element_label']; // We've already done stripping/replacing: strip_tags(br2nl(str_replace(array("\r\n","\r"), array("\n","\n"), label_decode($row['element_label']))));
		$lines = explode("\n", $this_string);
		$lines2 = array();
		foreach ($lines as $key=>$line) {
			$lines2[] = wordwrap($line, $char_limit_q, "\n", true);
		}
		return explode("\n", implode("\n", $lines2));
	}

	public static function backwardStrpos($haystack, $needle, $offset = 0){
		$length = strlen($haystack);
		$offset = ($offset > 0)?($length - $offset):abs($offset);
		$pos = strpos(strrev($haystack), strrev($needle), $offset);
		return ($pos === false)?false:( $length - $pos - strlen($needle) );
	}

	public static function text_vertical($this_string,$char_limit) {
		$this_string = str_replace(array("\r\n","\r"), array("\n","\n"), html_entity_decode($this_string, ENT_QUOTES));
		$lines = explode("\n", $this_string);
		// Go through each line and place \n to break up into segments based on $char_limit value
		$lines2 = array();
		foreach ($lines as $key=>$line) {
			$lines2[] = wordwrap($line, $char_limit, "\n", true);
		}
		return explode("\n", implode("\n", $lines2));
	}

	//Format answer text for questions with vertically-rendered answers
	public static function atext_vertical_mc($row, $dataNormalized, $char_limit_a, $indent_a, $project_language, $compactDisplay=false)
	{
		global $project_encoding;

		$atext = array();
		$line = array();

		// Set char limit as a little shorter for non-latin chars
		if ($project_encoding != '') {
			$char_limit_a = $char_limit_a-4;
		}

		$row['element_enum'] = strip_tags(label_decode($row['element_enum']));
		if ($project_encoding == 'japanese_sjis') $row['element_enum'] = mb_convert_encoding($row['element_enum'], "SJIS", "UTF-8");

		// Loop through each choice for this field
		foreach (parseEnum($row['element_enum']) as $this_code=>$this_choice)
		{
			if ($compactDisplay && $row['element_type'] != 'descriptive') {
				if (is_array($dataNormalized[$row['field_name']])) {
					// Checkbox with no choices checked
					if ($dataNormalized[$row['field_name']][$this_code] == 0) {
						continue;
					}
				} else {
					// Normal field type with no value
					if ($dataNormalized[$row['field_name']] != $this_code) {
						continue;
					}
				}
			}

			// Default: checkbox is unchecked
			$chosen = false;

			// Determine if this row's checkbox needs to be checked (i.e. it has data)
			if (isset($dataNormalized[$row['field_name']])) {
				if (is_array($dataNormalized[$row['field_name']])) {
					// Checkbox fields
					if (isset($dataNormalized[$row['field_name']][$this_code]) && $dataNormalized[$row['field_name']][$this_code] == "1") {
						$chosen = true;
					}
				} elseif ($dataNormalized[$row['field_name']] == $this_code) {
					// Regular fields
					$chosen = true;
				}
			}

			$this_string = trim($this_choice);

			// Deal with 2-byte characters in strings
			if ($project_encoding != '') {
				$indent_this = false;
				foreach (str_split($this_string, $char_limit_a) as $newline) {
					$newline = trim($newline);
					if ($newline == '') continue;
					// Set values for this line of text
					$atext[] = array('chosen'=>$chosen, 'sigil'=>!$indent_this, 'line'=>($indent_this ? $indent_a : '') . $newline);
					$indent_this = true;
				}
			}
			// Latin character language processing
			else {
				$start_pos = 0;
				do {
					$indent_this = false;
					if ($start_pos + $char_limit_a >= strlen($this_string)) {
						if ($start_pos == 0) {
							$this_line = substr($this_string,$start_pos,$char_limit_a); //if only one line of text
						} else {
							$this_line = $indent_a . substr($this_string,$start_pos,$char_limit_a); //for last line of text
							$indent_this = true;
						}
						$end_pos = strlen($this_line);
					} else {
						if ($start_pos == 0) {
							$this_line = substr($this_string,$start_pos,$char_limit_a);
						} else {
							$this_line = $indent_a . substr($this_string,$start_pos,$char_limit_a); //indent all lines after first line
							$indent_this = true;
						}
						$end_pos = strrpos($this_line," "); //for all lines of text except last
					}
					// Set values for this line of text
					$line = array('chosen'=>$chosen, 'sigil'=>true, 'line'=>substr($this_line,0,$end_pos));
					// If secondary line for same choice, then indent and do not display checkbox
					if ($indent_this) {
						$line['sigil'] = false;
						$end_pos = $end_pos - strlen($indent_a);
					}
					// Add line of text to array
					$atext[] = $line;
					// Set start position for next loop
					$start_pos = $start_pos + $end_pos + 1;
				} while ($start_pos <= strlen($this_string));
			}
		}

		return $atext;
	}

	// If all questions in the previous section were hidden, then manually remove the SH from the $pdf object
	public static function removeSectionHeader($pdf)
	{
		global $pdfLastSH;
		if ($pdfLastSH !== null) $pdf = clone $pdfLastSH;
		return $pdf;
	}

	// If all questions in the previous matrix were hidden, then manually remove the matrix header from the $pdf object
	public static function removeMatrixHeader($pdf)
	{
		global $pdfLastMH;
		if ($pdfLastMH !== null) $pdf = clone $pdfLastMH;
		return $pdf;
	}

	/**
	 * Build and render the PDF
	 */
	public static function renderPDF($metadata, $acknowledgement, $project_name = "", $Data = array(), $compactDisplay=false)
	{
		global $Proj, $table_pk, $table_pk_label, $longitudinal, $surveys_enabled,
			   $salt, $__SALT__, $user_rights, $lang, $ProjMetadata, $ProjForms, $project_encoding;

		// Set repeating instrument/event var
		$hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
		// Increase memory limit in case needed for intensive processing
		System::increaseMemory(2048);
		//Set the character limit per line for questions (left column) and answers (right column)
		$char_limit_q = 54; //question char limit per line
		$char_limit_a = 51; //answer char limit per line
		$char_limit_slider = 18; //slider char limit per line
		//Set column width and row height
		$col_width_a = 105; //left column width
		$col_width_b = 75;  //right column width
		$sigil_width = 4;
		$atext_width = 70;
		$row_height = 4;
		//Set other widths
		$page_width = 190;
		$matrix_label_width = 55;
		//Indentation string
		$indent_q = "     ";
		$indent_a = "";
		//Parameters for determining page breaks
		$est_char_per_line = 110;
		$y_units_per_line = 4.5;
		$bottom_of_page = 275;
		// Slider parameters
		$rect_width = 1.5;
		$num_rect = 50;
		// Set max width of an entire line in the PDF
		$max_line_width = 190;
		// Collect $pdf object in temporary form for section headers and matrix headers
		// in case we have to roll back the PDF if hiding those headers due to branching logic.
		global $pdfLastSH, $pdfLastMH;
		$pdfLastSH = $pdfLastMH = null;

		// Create array of event_ids=>event names
		$events = array();
		if (isset($Proj))
		{
			foreach ($Proj->events as $this_arm_num=>$this_arm)
			{
				foreach ($this_arm['events'] as $this_event_id=>$event_attr)
				{
					$events[$this_event_id] = strip_tags(label_decode($event_attr['descrip']));
				}
			}
		}
		else {
			$events[1] = 'Event 1';
		}

		// Determine if in Consortium website or REDCap core
		if (!defined("PROJECT_ID"))
		{
			// We are in Consortium website
			$project_language = 'English'; // set as default (English)
			$project_encoding = '';
			define("LOGO_PATH", APP_PATH_DOCROOT . "resources/img/");
			// Set font constant
			define("FONT", "Arial");
		}
		else
		{
			// We are in REDCap core
			global $project_language;
			define("LOGO_PATH", APP_PATH_DOCROOT . "Resources/images/");
			// Set font constant
			if ($project_encoding == 'japanese_sjis')
			{
				// Japanese
				define("FONT", KOZMIN);
			}
			else
			{
				// If using UTF-8 encoding, include other fonts
				if (USE_UTF8) {
					if ($project_encoding == 'chinese_utf8') {
						// Chinese Simplified
						define("FONT", 'uGB');
					} elseif ($project_encoding == 'chinese_utf8_traditional') {
						// Chinese Traditional
						define("FONT", 'uni');
					} else {
						// Normal UTF-8 (add-on package)
						define("FONT", "DejaVu");
					}
				} else {
					// Default installation
					define("FONT", "Arial");
				}
			}
		}

		//Begin creating PDF
		if ($project_encoding == 'japanese_sjis')
		{
			//Japanese
			$pdf = new FPDF_HTML();
			$pdf->AddMBFont(FONT ,'SJIS');
			$project_name = mb_convert_encoding($project_name, "SJIS", "UTF-8");
		}
		elseif ($project_encoding == 'chinese_utf8' || $project_encoding == 'chinese_utf8_traditional')
		{
			// Chinese
			$pdf = new FPDF_HTML();
			if (USE_UTF8) {
				// using adobe fonts
				if ($project_encoding == 'chinese_utf8') {
					// Chinese Simplified
					$pdf->AddUniGBhwFont();
				} elseif ($project_encoding == 'chinese_utf8_traditional') {
					// Chinese Traditional
					$pdf->AddUniCNSFont();
				}
			}
		}
		else
		{
			// Normal
			$pdf = new FPDF_HTML();
			// If using UTF-8 encoding, include other fonts
			if (USE_UTF8) {
				$pdf->AddFont('DejaVu','','DejaVuSansCondensed.ttf',true);
				$pdf->AddFont('DejaVu','B','DejaVuSansCondensed-Bold.ttf',true);
				$pdf->AddFont('DejaVu','I','DejaVuSansCondensed-Oblique.ttf',true);
				$pdf->AddFont('DejaVu','BI','DejaVuSansCondensed-BoldOblique.ttf',true);
			}
		}

		// Set paging settings
		$pdf->SetAutoPageBreak('auto'); # on by default with 2cm margin
		/*
		$pdf->AliasNbPages(); # defines a string which is substituted with total number of pages when the
							  # document is closed: '{nb}' by default.
		*/

		// Obtain custom record label & secondary unique field labels for all relevant records.
		$extra_record_labels = array();
		if (!isset($Data['']) && isset($Proj->project['pdf_hide_secondary_field']) && $Proj->project['pdf_hide_secondary_field'] == '0') {
			// Only get the extra labels if we have some data in $Data
			$extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords(array_keys($Data), true);
		}

		// PIPING: Obtain all saved data for all records involved (just in case we need to pipe any into a label)
		$piping_record_data = (isset($Data[''])) ? array() : Records::getData(array('records'=>array_keys($Data), 'returnEmptyEvents'=>true));

		## BRANCHING LOGIC
		// Loop through all fields with branching logic, validate the syntax, and if valid add to array so we can eval on a per record basis.
		// This eliminates any branching with incorrect syntax (i.e. has javascript) that would cause a field to be hidden.
		// If the syntax is invalid, we'll always show the field in the PDF
		$branchingLogicValid = array();
		if (!isset($Data[''])) {
			// Loop through all fields
			foreach ($metadata as $row) {
				if ($row['branching_logic'] != '' && LogicTester::isValid($row['branching_logic'])) {
					$branchingLogicValid[$row['field_name']] = true;
				}
			}
		}

		// Get all embedded fields in the project
		$embeddedFields = Piping::getEmbeddedVariables($Proj->project_id);

		## LOOP THROUGH ALL EVENTS/RECORDS
		foreach ($Data as $record=>$event_array)
		{
			// Reset for new record
			$pdfLastSH = $pdfLastMH = null;
			// Loop through records within the event
			foreach (array_keys($event_array) as $event_id)
			{
				if ($event_id == 'repeat_instances') {
					$eventNormalized = $event_array['repeat_instances'];
				} else {
					$eventNormalized = array();
					$eventNormalized[$event_id][""][0] = $event_array[$event_id];
				}

				// Loop through the normalized structure for consistency even with repeating instances
				foreach ($eventNormalized as $event_id=>$nattr1)
				{
					// Display event name if longitudinal
					$event_name = (isset($events[$event_id]) ? $events[$event_id] : "");

					// Get record name to display (top right of pdf), if displaying data for a record
					$study_id_event = "";
					if ($record != '')
					{
						// Is PK an identifier? If so, then hash it
						$pk_display_val = ($ProjMetadata[$table_pk]['field_phi'] && $user_rights['data_export_tool'] > 1) ? md5($salt . $record . $__SALT__) : $record;
						// Set top-left corner display labels
						if(isset($Proj->project['pdf_hide_record_id']) && $Proj->project['pdf_hide_record_id'] == '0') {
							$study_id_event = "$table_pk_label $pk_display_val" .
								strip_tags(isset($extra_record_labels[$record]) ? " ".$extra_record_labels[$record] : "");
							// Display event name if longitudinal
							if (isset($longitudinal) && $longitudinal) {
								$study_id_event .= " ($event_name)";
							}
							if ($project_encoding == 'japanese_sjis') {
								$study_id_event = mb_convert_encoding($study_id_event, "SJIS", "UTF-8"); //Japanese
							}
						}
						// Add survey identifier to $study_id_event, if identifier exists and user does not have de-id rights
						$removeIdentifierFields = (!isset($user_rights['data_export_tool']) || ($user_rights['data_export_tool'] == '3' || $user_rights['data_export_tool'] == '2'));
						$response_time_text = array();
						if (isset($surveys_enabled) && $surveys_enabled && !isset($_GET['hideSurveyTimestamp']) && !$removeIdentifierFields)
						{
							$sql = "select p.participant_identifier, r.first_submit_time, r.completion_time, s.form_name, r.instance
									from redcap_surveys s, redcap_surveys_participants p, redcap_events_metadata e,
									redcap_surveys_response r where s.project_id = ".PROJECT_ID."
									and e.event_id = p.event_id and e.event_id = $event_id and s.survey_id = p.survey_id
									and p.participant_id = r.participant_id and r.record = '".db_escape($record)."'
									order by r.completion_time, r.first_submit_time";
							$q = db_query($sql);
							while ($rowsu = db_fetch_assoc($q)) {
								// Append identifier
								if ($rowsu['participant_identifier'] != "") {
									$study_id_event .= " - " . $rowsu['participant_identifier'];
								}
								// Set response time also
								if ($rowsu['completion_time'] == "" && $rowsu['first_submit_time'] != "") {
									// Partial
									$response_time_text[$rowsu['form_name']][$rowsu['instance']] = "{$lang['data_entry_101']} {$lang['data_entry_100']} ".DateTimeRC::format_ts_from_ymd($rowsu['first_submit_time']).".";
								} elseif ($rowsu['completion_time'] != "") {
									// Complete
									$response_time_text[$rowsu['form_name']][$rowsu['instance']] = $lang['data_entry_100']." ".DateTimeRC::format_ts_from_ymd($rowsu['completion_time']).".";
								}
							}
						}
					}

					$last_repeat_instrument = $last_repeat_instance = null;
					foreach ($nattr1 as $repeat_instrument=>$nattr2)
					{
						foreach ($nattr2 as $repeat_instance=>$dataNormalized)
						{
							// Set temp metadata array for this record/event
							$this_metadata = $metadata;

							// print "<hr>$record, $event_id, $repeat_instrument, $repeat_instance";
							// print_array($dataNormalized);

							// Skip if this is a repeating instrument but currently on base instance (or if a repeating instance and not a field on repeating form)
							if ($hasRepeatingFormsEvents) {
								foreach ($this_metadata as $key=>$row) {
									$isRepeatingForm = $Proj->isRepeatingForm($event_id, $row['form_name']);
									$isRepeatingEvent = $Proj->isRepeatingEvent($event_id);
									if (($repeat_instance == 0 && $isRepeatingForm)
										|| ($repeat_instance != 0 && !$isRepeatingForm && !$isRepeatingEvent)
										|| ($repeat_instance != 0 && $isRepeatingForm && $repeat_instrument != $row['form_name'])
										|| ($repeat_instance != 0 && $isRepeatingEvent && !in_array($row['form_name'], $Proj->eventsForms[$event_id]))
									) {
										// Remove non-relevant field for this instance
										unset($this_metadata[$key]);
									}
								}
							}

							// PIPING: If exporting a PDF with data, then any labels, notes, choices that have field_names in them for piping, replace out with data.
							// Set array of field attributes to look at in $metadata
							$elements_attr_to_replace = array('element_label', 'element_enum', 'element_preceding_header', 'element_note');
							// Loop through all fields to be displayed on this form/survey
							foreach ($this_metadata as $key=>$attr)
							{
								// Loop through all relevant field attributes for this field
								foreach ($elements_attr_to_replace as $this_attr_type)
								{
									// Get value for the current attribute
									$this_string = $attr[$this_attr_type];
									// If a field, check field label
									if ($this_string != '')
									{
										if ($record != '') {
											// Do piping only if there is data
											$this_metadata[$key][$this_attr_type] = Piping::replaceVariablesInLabel($this_string, $record, $event_id, $repeat_instance, $piping_record_data, true, null, false, "", 1, false, true, $attr['form_name']);
										}
										// Perform string replacement
										$this_metadata[$key][$this_attr_type] = str_replace(array("\t","&nbsp;"), array(" "," "), label_decode($this_metadata[$key][$this_attr_type]));
									}
								}
							}

							// Loop through each field to create row in PDF
							$num = 1;
							$last_form = "";
							$num_rows = count($this_metadata);
							$this_metadata = array_values($this_metadata);
							for ($row_idx = 0; $row_idx < $num_rows; $row_idx++)
								// foreach ($this_metadata as $row)
							{
								$row = $this_metadata[$row_idx];
								$next_row = $row_idx < $num_rows - 1 ? $this_metadata[$row_idx + 1] : null;
								$instrument_about_to_change = $next_row != null && $row["form_name"] != $next_row["form_name"];

								// If longitudinal, make sure this form is designated for this event (if not, skip this loop and continue)
								if ($longitudinal && $record != '' && !in_array($row['form_name'], $Proj->eventsForms[$event_id]))
								{
									continue;
								}

								// @HIDDEN-PDF action tags
								if (Form::hasHiddenPdfActionTag($row['misc'])) {
									continue;
								}

								// If a survey respondent is downloading the PDF, hide all fields with @HIDDEN and @HIDDEN-SURVEY action tags
								if (isset($_GET['s']) && Form::hasHiddenOrHiddenSurveyActionTag($row['misc'])) {
									continue;
								}

								// If field is embedded in another field, then skip
								if (in_array($row['field_name'], $embeddedFields)) continue;

								// Compact display
								if ($compactDisplay && $record != '' && $row['element_type'] != 'descriptive') {
									if (is_array($dataNormalized[$row['field_name']])) {
										// Checkbox with no choices checked
										if (array_sum($dataNormalized[$row['field_name']]) == 0) {
											$num++; // Advance question number for auto-numbering (if needed)
											continue;
										}
									} else {
										// Normal field type with no value
										if ($dataNormalized[$row['field_name']] == '') {
											$num++; // Advance question number for auto-numbering (if needed)
											continue;
										}
									}
								}

								// print "<hr>$record, $event_id, {$row['field_name']}, {$row['form_name']}, $repeat_instrument, $repeat_instance, ";
								// var_dump($Proj->isRepeatingForm($event_id, $row['form_name']));

								// Check if starting new form or instance
								if ($last_form != $row['form_name']) // || $last_repeat_instrument != $repeat_instrument || $last_repeat_instance != $repeat_instance)
								{
									// For compact displays where form has never had any data entered, skip it in the pdf export
									if ($compactDisplay && $record != '' && $dataNormalized[$row['form_name']."_complete"] == '0')
									{
										// Loop through all values to see if they're blank
										$dataFieldThisForm = array_intersect_key($dataNormalized, $Proj->forms[$row['form_name']]['fields']);
										$formHasData = false;
										foreach ($dataFieldThisForm as $this_field2=>$this_val2) {
											if ($this_field2 == $row['form_name']."_complete") continue;
											if ((!is_array($this_val2) && $this_val2 != '') || (is_array($this_val2) && array_sum($this_val2) > 0)) {
												$formHasData = true;
												break;
											}
										}
										unset($dataFieldThisForm);
										// If all fields are blank, then skip this form
										if (!$formHasData) {
											continue;
										}
									}

									if ($last_form != "") {
										PDF::displayLockingEsig($pdf, $record, $event_id, $last_form, $repeat_instance, $est_char_per_line, $y_units_per_line, $bottom_of_page, $study_id_event);
									}
									//print "<br>New page! field: {$row['field_name']}, $last_form != {$row['form_name']} || $last_repeat_instrument != $repeat_instrument || $last_repeat_instance != $repeat_instance";
									// Set flags to denote beginning of new form/page
									$fieldsDisplayedInSection = $fieldsDisplayedInMatrix = 0;
									$prev_grid_name = "";
									// Set flag for first SH encountered
									$encounteredFirstSH = false;

									// Set form/survey values
									if (isset($Proj) && is_array($ProjForms) && isset($ProjForms[$row['form_name']]['survey_id'])) {
										// Survey
										$survey_id = $ProjForms[$row['form_name']]['survey_id'];
										$isSurvey = true;
										$newPageOnSH = $Proj->surveys[$survey_id]['question_by_section'];
										$customQuesNum = !$Proj->surveys[$survey_id]['question_auto_numbering'];
										$survey_instructions = str_replace(array("\r", "\n", "</p> <p>","</p>\r\n","</p>\n","</p>","</tr>"), array("", "", "</p><p>","</p>","</p>","</p>\n\n","</tr>\n"), label_decode(label_decode($Proj->surveys[$survey_id]['instructions'])));
										$survey_instructions = str_replace("\t", " ", label_decode(Piping::replaceVariablesInLabel($survey_instructions, $record, $event_id, $repeat_instance, $piping_record_data, true, null, false, "", 1, false, true, $row['form_name'])));
										$survey_instructions = strip_tags(str_replace(array("</tr>", "</td>"), array("</tr>\n", "</td>   "), br2nl($survey_instructions)));
										$survey_instructions = str_replace(array("\r\n","\r"), array("\n","\n"), $survey_instructions);
										$form_title = strip_tags(label_decode($Proj->surveys[$survey_id]['title']));
									} elseif (isset($Proj) && is_array($ProjForms)) {
										// Form
										$survey_instructions = "";
										$isSurvey = false;
										$newPageOnSH = false;
										$customQuesNum = false;
										$form_title = strip_tags(label_decode($ProjForms[$row['form_name']]['menu']));
									} else {
										// Shared Library defaults
										$form_title = $project_name;
										$customQuesNum = false;
										$isSurvey = false;
									}

									if ($project_encoding == 'japanese_sjis') {
										$form_title = mb_convert_encoding($form_title, "SJIS", "UTF-8"); //Japanese
										$survey_instructions = mb_convert_encoding($survey_instructions, "SJIS", "UTF-8"); //Japanese
									}

									// For surveys only, skip participant_id field
									if (isset($isSurvey) && $isSurvey && $row['field_name'] == $table_pk) {
										$atSurveyPkField = true;
										continue;
									}

									// Begin new page
									$pdf->AddPage();
									// Set REDCap logo at bottom right
									PDF::setFooterImage($pdf);
									// Set "Confidential" text at top
									$pdf = PDF::confidentialText($pdf);
									//Display project name (top right)
									$pdf->SetFillColor(0,0,0); # Set fill color (when used) to black
									$pdf->SetFont(FONT,'I',8); # retained from page to page. #  'I' for italic, 8 for size in points.
									if (!$isSurvey) {
										$pdf->Cell(0,2,$project_name,0,1,'R');
										$pdf->Ln();
									}
									// If appending text to header
									$pdf = PDF::appendToHeader($pdf);
									//Display record name (top right), if displaying data for a record
									if ($study_id_event != "" && !isset($_GET['s'])) {
										$pdf->SetFont(FONT,'BI',8);
										$pdf->Cell(0,2,$study_id_event,0,1,'R');
										$pdf->Ln();
										$pdf->SetFont(FONT,'I',8);
									}
									//Initial page number
									$pdf->Cell(0,2,$GLOBALS['lang']['survey_132'].' '.$pdf->PageNo(),0,1,'R');
									//Display form title as page header
									$pdf->SetFont(FONT,'B',18);
									$pdf->MultiCell(0,6,$form_title,0);
									$pdf->Ln();
									$pdf->SetFont(FONT,'',10);
									// Survey instructions, if a survey
									if (isset($isSurvey) && $isSurvey)
									{
										$pdf->MultiCell(0,$row_height,$survey_instructions,0);
										$pdf->Ln();
										// Set the survey response time
										$repeat_instance_norm = ($repeat_instance < 1) ? 1 : $repeat_instance;
										if (isset($response_time_text[$row['form_name']][$repeat_instance_norm])) {
											// Display timestamp for surveys
											$pdf->SetFont(FONT,'',10);
											$pdf->SetTextColor(255,255,255);
											$pdf->Ln();
											$pdf->MultiCell(0,6,$response_time_text[$row['form_name']][$repeat_instance_norm],1,'L',1);
											$pdf->SetTextColor(0,0,0);
											$pdf->SetFont(FONT,'',10);
										}
									}
									$pdf->Ln();
									// Set as default for next loop
									$atSurveyPkField = false;
								}

								// If the current value is blank, then remove it from $Data to correct interpretation issues (due to change of old code)
								if (isset($dataNormalized[$row['field_name']]) && $dataNormalized[$row['field_name']] == '') {
									unset($dataNormalized[$row['field_name']]);
								} elseif (isset($dataNormalized[$row['field_name']]) && !is_array($dataNormalized[$row['field_name']])) {
									// Replace tabs with 5 spaces for text because otherwise it will display them as square box characters
									$dataNormalized[$row['field_name']] = str_replace("\t", "     ", $dataNormalized[$row['field_name']]);
								}

								//Set default font
								$pdf->SetFont(FONT,'',10);
								$q_lines = array();
								$a_lines = array();

								## MATRIX QUESTION GROUPS
								$matrixGroupPosition = ''; //default
								$grid_name = $row['grid_name'];
								$matrixHeight = null;
								// Just ended a grid, so give a little extra space
								if ($grid_name == "" && $prev_grid_name != $grid_name)
								{
									$pdf->Ln();
								}
								// Beginning a new grid
								elseif ($grid_name != "" && $prev_grid_name != $grid_name)
								{
									// Set that field is the first field in matrix group
									$matrixGroupPosition = '1';
									// Get total matrix group height, including SH, so check if we need a page break invoked below
									$matrixHeight = $row_height * PDF::getMatrixHeight($pdf, $row['field_name'], $page_width, $matrix_label_width);
								}
								// Continuing an existing grid
								elseif ($grid_name != "" && $prev_grid_name == $grid_name)
								{
									// Set that field is *not* the first field in matrix group
									$matrixGroupPosition = 'X';
								}


								// If just ended a matrix in the previous loop, then check if all questions in previous matrix were hidden.
								// If so, hide matrix header.
								if ($record != '') {
									if ($prev_grid_name != "" && $prev_grid_name != $grid_name && $fieldsDisplayedInMatrix !== 0)
									{
										// Set to 0 if we have a matrix back-to-back and this is the start of the second matrix
										$fieldsDisplayedInMatrix = 0;
									}
									elseif ($prev_grid_name != "" && $prev_grid_name != $grid_name && $fieldsDisplayedInMatrix === 0)
									{
										$pdf = PDF::removeMatrixHeader($pdf);
									}
								}

								// REMOVE SH: Check if all questions in previous section were hidden. Skip if this is field 2 on form 1 (field right after table_pk).
								if (($row['element_preceding_header'] != "" || $instrument_about_to_change) && $record != '' && $fieldsDisplayedInSection === 0 && $encounteredFirstSH && $row['field_order'] != '2') {
									$pdf = PDF::removeSectionHeader($pdf);
								}

								$row['element_label'] = label_decode($row['element_label']);
								$row['element_preceding_header'] = label_decode($row['element_preceding_header']);
								// Pre-format any field labels created with the rich text editor
								if (strpos($row['element_label'], '"rich-text-field-label"') || strpos($row['element_label'], "'rich-text-field-label'")) {
									$row['element_label'] = str_replace(array("</p> <p>","</p>\r\n","</p>\n","</p>","</tr>"),
										array("</p><p>","</p>","</p>","</p>\n\n","</tr>\n"), $row['element_label']);
								}
								if (strpos($row['element_preceding_header'], '"rich-text-field-label"') || strpos($row['element_preceding_header'], "'rich-text-field-label'")) {
									$row['element_preceding_header'] = str_replace(array("</p> <p>","</p>\r\n","</p>\n","</p>","</tr>"),
										array("</p><p>","</p>","</p>","</p>\n\n","</tr>\n"), $row['element_preceding_header']);
								}
								// Remove HTML tags from field labels and field notes
								$row['element_label'] = trim(strip_tags(br2nl($row['element_label'])));
								$row['element_preceding_header'] = trim($row['element_preceding_header']);
								// For all fields (except Descriptive), remove line breaks
								if ($row['element_type'] != 'descriptive') {
									$row['element_label'] = str_replace(array("\r\n","\r"), array("\n","\n"), $row['element_label']);
								}
								if ($project_encoding == 'japanese_sjis') $row['element_label'] = mb_convert_encoding($row['element_label'], "SJIS", "UTF-8"); //Japanese
								if ($row['element_note'] != "") {
									$row['element_note'] = strip_tags(label_decode($row['element_note']));
									if ($project_encoding == 'japanese_sjis') $row['element_note'] = mb_convert_encoding($row['element_note'], "SJIS", "UTF-8"); //Japanese
								}

								// If a Matrix AND whole matrix will exceed length of page
								$matrixExceedPage = ($matrixGroupPosition == '1' && ($pdf->GetY()+$matrixHeight) > ($bottom_of_page-20) && $pdf->PageNo() > 1);
								// If Section Header AND (starting new page OR close to the bottom)
								$headerExceedPage = ($row['element_preceding_header'] != "" && ((isset($isSurvey) && $isSurvey && $newPageOnSH && $num != 1) || ($pdf->GetY() > $bottom_of_page-50)));

								// Check pagebreak for Section Header OR Matrix
								if ($matrixExceedPage || $headerExceedPage) {
									// Cache the current $pdf object in case we have to hide this header later
									if ($matrixExceedPage) $pdfLastMH = clone $pdf;
									// Cache the current $pdf object in case we have to hide this header later
									if ($headerExceedPage) $pdfLastSH = clone $pdf;
									// Begin new page
									$pdf->AddPage();
									PDF::setFooterImage($pdf);
									// Set "Confidential" text at top
									$pdf = PDF::confidentialText($pdf);
									// If appending text to header
									$pdf = PDF::appendToHeader($pdf);
									//Display record name (top right), if displaying data for a record
									if ($study_id_event != "" && !isset($_GET['s'])) {
										$pdf->SetFont(FONT,'BI',8);
										$pdf->Cell(0,2,$study_id_event,0,1,'R');
										$pdf->Ln();
									}
									$pdf->SetFont(FONT,'I',8);
									$pdf->Cell(0,5,$GLOBALS['lang']['survey_132'].' '.$pdf->PageNo(),0,1,'R');
								}

								// Section header
								if ($row['element_preceding_header'] != "")
								{
									// Cache the current $pdf object in case we have to hide this header later
									if (!$headerExceedPage) $pdfLastSH = clone $pdf;

									// Render section header
									$shHeightBefore = $pdf->GetY();
									$pdf->Ln();
									// $pdf->MultiCell(0,0,'','B'); $pdf->Ln();
									// $pdf->MultiCell(0,0,'','T'); $pdf->Ln();
									$pdf->SetFont(FONT,'B',11);
									$row['element_preceding_header'] = strip_tags(br2nl($row['element_preceding_header']));
									if ($project_encoding == 'japanese_sjis') $row['element_preceding_header'] = mb_convert_encoding($row['element_preceding_header'], "SJIS", "UTF-8"); //Japanese
									$pdf->SetFillColor(225,225,225);
									$pdf->MultiCell(0,6,$row['element_preceding_header'],'T','J',true);
									//$pdf->Ln();
									$pdf->SetFont(FONT,'',10);
									// Set flag to denote a new section
									//print " <b>".$row['element_preceding_header']."</b><br>";
									$fieldsDisplayedInSection = 0;
									// Set flag
									$encounteredFirstSH = true;
									// Calculate the total height of this SH
									$heightPrevSH = $pdf->GetY() - $shHeightBefore;
								}

								// APPLY BRANCHING LOGIC
								$displayField = true; //default
								if ($record != '')
								{
									// If field has data, then show it regardless (may have data but is trying to be hidden)
									if (!(isset($dataNormalized[$row['field_name']]) && !is_array($dataNormalized[$row['field_name']])
										&& $dataNormalized[$row['field_name']] != ''))
									{
										// Check logic, if applicable
										if (isset($branchingLogicValid[$row['field_name']])) {
											// If longitudinal, then inject the unique event names into logic (if missing)
											// in order to specific the current event.
											if ($longitudinal) {
												$row['branching_logic'] = LogicTester::logicPrependEventName($row['branching_logic'], $Proj->getUniqueEventNames($event_id), $Proj);
											}
											$row['branching_logic'] = Piping::pipeSpecialTags($row['branching_logic'], $Proj->project_id, $record, $event_id, $repeat_instance, null, true, null, $row['form_name'], true);
											if ($Proj->hasRepeatingFormsEvents()) {
												$row['branching_logic'] = LogicTester::logicAppendInstance($row['branching_logic'], $Proj, $event_id, $row['form_name'], $repeat_instance);
											}
											$displayField = LogicTester::apply($row['branching_logic'], $piping_record_data[$record]);
											// If field should be hidden, then skip it here
											if (!$displayField) {
												// Set last_form for next loop
												$last_form = $row['form_name'];
												// Set value for next loop
												$prev_grid_name = $grid_name;
												// If beginning a matrix of fields here, then go ahead and render this matrix header row (we will remove later if needed)
												if ($matrixGroupPosition == '1') {
													$mhHeightBefore = $pdf->GetY();
													if ($project_encoding == 'japanese_sjis') {
														$thisEnum = parseEnum(mb_convert_encoding($row['element_enum'], "SJIS", "UTF-8")); //Japanese
													} else {
														$thisEnum = parseEnum($row['element_enum']);
													}
													// Cache the current $pdf object in case we have to hide this header later
													if (!$matrixExceedPage) $pdfLastMH = clone $pdf;
													$pdf = PDF::renderMatrixHeaderRow($pdf, $thisEnum, $page_width, $matrix_label_width);
													// Calculate the total height of this matrix header
													$heightPrevMH = $pdf->GetY() - $mhHeightBefore;
												}
												// Stop and begin next loop
												continue;
											}
										}
									}
									// Set flag to denote that field was displayed
									$fieldsDisplayedInSection++;
								}

								// Beginning a new grid
								if ($matrixGroupPosition == '1') {
									$fieldsDisplayedInMatrix = 1;
								}
								// Continuing an existing grid
								elseif ($matrixGroupPosition == 'X') {
									$fieldsDisplayedInMatrix++;
								}
								// Just ended a grid, so give a little extra space
								else {
									$fieldsDisplayedInMatrix = 0;
								}

								// DON'T DO THIS YET. SHOULD WE EVER DO THIS? MIGHT GO AGAINST WHAT PEOPLE HAVE ALREADY DONE.
								// If any drop-down fields are RH alignment, set as RV to emulate webpage appearance better since they get rendered out as radios here.
								// if (($row['element_type'] == "select" || $row['element_type'] == "sql") && $row['custom_alignment'] == 'RH') {
								// $row['custom_alignment'] = 'RV';
								// }

								// If a multiple choice field had its data removed via De-Id rights, then don't display its choices
								if (isset($dataNormalized[$row['field_name']]) && $dataNormalized[$row['field_name']] === DEID_TEXT) {
									$row['element_type'] = "text";
								}

								//Drop-downs & Radio buttons
								if ($row['element_type'] == "yesno" || $row['element_type'] == "truefalse" || $row['element_type'] == "radio" || $row['element_type'] == "select" || $row['element_type'] == "advcheckbox" || $row['element_type'] == "checkbox" || $row['element_type'] == "sql")
								{
									//If AdvCheckbox, render as Yes/No radio buttons
									if ($row['element_type'] == "advcheckbox") {
										$row['element_enum'] = "1, ";
									}
									//If Yes/No, manually set options
									elseif ($row['element_type'] == "yesno") {
										$row['element_enum'] = YN_ENUM;
									}
									//If True/False, manually set options
									elseif ($row['element_type'] == "truefalse") {
										$row['element_enum'] = TF_ENUM;
									}

									if ($row['element_note'] != "") $row['element_note'] = "(".$row['element_note'].")";

									// If a Matrix formatted field
									if ($row['grid_name'] != '') {
										// Parse choices into an array
										$enum = parseEnum($row['element_enum']);
										// Render this matrix header row
										if ($matrixGroupPosition == '1') {
											$mhHeightBefore = $pdf->GetY();
											if ($project_encoding == 'japanese_sjis') {
												$enum = parseEnum(mb_convert_encoding($row['element_enum'], "SJIS", "UTF-8")); //Japanese
											} else {
												$enum = parseEnum($row['element_enum']);
											}
											// Cache the current $pdf object in case we have to hide this header later
											if (!$matrixExceedPage) $pdfLastMH = clone $pdf;
											$pdf = PDF::renderMatrixHeaderRow($pdf, $enum, $page_width, $matrix_label_width);
											// Calculate the total height of this matrix header
											$heightPrevMH = $pdf->GetY() - $mhHeightBefore;
										}
										// Determine if this row's checkbox needs to be checked (i.e. it has data)
										$enumData = array();
										if (isset($dataNormalized[$row['field_name']]))
										{
											// Field DOES have data, so loop through EVERY choice and put in array
											foreach (array_keys($enum) as $this_code) {
												if (is_array($dataNormalized[$row['field_name']])) {
													// Checkbox fields
													if (isset($dataNormalized[$row['field_name']][$this_code]) && $dataNormalized[$row['field_name']][$this_code] == "1") {
														$enumData[$this_code] = '1';
													}
												} elseif ($dataNormalized[$row['field_name']] == $this_code) {
													// Regular fields
													$enumData[$this_code] = '1';
												}
											}
										}
										$pdf = PDF::addQuestionNumber($pdf, $row_height, $row['question_num'], $isSurvey, $customQuesNum, $num);
										// Render the matrix row for this field
										$pdf = PDF::renderMatrixRow($pdf, $row['element_label'], $enum, $enumData, $row_height, $sigil_width, $page_width, $matrix_label_width, $bottom_of_page,$study_id_event,($row['element_type'] == "advcheckbox" || $row['element_type'] == "checkbox"));
									}
									// LV, LH, RH Alignment
									elseif ($row['custom_alignment'] == 'LV' || $row['custom_alignment'] == 'LH' || $row['custom_alignment'] == 'RH')
									{
										// Set begin position of new line
										$xStartPos = 10;
										if ($row['custom_alignment'] == 'RH') {
											$xStartPos = 115;
										}

										// Place enums in array while trying to judge general line count of all choices
										$row['element_enum'] = strip_tags(label_decode($row['element_enum']));
										if ($project_encoding == 'japanese_sjis') $row['element_enum'] = mb_convert_encoding($row['element_enum'], "SJIS", "UTF-8");
										$enum = array();
										foreach (parseEnum($row['element_enum']) as $this_code=>$line)
										{
											if ($compactDisplay && $record != '' && $row['element_type'] != 'descriptive') {
												if (is_array($dataNormalized[$row['field_name']])) {
													// Checkbox with no choices checked
													if ($dataNormalized[$row['field_name']][$this_code] == 0) {
														continue;
													}
												} else {
													// Normal field type with no value
													if ($dataNormalized[$row['field_name']] != $this_code) {
														continue;
													}
												}
											}
											// Add to array
											$enum[$this_code] = strip_tags(label_decode($line));
										}

										// Field label text
										if ($row['custom_alignment'] == 'RH') {
											// Right-horizontal aligned
											$q_lines = PDF::qtext_vertical($row, $char_limit_q);
											//print_array($q_lines);
											$counter = (count($q_lines) >= count($enum)) ? count($q_lines) : count($enum);
											$pdf = PDF::new_page_check($counter, $pdf, $y_units_per_line, $bottom_of_page, $study_id_event);
											$pdf->MultiCell(0,1,'','T'); $pdf->Ln();
											$yStartPos = $pdf->GetY();
											// If a survey and using custom question numbering, then render question number
											$pdf = PDF::addQuestionNumber($pdf, $row_height, $row['question_num'], $isSurvey, $customQuesNum, $num);
											for ($i = 0; $i < count($q_lines); $i++) {
												$pdf->Cell($col_width_a,$row_height,$q_lines[$i],0,1);
											}
											$yPosAfterLabel = $pdf->GetY();
											$pdf->SetY($yStartPos);
										} else {
											// Left aligned
											$counter = ceil($pdf->GetStringWidth($row['element_label']."\n".$row['element_note'])/$max_line_width)+2+count($enum);
											$pdf = PDF::new_page_check($counter, $pdf, $y_units_per_line, $bottom_of_page, $study_id_event);
											$pdf->MultiCell(0,1,'','T'); $pdf->Ln();
											// If a survey and using custom question numbering, then render question number
											$pdf = PDF::addQuestionNumber($pdf, $row_height, $row['question_num'], $isSurvey, $customQuesNum, $num);
											$pdf->MultiCell(0,$row_height,$row['element_label']."\n".$row['element_note']);
											$pdf->Ln();
										}

										// Set initial x-position on line
										$pdf->SetX($xStartPos);

										// Render choices
										foreach ($enum as $this_code=>$line)
										{
											$this_code = $this_code."";
											if ($compactDisplay && $record != '' && $row['element_type'] != 'descriptive') {
												if (is_array($dataNormalized[$row['field_name']])) {
													// Checkbox with no choices checked
													if ((string)$dataNormalized[$row['field_name']][$this_code] === '0') {
														continue;
													}
												} else {
													// Normal field type with no value
													if ((string)$dataNormalized[$row['field_name']] !== $this_code) {
														continue;
													}
												}
											}
											// Check if we need to start new line to prevent text run-off
											if ($pdf->GetX() > ($max_line_width - $sigil_width - 30)) {
												$pdf->Ln();
												$pdf->SetX($xStartPos);
											}
											// Draw checkboxes
											$pdf->Cell(1,$row_height,'');
											$pdf->Cell($sigil_width,$row_height,'',0,0,'L',false);
											$x = array($pdf->GetX()-$sigil_width+.5,0);
											$x[1] = $x[0] + $row_height-1;
											$y = array($pdf->GetY()+.5,0);
											$y[1] = $y[0] + $row_height-1;
											if ($row['element_type'] == "advcheckbox" || $row['element_type'] == "checkbox") {
												$pdf->Rect($x[0],$y[0],$row_height-1,$row_height-1);
												$crosslineoffset = 0;
											} else {
												$pdf = PDF::Circle($pdf, $x[0]+1.5, $y[0]+1.5, 1.6);
												$crosslineoffset = 0.5;
											}
											// Determine if checkbox needs to be checked (if has data)
											$hasData = false; // Default
											// Determine if this row's checkbox needs to be checked (i.e. it has data)
											if (isset($dataNormalized[$row['field_name']])) {
												if (is_array($dataNormalized[$row['field_name']])) {
													// Checkbox fields
													if (isset($dataNormalized[$row['field_name']][$this_code]) && (string)$dataNormalized[$row['field_name']][$this_code] === "1") {
														$hasData = true;
													}
												} elseif ((string)$dataNormalized[$row['field_name']] === $this_code) {
													// Regular fields
													$hasData = true;
												}
											}
											if ($hasData) {
												// X marks the spot
												$pdf->Line($x[0]+$crosslineoffset,$y[0]+$crosslineoffset,$x[1]-$crosslineoffset,$y[1]-$crosslineoffset);
												$pdf->Line($x[0]+$crosslineoffset,$y[1]-$crosslineoffset,$x[1]-$crosslineoffset,$y[0]+$crosslineoffset);
											}
											// Before printing label, first check if we need to start new line to prevent text run-off
											while (strlen($line) > 0)
											{
												//print "<br>Xpos: ".$pdf->GetX().", Line: $line";
												if (($pdf->GetX() + $pdf->GetStringWidth($line)) >= $max_line_width)
												{
													// If text will produce run-off, cut off and repeat in next loop to split up onto multiple lines
													$cutoff = $max_line_width - $pdf->GetX();
													// Since cutoff is in FPDF width, we need to find it's length in characters by going one character at a time
													$last_space_pos = 0; // Note the position of last space (for cutting off purposes)
													for ($i = 1; $i <= strlen($line); $i++) {
														// Check length of string segment
														$segment_width = $pdf->GetStringWidth(substr($line, 0, $i));
														// Check if current character is a space
														if (substr($line, $i, 1) == " ") $last_space_pos = $i;
														// If we found the cutoff, get the character count
														if ($segment_width >= $cutoff) {
															// Obtain length of segment and set segment value
															$segment_char_length = ($last_space_pos != 0) ? $last_space_pos : $i;
															$thisline = substr($line, 0, $segment_char_length);
															break;
														} else {
															$segment_char_length = strlen($line);
															$thisline = $line;
														}
													}
													// Print this segment of the line
													$thisline = trim($thisline);
													$pdf->Cell($pdf->GetStringWidth($thisline)+2,$row_height,$thisline);
													// Set text for next loop on next line
													$line = substr($line, $segment_char_length);
													// Now set new line with slight indentation (if another line is needed)
													if (strlen($line) > 0) {
														$pdf->Ln();
														$pdf->SetX($xStartPos+(($row['custom_alignment'] == 'LV') ? $sigil_width : 0));
														$pdf->Cell(1,$row_height,'');
													}
												} else {
													// Text fits easily on one line
													$line = trim($line);
													$pdf->Cell($pdf->GetStringWidth($line)+4,$row_height,$line);
													// Reset to prevent further looping
													$line = "";
												}
											}
											// Insert line break if left-vertical alignment
											if ($row['custom_alignment'] == 'LV') {
												$pdf->Ln();
											}
										}
										// For RH aligned with element note...
										if ($row['custom_alignment'] == 'RH' && $row['element_note']) {
											$a_lines_note = PDF::text_vertical($row['element_note'], $char_limit_a);
											foreach ($a_lines_note as $row2) {
												$pdf->Ln();
												$pdf->SetX($xStartPos);
												$pdf->Cell($col_width_a,$row_height,$row2);
											}
										}
										// For RH aligned, reset y-position if field label has more lines than choices
										if ($row['custom_alignment'] == 'RH' && $yPosAfterLabel > $pdf->GetY()) {
											$pdf->SetY($yPosAfterLabel);
										}
										// Insert line break if NOT left-vertical alignment (because was just added on last loop)
										else if ($row['custom_alignment'] != 'LV') {
											$pdf->Ln();
										}
									}
									// RV Alignment
									else
									{
										$q_lines = PDF::qtext_vertical($row, $char_limit_q);
										$a_lines = PDF::atext_vertical_mc($row, $dataNormalized, $char_limit_a, $indent_a, $project_language, $compactDisplay);
										if ($row['element_note'] != "") {
											$a_lines_note = PDF::text_vertical($row['element_note'], $char_limit_a);
											foreach ($a_lines_note as $row2) {
												$a_lines[] = $row2;
											}
										}
										$counter = (count($q_lines) >= count($a_lines)) ? count($q_lines) : count($a_lines);
										$pdf = PDF::new_page_check($counter, $pdf, $y_units_per_line, $bottom_of_page, $study_id_event);
										$pdf->MultiCell(0,1,'','T'); $pdf->Ln();
										// If a survey and using custom question numbering, then render question number
										$pdf = PDF::addQuestionNumber($pdf, $row_height, $row['question_num'], (isset($isSurvey) && $isSurvey), $customQuesNum, $num);
										for ($i = 0; $i < $counter; $i++) {
											$pdf->Cell($col_width_a,$row_height,(isset($q_lines[$i]) ? $q_lines[$i] : ""),0,0,'L',false);
											// Advances X without drawing anything
											if (isset($a_lines[$i]['sigil']) && is_array($a_lines[$i]) && $a_lines[$i]['sigil'] == "1") {
												$pdf->Cell($sigil_width,$row_height,'',0,0,'L',false);
												$x = array($pdf->GetX()-$sigil_width+.5,0); $x[1] = $x[0] + $row_height-1;
												$y = array($pdf->GetY()+.5,0); $y[1] = $y[0] + $row_height-1;
												if ($row['element_type'] == "advcheckbox" || $row['element_type'] == "checkbox") {
													$pdf->Rect($x[0],$y[0],$row_height-1,$row_height-1);
													$crosslineoffset = 0;
												} else {
													$pdf = PDF::Circle($pdf, $x[0]+1.5, $y[0]+1.5, 1.6);
													$crosslineoffset = 0.5;
												}
												if ($a_lines[$i]['chosen']){
													// X marks the spot
													$pdf->Line($x[0]+$crosslineoffset,$y[0]+$crosslineoffset,$x[1]-$crosslineoffset,$y[1]-$crosslineoffset);
													$pdf->Line($x[0]+$crosslineoffset,$y[1]-$crosslineoffset,$x[1]-$crosslineoffset,$y[0]+$crosslineoffset);
												}
												$pdf->Cell($atext_width,$row_height,$a_lines[$i]['line'],0,0,'L',false);
												$pdf->Ln();
											} else {
												if (isset($a_lines[$i]) && is_array($a_lines[$i])) {
													// If a choice (and not an element note), then indent for checkbox/radio box
													$pdf->Cell($sigil_width,$row_height,'',0,0,'C',false);
												}
												$pdf->Cell($atext_width,$row_height,((isset($a_lines[$i]) && is_array($a_lines[$i])) ? $a_lines[$i]['line'] : (isset($a_lines[$i]) ? $a_lines[$i] : "")),0,0,'L',false);
												$pdf->Ln();
											}
										}
									}
									//print "<br>{$row['field_name']} \$pdf->GetY() = {$pdf->GetY()}";
									$num++;

									// Descriptive
								} elseif ($row['element_type'] == "descriptive") {

									//Show notice of image/attachment
									$this_string = "";
									if (is_numeric($row['edoc_id']) || !defined("PROJECT_ID"))
									{
										$display_inline_image = false;
										if (!defined("PROJECT_ID")) {
											// Shared Library
											if ($row['edoc_display_img'] == '1') {
												$this_string .= "\n\n[Inline Image: {$row['edoc_id']}]";
											} elseif ($row['edoc_display_img'] == '0') {
												$this_string .= "\n\n[Attachment: {$row['edoc_id']}]";
											}
										} else {
											// REDCap project
											$sql = "select doc_name from redcap_edocs_metadata where project_id = " . PROJECT_ID . "
													and delete_date is null and doc_id = ".$row['edoc_id']." limit 1";
											$q = db_query($sql);
											$fname = (db_num_rows($q) < 1) ? "Not found" : "\"".label_decode(db_result($q, 0))."\"";
											if ($row['edoc_display_img']) {
												$display_inline_image = true;
											} else {
												$this_string .= "\n\n[Attachment: $fname]";
											}
										}
									}
									## DISPLAY INLINE IMAGE ATTACHMENT
									if ($display_inline_image) {
										// Copy file to temp directory
										$imgfile_path = Files::copyEdocToTemp($row['edoc_id'], true);
										if ($imgfile_path !== false) {
											// Get image size
											$imgfile_size = getimagesize($imgfile_path);
											$img_width = ceil($imgfile_size[0]/4);
											$img_height = $img_height_orig = ceil($imgfile_size[1]/4);
											// If image is too big, then scale it down
											$this_page_width = $page_width - 4;
											$resized_width = false;
											if ($img_width > $this_page_width) {
												$scale_ratio = ($img_width / $this_page_width);
												$img_width = $this_page_width;
												$img_height = ceil($img_height / $scale_ratio);
												$resized_width = true;
											}
											// New page check (due to label length + image)
											$counter = ceil($pdf->GetStringWidth($row['element_label'])/$max_line_width);
											$label_img_at_top = (($img_height + ($y_units_per_line * $counter) + $pdf->GetY()) > $bottom_of_page);
											$pdf = PDF::new_page_check(($img_height/$y_units_per_line)+$counter, $pdf, $y_units_per_line, $bottom_of_page, $study_id_event);
											$pdf->MultiCell(0,1,'','T'); $pdf->Ln();
											// If a survey and using custom question numbering, then render question number
											$pdf = PDF::addQuestionNumber($pdf, $row_height, $row['question_num'], (isset($isSurvey) && $isSurvey), $customQuesNum, "");
											// Label
											$pdf->MultiCell(0,$row_height,$row['element_label'].$this_string,0);
											// Check if we need to start a new page with this image (but not if we just started a new page due to long label length)
											$resized_height = false;
											if ($label_img_at_top) {
												// Label and image are at top of page
												// Make sure image+label height is not taller than entire page. If so, make its height the height of the page minus the label height
												if (($img_height + $pdf->GetY()) > $bottom_of_page) {
													$scale_ratio = $img_height / ($bottom_of_page - 20 - $pdf->GetY());
													$img_height = ceil($img_height / $scale_ratio);
													$img_width = ceil($img_width / $scale_ratio);
													$resized_height = true;
												}
											}
											//print "<hr>\$page_width: $page_width<br>\$bottom_of_page: $bottom_of_page<br>w: $img_width, h: $img_height";
											// Get current position, so we can reset it back later
											$y = $pdf->GetY();
											$x = $pdf->GetX();
											// Set the image
											$pdf->Image($imgfile_path, $x+1, $y+2, $img_width);
											// Now we can delete the image from temp
											unlink($imgfile_path);
											// Reset Y position to right below the image
											$pdf->SetY($y+3+$img_height);
										} else {
											$display_inline_image = false;
										}
									}
									if (!$display_inline_image) {
										## No inline image
										// New page check
										$counter = ceil($pdf->GetStringWidth($row['element_label'])/$max_line_width);
										$pdf = PDF::new_page_check($counter, $pdf, $y_units_per_line, $bottom_of_page, $study_id_event);
										$pdf->MultiCell(0,1,'','T'); $pdf->Ln();
										// If a survey and using custom question numbering, then render question number
										$pdf = PDF::addQuestionNumber($pdf, $row_height, $row['question_num'], (isset($isSurvey) && $isSurvey), $customQuesNum, "");
										// Label
										$pdf->MultiCell(0,$row_height,$row['element_label'].$this_string,0);
									}
									// Slider
								} elseif ($row['element_type'] == "slider") {

									// Parse the slider labels
									$slider_labels = Form::parseSliderLabels($row['element_enum']);
									$slider_min = PDF::slider_label($slider_labels['left'], $char_limit_slider);
									$slider_mid = PDF::slider_label($slider_labels['middle'], $char_limit_slider);
									$slider_max = PDF::slider_label($slider_labels['right'], $char_limit_slider);

									if ($row['custom_alignment'] == 'LV' || $row['custom_alignment'] == 'LH') {
										//Display left-aligned
										$this_string = $row['element_label'] . "\n\n";
										$slider_rows = array(count($slider_min), count($slider_mid), count($slider_max));
										$counter = max($slider_rows);
										$pdf = PDF::new_page_check($counter, $pdf, $y_units_per_line, $bottom_of_page, $study_id_event);
										$pdf->MultiCell(0,1,'','T'); $pdf->Ln();
										// If a survey and using custom question numbering, then render question number
										$pdf = PDF::addQuestionNumber($pdf, $row_height, $row['question_num'], $isSurvey, $customQuesNum, $num);
										while (count($slider_min) < $counter) array_unshift($slider_min,"");
										while (count($slider_mid) < $counter) array_unshift($slider_mid,"");
										while (count($slider_max) < $counter) array_unshift($slider_max,"");
										$pdf->MultiCell(0,$row_height,$this_string);
										$pdf->SetFont(FONT,'',8);
										for ($i = 0; $i < $counter; $i++) {
											$pdf->Cell(6,$row_height,"",0,0);
											$pdf->Cell((($num_rect*$rect_width)/3)+2,$row_height,$slider_min[$i],0,0,'L');
											$pdf->Cell((($num_rect*$rect_width)/3)+2,$row_height,$slider_mid[$i],0,0,'C');
											$pdf->Cell((($num_rect*$rect_width)/3)+2,$row_height,$slider_max[$i],0,1,'R');
										}
										$x_pos = 20;
										$pdf->MultiCell(0,2,"",0);
										for ($i = 1; $i <= $num_rect; $i++) {
											$emptyRect = true;
											if (isset($dataNormalized[$row['field_name']]))
											{
												// If slider has value 0, fudge it to 1 so that it appears (otherwise looks empty)
												$sliderDisplayVal = ($dataNormalized[$row['field_name']] < 1) ? 1 : $dataNormalized[$row['field_name']];
												// Set empty rectangle
												if (is_numeric($sliderDisplayVal) && round($sliderDisplayVal*$num_rect/100) == $i) {
													$emptyRect = false;
												}
											}
											if ($emptyRect) {
												$pdf->Rect($x_pos,$pdf->GetY(),$rect_width,1);
											} else {
												$pdf->Rect($x_pos,$pdf->GetY(),$rect_width,3,'F');
											}
											$x_pos = $x_pos + $rect_width;
										}
										if ($row['element_validation_type'] == "number" && isset($dataNormalized[$row['field_name']])) {
											$pdf->SetX($x_pos+2);
											$pdf->Cell(6,$row_height,$dataNormalized[$row['field_name']],1,0);
										}
										$pdf->MultiCell(0,$row_height,"",0);
										$pdf->SetFont(FONT,'I',7);
										if (!isset($dataNormalized[$row['field_name']])) {
											$pdf->MultiCell(0,3,"                                                             (Place a mark on the scale above)",0);
										}
									} else {
										//Display right-aligned
										$q_lines = PDF::qtext_vertical($row, $char_limit_q);
										$slider_rows = array(count($q_lines), count($slider_min), count($slider_mid), count($slider_max));
										$counter = max($slider_rows);
										$pdf = PDF::new_page_check($counter, $pdf, $y_units_per_line, $bottom_of_page, $study_id_event);
										$pdf->MultiCell(0,1,'','T'); $pdf->Ln();
										// If a survey and using custom question numbering, then render question number
										$pdf = PDF::addQuestionNumber($pdf, $row_height, $row['question_num'], $isSurvey, $customQuesNum, $num);
										while (count($slider_min) < $counter) array_unshift($slider_min,"");
										while (count($slider_mid) < $counter) array_unshift($slider_mid,"");
										while (count($slider_max) < $counter) array_unshift($slider_max,"");
										$x_pos = 120;
										for ($i = 0; $i < $counter; $i++) {
											$pdf->SetFont(FONT,'',10);
											$pdf->Cell($col_width_a,$row_height,$q_lines[$i],0,0);
											$pdf->SetFont(FONT,'',8);
											$pdf->Cell(1,$row_height,"",0,0);
											$pdf->Cell((($num_rect*$rect_width)/3)+2,$row_height,$slider_min[$i],0,0,'L');
											$pdf->Cell((($num_rect*$rect_width)/3)+2,$row_height,$slider_mid[$i],0,0,'C');
											$pdf->Cell((($num_rect*$rect_width)/3)+2,$row_height,$slider_max[$i],0,1,'R');
										}
										$pdf->MultiCell(0,2,"",0);
										for ($i = 1; $i <= $num_rect; $i++) {
											$emptyRect = true;
											if (isset($dataNormalized[$row['field_name']])) {
												// If slider has value 0, fudge it to 1 so that it appears (otherwise looks empty)
												$sliderDisplayVal = ($dataNormalized[$row['field_name']] < 1) ? 1 : $dataNormalized[$row['field_name']];
												// Set empty rectangle
												if (is_numeric($sliderDisplayVal) && round($sliderDisplayVal*$num_rect/100) == $i) {
													$emptyRect = false;
												}
											}
											if ($emptyRect) {
												$pdf->Rect($x_pos,$pdf->GetY(),$rect_width,1);
											} else {
												$pdf->Rect($x_pos,$pdf->GetY(),$rect_width,3,'F');
											}
											$x_pos = $x_pos + $rect_width;
										}
										if ($row['element_validation_type'] == "number" && isset($dataNormalized[$row['field_name']])) {
											$pdf->SetX($x_pos+2);
											$pdf->Cell(6,$row_height,$dataNormalized[$row['field_name']],1,0);
										}
										$pdf->MultiCell(0,$row_height,"",0);
										$pdf->SetFont(FONT,'I',7);
										if (!isset($dataNormalized[$row['field_name']])) {
											$pdf->MultiCell(0,3,"(Place a mark on the scale above)           ",0,'R');
										}
									}
									$num++;

									// Text, Notes, Calcs, and File Upload fields
								} elseif ($row['element_type'] == "textarea" || $row['element_type'] == "text"
									|| $row['element_type'] == "calc" || $row['element_type'] == "file") {

									// If field note exists, format it first
									if ($row['element_note'] != "") {
										$row['element_note'] = "\n(".$row['element_note'].")";
									}

									// If a File Upload field *with* data, just display [document]. If no data, display nothing.
									if ($row['element_type'] == "file" && $row['element_validation_type'] != "signature") {
										$dataNormalized[$row['field_name']] = (isset($dataNormalized[$row['field_name']]))
											? "[".$lang['data_export_tool_248']." ".truncateTextMiddle(Files::getEdocName($dataNormalized[$row['field_name']]), 40, 10)."]" : '';
									}

									if ($row['custom_alignment'] == 'LV' || $row['custom_alignment'] == 'LH')
									{
										if ($row['element_type'] == "textarea") {
											$row['element_label'] .= $row['element_note'] . "\n \n";
										} else {
											$row['element_label'] .= "\n \n";
										}

										// Left-aligned
										if (isset($dataNormalized[$row['field_name']])) {
											// Unescape text for Text and Notes fields (somehow not getting unescaped for left alignment)
											$dataNormalized[$row['field_name']] = label_decode($dataNormalized[$row['field_name']]);
											//Has data
											if ($project_encoding == 'japanese_sjis') {
												$row['element_label'] .= mb_convert_encoding($dataNormalized[$row['field_name']], "SJIS", "UTF-8"); // Japanese
											} elseif (!($row['element_type'] == "file" && $row['element_validation_type'] == "signature")) {
												$row['element_label'] .= $dataNormalized[$row['field_name']];
											}
											if ($row['element_type'] != "textarea" && !($row['element_type'] == "file" && $row['element_validation_type'] == "signature")) {
												$row['element_label'] .= $row['element_note'];
											}
										} else {
											if ($row['element_type'] == "textarea") {
												$row['element_label'] .= "\n \n \n";
											} else {
												if ($row['element_type'] == "file" && $row['element_validation_type'] == "signature") {
													$row['element_label'] .= "\n\n__________________________________________" . $row['element_note'];
												} else {
													$row['element_label'] .= "\n__________________________________" . $row['element_note'];
												}
											}
										}
										// New page check
										$counter = ceil($pdf->GetStringWidth($row['element_label'])/$max_line_width);
										$pdf = PDF::new_page_check($counter, $pdf, $y_units_per_line, $bottom_of_page, $study_id_event);
										$pdf->MultiCell(0,1,'','T'); $pdf->Ln();
										// If a survey and using custom question numbering, then render question number
										$pdf = PDF::addQuestionNumber($pdf, $row_height, $row['question_num'], $isSurvey, $customQuesNum, $num);
										$pdf->MultiCell(0,$row_height,$row['element_label'],0);
										## DISPLAY SIGNATURE IMAGE
										if (isset($dataNormalized[$row['field_name']]) && $row['element_type'] == "file" && $row['element_validation_type'] == "signature") {
											// Cannot display PNG signature w/o Zlib extension enabled
											if (!USE_UTF8) {
												// Need about 2 lines worth of height for this label
												$pdf = PDF::new_page_check(2, $pdf, $y_units_per_line, $bottom_of_page, $study_id_event);
												$pdf->Ln();
												$pdf->MultiCell(0,$row_height,$lang['data_entry_248'],0);
											} else {
												// Need about 6 lines worth of height for this image
												$pdf = PDF::new_page_check(7, $pdf, $y_units_per_line, $bottom_of_page, $study_id_event);
												// Get current position, so we can reset it back later
												$y = $pdf->GetY();
												$x = $pdf->GetX();
												// Copy file to temp directory
												$sigfile_path = Files::copyEdocToTemp($dataNormalized[$row['field_name']], true);
												if ($sigfile_path !== false) {
													// Get image size
													$sigfile_size = getimagesize($sigfile_path);
													// Set the image
													$pdf->Image($sigfile_path, $x, $y, round($sigfile_size[0]/6));
													// Now we can delete the image from temp
													unlink($sigfile_path);
													// Set new position
													$pdf->SetY($y+round($sigfile_size[1]/6));
												}
											}
											// Add field note, if applicable
											if ($row['element_note'] != '') {
												if (substr($row['element_note'], 0, 1) == "\n") $row['element_note'] = substr($row['element_note'], 1);
												$pdf->MultiCell(0,$row_height,$row['element_note'],0);
											}
										}
									}
									else
									{
										// Right-aligned
										if ($row['element_type'] == "textarea") {
											//$row['element_label'] .= $row['element_note'];
											$this_string = $row['element_label'];
											$q_lines = PDF::text_vertical($this_string,$char_limit_q);
										} else {
											$q_lines = PDF::qtext_vertical($row, $char_limit_q);
										}
										if (isset($dataNormalized[$row['field_name']])) {
											//Has data
											$this_textv = $dataNormalized[$row['field_name']];
											if ($project_encoding == 'japanese_sjis') {
												$this_textv = mb_convert_encoding($this_textv, "SJIS", "UTF-8") . $row['element_note']; // Japanese - field note is already encoded
											} else {
												$this_textv .= $row['element_note'];
											}
											$a_lines = PDF::text_vertical($this_textv, $char_limit_a);
										} else {
											if ($row['element_type'] == "textarea") {
												$a_lines = PDF::text_vertical("\n \n__________________________________________" . $row['element_note'], $char_limit_a);
											} else {
												if ($row['element_type'] == "file" && $row['element_validation_type'] == "signature") {
													$a_lines = PDF::text_vertical("\n\n\n__________________________________________" . $row['element_note'], $char_limit_a);
												} else {
													$a_lines = PDF::text_vertical("\n__________________________________" . $row['element_note'], $char_limit_a);
												}
											}
										}

										## DISPLAY SIGNATURE IMAGE
										if (isset($dataNormalized[$row['field_name']]) && $row['element_type'] == "file" && $row['element_validation_type'] == "signature") {
											// Cannot display PNG signature w/o Zlib extension enabled
											$sigMinLines = 9;
											if (count($q_lines) < $sigMinLines) {
												$linesAdd = $sigMinLines - count($q_lines);
												for ($i=0; $i<$linesAdd; $i++) {
													$q_lines[] = "";
												}
											}
											if (!USE_UTF8) {
												$counter = count($q_lines);
												$pdf = PDF::new_page_check($counter, $pdf, $y_units_per_line, $bottom_of_page, $study_id_event);
												// If a survey and using custom question numbering, then render question number
												$pdf = PDF::addQuestionNumber($pdf, $row_height, $row['question_num'], (isset($isSurvey) && $isSurvey), $customQuesNum, $num);
												// Display text first
												for ($i = 0; $i < $counter; $i++) {
													$pdf->Cell($col_width_a,$row_height,$q_lines[$i],0,0);
													$pdf->Cell($col_width_b,$row_height,($i == 0 ? $lang['data_entry_248'] : ""),0,1);
												}
											} else {
												// Check for new page
												//$counter = max(count($q_lines), 7);
												$counter = count($q_lines);
												$pdf = PDF::new_page_check($counter, $pdf, $y_units_per_line, $bottom_of_page, $study_id_event);
												// If a survey and using custom question numbering, then render question number
												$pdf = PDF::addQuestionNumber($pdf, $row_height, $row['question_num'], (isset($isSurvey) && $isSurvey), $customQuesNum, $num);
												// Get current position, so we can reset it back later
												$y = $pdf->GetY();
												$x = $pdf->GetX();
												// Display text first
												for ($i = 0; $i < $counter; $i++) {
													$pdf->Cell($col_width_a,$row_height,$q_lines[$i],0,0);
													$pdf->Cell($col_width_b,$row_height,"",0,1);
												}
												$y2 = $pdf->GetY();
												// Copy file to temp directory
												$sigfile_path = Files::copyEdocToTemp($dataNormalized[$row['field_name']], true);
												if ($sigfile_path !== false) {
													// Get image size
													$sigfile_size = getimagesize($sigfile_path);
													// Set the image
													$pdf->Image($sigfile_path, $col_width_a+5, $y, round($sigfile_size[0]/6));
													// Now we can delete the image from temp
													unlink($sigfile_path);
													// Set new position
													if ($y+round($sigfile_size[1]/6) > $y2) $y2 = round($sigfile_size[1]/6);
													$pdf->SetY($y2);
												}
											}
											// Add field note, if applicable
											if ($row['element_note'] != '') {
												if (substr($row['element_note'], 0, 1) == "\n") $row['element_note'] = substr($row['element_note'], 1);
												$a_lines = PDF::text_vertical($row['element_note'], $char_limit_a);
												for ($i = 0; $i < count($a_lines); $i++) {
													$pdf->Cell($col_width_a,$row_height,'',0,0);
													$pdf->Cell($col_width_b,$row_height,$a_lines[$i],0,1);
												}
											}
										} else {
											$counter = (count($q_lines) >= count($a_lines)) ? count($q_lines) : count($a_lines);
											$pdf = PDF::new_page_check($counter, $pdf, $y_units_per_line, $bottom_of_page, $study_id_event);
											$pdf->MultiCell(0,1,'','T'); $pdf->Ln();
											// If a survey and using custom question numbering, then render question number
											$pdf = PDF::addQuestionNumber($pdf, $row_height, $row['question_num'], (isset($isSurvey) && $isSurvey), $customQuesNum, $num);
											for ($i = 0; $i < $counter; $i++) {
												if (!isset($q_lines[$i])) $q_lines[$i] = "";
												if (!isset($a_lines[$i])) $a_lines[$i] = "";
												$pdf->Cell($col_width_a,$row_height,$q_lines[$i],0,0);
												$pdf->Cell($col_width_b,$row_height,$a_lines[$i],0,1);
											}
										}
									}

									// Loop num
									$num++;

								}
								$pdf->Ln();
								// Save this form_name for the next loop
								$last_form = $row['form_name'];
								// Set value for next loop
								$prev_grid_name = $grid_name;
							}

							PDF::displayLockingEsig($pdf, $record, $event_id, $last_form, $repeat_instance, $est_char_per_line, $y_units_per_line, $bottom_of_page, $study_id_event);


							if ($record != '')
							{
								// Check if all questions in previous matrix were hidden. If so, hide matrix header.
								if ($prev_grid_name != "" && $fieldsDisplayedInMatrix === 0)
								{
									$pdf = PDF::removeMatrixHeader($pdf);
								}
								// Check if all questions in previous section were hidden
								if ($fieldsDisplayedInSection === 0) {
									$pdf = PDF::removeSectionHeader($pdf);
								}
							}


							// If form has an Acknowledgement, render it here
							if ($acknowledgement != "") {
								// Calculate how many lines will be needed for text to check if new page is needed
								$num_lines = ceil(strlen(strip_tags($acknowledgement))/$est_char_per_line)+substr_count($acknowledgement, "\n");
								$pdf = PDF::new_page_check($num_lines, $pdf, $y_units_per_line, $bottom_of_page, $study_id_event);
								$pdf->MultiCell(0,20,'');
								$pdf->MultiCell(0,1,'','B');
								$pdf->WriteHTML(nl2br($acknowledgement));
							}
							$last_repeat_instance = $repeat_instance;
						}
						$last_repeat_instrument = $repeat_instrument;
					}
				}
			}
		}

		// Remove special characters from title for using as filename
		$filename = "";
		if (isset($_GET['page'])) {
			$filename .= str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9]/", " ", $form_title))) . "_";
		}
		$filename .= str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9]/", " ", $project_name)));
		// Make sure filename is not too long
		if (strlen($filename) > 30) {
			$filename = substr($filename, 0, 30);
		}
		// Add timestamp if data in PDF
		if (isset($_GET['id']) || isset($_GET['allrecords'])) {
			$filename .= date("_Y-m-d_Hi");
		}

		// Don't output PDF if some text has been printed to page already
		if (strlen(ob_get_contents()) > 0) {
			exit("<br><br>ERROR: PDF cannot be output because some content has already been output to the buffer.");
		}

		// Output to file only for PDF download page (for Plugins and Hooks, output as string)
		if (isset($_GET['display']) && $_GET['display'] == 'inline') {
			print $pdf->Output("$filename.pdf", 'I');
		} elseif (PAGE != "PdfController:index" && !isset($_GET['s'])) {
			print $pdf->Output("$filename.pdf", 'S');
		} else {
			$pdf->Output("$filename.pdf", 'D');
		}
	}

	//Computes the number of lines a MultiCell of width w will take
	public static function NbLines($pdf, $w, $txt)
	{
		global $project_encoding;
		// Manually break up the lines into fragments of <=$w width to determine the true line count
		$txt = strip_tags(br2nl(label_decode($txt)));
		$num_lines = 1;
		$this_text = $txt;
		$num_loop = 1;
		if ($this_text == "") return 1;

		// Deal with 2-byte characters in strings
		if ($project_encoding != '') {
			//if ($project_encoding == 'japanese_sjis') $this_text = mb_convert_encoding($this_text, "SJIS", "UTF-8");
			return ceil($pdf->GetStringWidth($this_text)/$w);
		}

		// Latin character strings
		while ($this_text != "") {
			// Get last space before cutoff
			$this_text_width = $pdf->GetStringWidth($this_text);
			if ($this_text_width <= $w) {
				// Only one line
				$this_text = "";
			} else {
				// Multiple lines: Split it by max length < $w by spaces
				$this_line_num_chars = floor(($w / $this_text_width) * strlen($this_text));
				$this_line_last_space_pos = strrpos(substr($this_text, 0, $this_line_num_chars-1), " ");
				// If $this_line_last_space_pos is FALSE, then just get first space and cut it off there
				if ($this_line_last_space_pos === false) {
					list ($nothing, $this_text) = explode(" ", $this_text, 2);
				} else {
					$this_text = substr($this_text, $this_line_last_space_pos);
				}
				$this_text = trim($this_text);
				$num_lines++;
			}
			// Increment loop
			$num_loop++;
			// If we're stuck in an infinite loop, then get out using legacy method to determine line count
			if ($num_loop > 50) {
				return ceil($pdf->GetStringWidth($txt)/$w);
			}
		}
		// Return line count
		return $num_lines;
	}

	// Draw circle
	public static function Circle($pdf, $x, $y, $r, $style='')
	{
		return PDF::Ellipse($pdf, $x, $y, $r, $r, $style);
	}

	public static function Ellipse($pdf, $x, $y, $rx, $ry, $style='D')
	{
		if($style=='F')
			$op='f';
		elseif($style=='FD' or $style=='DF')
			$op='B';
		else
			$op='S';
		$lx=4/3*(M_SQRT2-1)*$rx;
		$ly=4/3*(M_SQRT2-1)*$ry;
		$k=$pdf->k;
		$h=$pdf->h;
		$pdf->_out(sprintf('%.2f %.2f m %.2f %.2f %.2f %.2f %.2f %.2f c',
			($x+$rx)*$k, ($h-$y)*$k,
			($x+$rx)*$k, ($h-($y-$ly))*$k,
			($x+$lx)*$k, ($h-($y-$ry))*$k,
			$x*$k, ($h-($y-$ry))*$k));
		$pdf->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c',
			($x-$lx)*$k, ($h-($y-$ry))*$k,
			($x-$rx)*$k, ($h-($y-$ly))*$k,
			($x-$rx)*$k, ($h-$y)*$k));
		$pdf->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c',
			($x-$rx)*$k, ($h-($y+$ly))*$k,
			($x-$lx)*$k, ($h-($y+$ry))*$k,
			$x*$k, ($h-($y+$ry))*$k));
		$pdf->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c %s',
			($x+$lx)*$k, ($h-($y+$ry))*$k,
			($x+$rx)*$k, ($h-($y+$ly))*$k,
			($x+$rx)*$k, ($h-$y)*$k,
			$op));
		return $pdf;
	}

	// Generate a matrix header row of multicells
	public static function renderMatrixHeaderRow($pdf,$hdrs,$page_width,$matrix_label_width)
	{
		// Construct row-specific parameters
		$mtx_hdr_width = round(($page_width - $matrix_label_width)/count($hdrs));
		$widths = array($matrix_label_width); // Default for field label
		$data = array("");
		foreach ($hdrs as $hdr) {
			$widths[] = $mtx_hdr_width;
			$data[] = $hdr;
		}
		//Calculate the height of the row
		$nb=0;
		for($i=0;$i<count($data);$i++)
			$nb=max($nb, PDF::NbLines($pdf, $widths[$i], $data[$i]));
		$h=5*$nb;
		//If the height h would cause an overflow, add a new page immediately
		if($pdf->GetY()+$h>$pdf->PageBreakTrigger) {
			$pdf->AddPage($pdf->CurOrientation);
		}
		$pdf->SetFont(FONT,'',9);
		//Draw the cells of the row
		for($i=0;$i<count($data);$i++)
		{
			$w=$widths[$i];
			//$a=isset($pdf->aligns[$i]) ? $pdf->aligns[$i] : 'L';
			$a=$i==0 ? 'L' : 'C';
			//Save the current position
			$x=$pdf->GetX();
			$y=$pdf->GetY();
			//Draw the border
			//$pdf->Rect($x, $y, $w, $h);
			//Print the text
			$pdf->MultiCell($w, 4, strip_tags(label_decode($data[$i])), 'T', $a);
			//Put the position to the right of the cell
			$pdf->SetXY($x+$w, $y);
		}
		// Set Y
		$pdf->SetY($pdf->GetY()+$h);
		//Go to the next line
		//$pdf->Ln();
		// Reset font back to earlier value
		$pdf->SetFont(FONT,'',10);
		return $pdf;
	}

	// Generate a matrix field row of multicells
	public static function renderMatrixRow($pdf,$label,$hdrs,$enumData,$row_height,$sigil_width,$page_width,$matrix_label_width,$bottom_of_page,$study_id_event,$isCheckbox)
	{
		$chkbx_width = $row_height-1;
		// Construct row-specific parameters
		$mtx_hdr_width = round(($page_width - $matrix_label_width)/count($hdrs));
		$widths = array($matrix_label_width); // Default for field label
		$data = array('Label-Key'=>$label);
		foreach ($hdrs as $key=>$hdr) {
			$widths[] = $mtx_hdr_width;
			$data[$key] = (isset($enumData[$key])); // checked value for each checkbox/radio button
		}
		//print_array($data);print "<br>";
		//Calculate the height of the row
		$nb = PDF::NbLines($pdf, $matrix_label_width, $label);
		//Issue a page break first if needed
		//$pdf->CheckPageBreak($h);
		//If the height h would cause an overflow, add a new page immediately
		// if($pdf->GetY()+$h>$pdf->PageBreakTrigger) {
		// $pdf->AddPage($pdf->CurOrientation);
		// }
		if ($pdf->GetY()+($nb*$row_height) > ($bottom_of_page-20)) {
			$pdf->AddPage();
			// Set logo at bottom
			PDF::setFooterImage($pdf);
			// Set "Confidential" text at top
			$pdf = PDF::confidentialText($pdf);
			// If appending text to header
			$pdf = PDF::appendToHeader($pdf);
			// Add page number
			if ($study_id_event != "" && !isset($_GET['s'])) {
				$pdf->SetFont(FONT,'BI',8);
				$pdf->Cell(0,2,$study_id_event,0,1,'R');
				$pdf->Ln();
			}
			$pdf->SetFont(FONT,'I',8);
			$pdf->Cell(0,5,$GLOBALS['lang']['survey_132'].' '.$pdf->PageNo(),0,1,'R');
			// Line break and reset font
			$pdf->Ln();
			$pdf->SetFont(FONT,'',10);
		}
		//Draw the cells of the row
		$i = 0;
		foreach ($data as $key=>$isChecked)
		{
			$w=$widths[$i];
			//Save the current position
			$x=$pdf->GetX();
			$y=$pdf->GetY();
			if($i!=0) {
				// Draw checkbox/radio
				$xboxpos = $x-1+floor($mtx_hdr_width/2);
				if ($isCheckbox) {
					$pdf->Rect($xboxpos, $y, $chkbx_width, $chkbx_width);
					$crosslineoffset = 0;
				} else {
					$pdf = PDF::Circle($pdf, $xboxpos+1.5, $y+1.5, 1.6);
					$crosslineoffset = 0.5;
				}
				// Positions of line 1
				$line1_x0 = $xboxpos;
				$line1_y0 = $y;
				$line1_x1 = $line1_x0+$chkbx_width;
				$line1_y1 = $line1_y0+$chkbx_width;
				// Positions of line 2
				$line2_x0 = $xboxpos;
				$line2_y0 = $y+$chkbx_width;
				$line2_x1 = $line2_x0+$chkbx_width;
				$line2_y1 = $y;
				// If checked, then X marks the spot
				if ($isChecked) {
					$pdf->Line($line1_x0+$crosslineoffset,$line1_y0+$crosslineoffset,$line1_x1-$crosslineoffset,$line1_y1-$crosslineoffset);
					$pdf->Line($line2_x0+$crosslineoffset,$line2_y0-$crosslineoffset,$line2_x1-$crosslineoffset,$line2_y1+$crosslineoffset);
				}
			} else {
				//Print the label
				$pdf->MultiCell($w, $row_height, strip_tags(label_decode($label)), 0, 'L');
				$yLabel = $y+(($nb-1)*$row_height*1.3);
			}
			//Put the position to the right of the cell
			$pdf->SetXY($x+$w, $y);
			// Increment counter
			$i++;
		}
		//Go to the next line
		if ($nb > 1) {
			// Set Y
			$pdf->SetY($yLabel);
		}
		$pdf->Ln(2);
		return $pdf;
	}

	// Get total matrix group height, including SH, so check if we need a page break invoked below
	public static function getMatrixHeight($pdf, $field, $page_width, $matrix_label_width)
	{
		global $Proj, $ProjMetadata, $ProjMatrixGroupNames;
		if (!is_array($ProjMatrixGroupNames)) $ProjMatrixGroupNames = array();
		// Set initial line count
		$lines = 0;
		// Get count of total lines for SH (adding 2 extra lines for spacing and double lines)
		$SH = $ProjMetadata[$field]['element_preceding_header'];
		$lines += ($SH == '' ? 0 : 2) + PDF::NbLines($pdf, $page_width, $SH);
		// Get max line count over all matrix headers
		$hdrs = parseEnum($ProjMetadata[$field]['element_enum']);
		$mtx_hdr_width = round(($page_width - $matrix_label_width)/count($hdrs));
		$widths = array($matrix_label_width); // Default for field label
		$data = array("");
		foreach ($hdrs as $hdr) {
			$widths[] = $mtx_hdr_width;
			$data[] = $hdr;
		}
		$nb=0;
		for($i=0;$i<count($data);$i++)
			$nb=max($nb, PDF::NbLines($pdf, $widths[$i], $data[$i]));
		$lines += $nb;
		// Get count of EACH field in the matrix
		$grid_name = $ProjMetadata[$field]['grid_name'];
		if (isset($ProjMatrixGroupNames[$grid_name])) {
			foreach ($ProjMatrixGroupNames[$grid_name] as $thisfield) {
				// Get label for each
				$thislabel = $ProjMetadata[$thisfield]['element_label'];
				// Get line count for this field
				$lines += PDF::NbLines($pdf, $matrix_label_width, $thislabel);
			}
		}
		// Return height
		return $lines;
	}

	public static function ImageCreateFromBMP($filename)
	{
		//Ouverture du fichier en mode binaire
		if (! $f1 = fopen($filename,"rb")) return FALSE;

		//1 : Chargement des FICHIER
		$FILE = unpack("vfile_type/Vfile_size/Vreserved/Vbitmap_offset", fread($f1,14));
		if ($FILE['file_type'] != 19778) return FALSE;

		//2 : Chargement des BMP
		$BMP = unpack('Vheader_size/Vwidth/Vheight/vplanes/vbits_per_pixel'.
			'/Vcompression/Vsize_bitmap/Vhoriz_resolution'.
			'/Vvert_resolution/Vcolors_used/Vcolors_important', fread($f1,40));
		$BMP['colors'] = pow(2,$BMP['bits_per_pixel']);
		if ($BMP['size_bitmap'] == 0) $BMP['size_bitmap'] = $FILE['file_size'] - $FILE['bitmap_offset'];
		$BMP['bytes_per_pixel'] = $BMP['bits_per_pixel']/8;
		$BMP['bytes_per_pixel2'] = ceil($BMP['bytes_per_pixel']);
		$BMP['decal'] = ($BMP['width']*$BMP['bytes_per_pixel']/4);
		$BMP['decal'] -= floor($BMP['width']*$BMP['bytes_per_pixel']/4);
		$BMP['decal'] = 4-(4*$BMP['decal']);
		if ($BMP['decal'] == 4) $BMP['decal'] = 0;

		//3 : Chargement des couleurs de la palette
		$PALETTE = array();
		if ($BMP['colors'] < 16777216)
		{
			$PALETTE = unpack('V'.$BMP['colors'], fread($f1,$BMP['colors']*4));
		}

		//4 : de l'image
		$IMG = fread($f1,$BMP['size_bitmap']);
		$VIDE = chr(0);

		$res = imagecreatetruecolor($BMP['width'],$BMP['height']);
		$P = 0;
		$Y = $BMP['height']-1;
		while ($Y >= 0)
		{
			$X=0;
			while ($X < $BMP['width'])
			{
				if ($BMP['bits_per_pixel'] == 24)
					$COLOR = unpack("V",substr($IMG,$P,3).$VIDE);
				elseif ($BMP['bits_per_pixel'] == 16)
				{
					$COLOR = unpack("n",substr($IMG,$P,2));
					$COLOR[1] = $PALETTE[$COLOR[1]+1];
				}
				elseif ($BMP['bits_per_pixel'] == 8)
				{
					$COLOR = unpack("n",$VIDE.substr($IMG,$P,1));
					$COLOR[1] = $PALETTE[$COLOR[1]+1];
				}
				elseif ($BMP['bits_per_pixel'] == 4)
				{
					$COLOR = unpack("n",$VIDE.substr($IMG,floor($P),1));
					if (($P*2)%2 == 0) $COLOR[1] = ($COLOR[1] >> 4) ; else $COLOR[1] = ($COLOR[1] & 0x0F);
					$COLOR[1] = $PALETTE[$COLOR[1]+1];
				}
				elseif ($BMP['bits_per_pixel'] == 1)
				{
					$COLOR = unpack("n",$VIDE.substr($IMG,floor($P),1));
					if     (($P*8)%8 == 0) $COLOR[1] =  $COLOR[1]        >>7;
					elseif (($P*8)%8 == 1) $COLOR[1] = ($COLOR[1] & 0x40)>>6;
					elseif (($P*8)%8 == 2) $COLOR[1] = ($COLOR[1] & 0x20)>>5;
					elseif (($P*8)%8 == 3) $COLOR[1] = ($COLOR[1] & 0x10)>>4;
					elseif (($P*8)%8 == 4) $COLOR[1] = ($COLOR[1] & 0x8)>>3;
					elseif (($P*8)%8 == 5) $COLOR[1] = ($COLOR[1] & 0x4)>>2;
					elseif (($P*8)%8 == 6) $COLOR[1] = ($COLOR[1] & 0x2)>>1;
					elseif (($P*8)%8 == 7) $COLOR[1] = ($COLOR[1] & 0x1);
					$COLOR[1] = $PALETTE[$COLOR[1]+1];
				}
				else
					return FALSE;
				imagesetpixel($res,$X,$Y,$COLOR[1]);
				$X++;
				$P += $BMP['bytes_per_pixel'];
			}
			$Y--;
			$P+=$BMP['decal'];
		}

		//Fermeture du fichier
		fclose($f1);

		return $res;
	}

	// LOCKING & E-SIGNATURE: Check if this form has been locked and/or e-signed, when viewing PDF with data
	public static function displayLockingEsig(&$pdf, $record, $event_id, $form, $instance, $est_char_per_line, $y_units_per_line, $bottom_of_page, $study_id_event)
	{
		global $lang;
		if ($record == '') return;
		if (!is_numeric($instance) || $instance < 1) $instance = 1;

		// Check if need to display this info at all
		$sql = "select display, display_esignature, label from redcap_locking_labels where project_id = " . PROJECT_ID . "
				and form_name = '" . db_escape($form) . "' limit 1";
		$q = db_query($sql);
		// If it is NOT in the table OR if it IS in table with display=1, then show locking/e-signature
		$displayLocking		= (!db_num_rows($q) || (db_num_rows($q) && db_result($q, 0, "display") == "1"));
		$displayEsignature  = (db_num_rows($q) && db_result($q, 0, "display_esignature") == "1");

		// LOCKING
		if ($displayLocking)
		{
			// Set customized locking label (i.e affidavit text for e-signatures)
			$custom_lock_label = db_num_rows($q) ? trim(label_decode(db_result($q, 0, "label"))) : "";
			if ($custom_lock_label != '') $custom_lock_label .= "\n\n";
			// Check if locked
			$sql = "select l.username, l.timestamp, u.user_firstname, u.user_lastname from redcap_locking_data l, redcap_user_information u
					where l.project_id = " . PROJECT_ID . " and l.username = u.username
					and l.record = '" . db_escape($record) . "' and l.event_id = '" . db_escape($event_id) . "'
					and l.form_name = '" . db_escape($form) . "' and l.instance = '" . db_escape($instance) . "' limit 1";
			$q = db_query($sql);
			if (db_num_rows($q))
			{
				$form_locked = db_fetch_assoc($q);
				// Set string to capture lock text
				$lock_string = "{$lang['form_renderer_05']} ";
				if ($form_locked['username'] != "") {
					$lock_string .= "{$lang['form_renderer_06']} {$form_locked['username']} ({$form_locked['user_firstname']} {$form_locked['user_lastname']}) ";
				}
				$lock_string .= "{$lang['global_51']} " . DateTimeRC::format_ts_from_ymd($form_locked['timestamp']);

				// E-SIGNATURE
				if ($displayEsignature)
				{
					// Check if e-signed
					$sql = "select e.username, e.timestamp, u.user_firstname, u.user_lastname from redcap_esignatures e, redcap_user_information u
							where e.project_id = " . PROJECT_ID . " and e.username = u.username and e.record = '" . db_escape($record) . "'
							and e.event_id = '" . db_escape($event_id) . "' and e.form_name = '" . db_escape($form) . "' 
							and e.instance = '" . db_escape($instance) . "' limit 1";
					$q = db_query($sql);
					if (db_num_rows($q))
					{
						$form_esigned = db_fetch_assoc($q);
						// Set string to capture lock text
						$lock_string = "{$lang['form_renderer_03']} {$form_esigned['username']} ({$form_esigned['user_firstname']} "
							. "{$form_esigned['user_lastname']}) {$lang['global_51']} " . DateTimeRC::format_ts_from_ymd($form_esigned['timestamp'])
							. "\n" . $lock_string;
					}
				}

				// Now add custom locking text, if was set (will have blank value if not set)
				$lock_string = $custom_lock_label . $lock_string;

				// Render the lock record and e-signature text
				$num_lines = ceil(strlen(strip_tags($lock_string))/$est_char_per_line)+substr_count($lock_string, "\n");
				$pdf = PDF::new_page_check($num_lines, $pdf, $y_units_per_line, $bottom_of_page, $study_id_event);
				$pdf->MultiCell(0,5,'');
				$pdf->MultiCell(0,5,$lock_string,1);
			}
		}
	}
}