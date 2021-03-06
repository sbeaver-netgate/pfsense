<?php
/*
	services_captiveportal_filemanager.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	Copyright (C) 2005-2006 Jonathan De Graeve (jonathan.de.graeve@imelda.be)
	and Paul Taylor (paultaylor@winn-dixie.com).
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
/*
	pfSense_MODULE: captiveportal
*/

##|+PRIV
##|*IDENT=page-services-captiveportal-filemanager
##|*NAME=Services: Captive portal: File Manager page
##|*DESCR=Allow access to the 'Services: Captive portal: File Manager' page.
##|*MATCH=services_captiveportal_filemanager.php*
##|-PRIV

function cpelementscmp($a, $b) {
	return strcasecmp($a['name'], $b['name']);
}

function cpelements_sort() {
	global $config, $cpzone;

	usort($config['captiveportal'][$cpzone]['element'], "cpelementscmp");
}

require("guiconfig.inc");
require("functions.inc");
require_once("filter.inc");
require("shaper.inc");
require("captiveportal.inc");

$cpzone = $_GET['zone'];
if (isset($_POST['zone'])) {
	$cpzone = $_POST['zone'];
}

if (empty($cpzone)) {
	header("Location: services_captiveportal_zones.php");
	exit;
}

if (!is_array($config['captiveportal'])) {
	$config['captiveportal'] = array();
}
$a_cp =& $config['captiveportal'];

$pgtitle = array(gettext("Services"), gettext("Captive portal"), $a_cp[$cpzone]['zone']);
$shortcut_section = "captiveportal";

if (!is_array($a_cp[$cpzone]['element'])) {
	$a_cp[$cpzone]['element'] = array();
}
$a_element =& $a_cp[$cpzone]['element'];

// Calculate total size of all files
$total_size = 0;
foreach ($a_element as $element) {
	$total_size += $element['size'];
}

if ($_POST) {
	unset($input_errors);

	if (is_uploaded_file($_FILES['new']['tmp_name'])) {

		if (!stristr($_FILES['new']['name'], "captiveportal-")) {
			$name = "captiveportal-" . $_FILES['new']['name'];
		} else {
			$name = $_FILES['new']['name'];
		}
		$size = filesize($_FILES['new']['tmp_name']);

		// is there already a file with that name?
		foreach ($a_element as $element) {
			if ($element['name'] == $name) {
				$input_errors[] = sprintf(gettext("A file with the name '%s' already exists."), $name);
				break;
			}
		}

		// check total file size
		if (($total_size + $size) > $g['captiveportal_element_sizelimit']) {
			$input_errors[] = gettext("The total size of all files uploaded may not exceed ") .
				format_bytes($g['captiveportal_element_sizelimit']) . ".";
		}

		if (!$input_errors) {
			$element = array();
			$element['name'] = $name;
			$element['size'] = $size;
			$element['content'] = base64_encode(file_get_contents($_FILES['new']['tmp_name']));

			$a_element[] = $element;
			cpelements_sort();

			write_config();
			captiveportal_write_elements();
			header("Location: services_captiveportal_filemanager.php?zone={$cpzone}");
			exit;
		}
	}
} else if (($_GET['act'] == "del") && !empty($cpzone) && $a_element[$_GET['id']]) {
	conf_mount_rw();
	@unlink("{$g['captiveportal_element_path']}/" . $a_element[$_GET['id']]['name']);
	@unlink("{$g['captiveportal_path']}/" . $a_element[$_GET['id']]['name']);
	conf_mount_ro();
	unset($a_element[$_GET['id']]);
	write_config();
	header("Location: services_captiveportal_filemanager.php?zone={$cpzone}");
	exit;
}

include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

$tab_array = array();
$tab_array[] = array(gettext("Captive portal(s)"), false, "services_captiveportal.php?zone={$cpzone}");
$tab_array[] = array(gettext("MAC"), false, "services_captiveportal_mac.php?zone={$cpzone}");
$tab_array[] = array(gettext("Allowed IP addresses"), false, "services_captiveportal_ip.php?zone={$cpzone}");
$tab_array[] = array(gettext("Allowed Hostnames"), false, "services_captiveportal_hostname.php?zone={$cpzone}");
$tab_array[] = array(gettext("Vouchers"), false, "services_captiveportal_vouchers.php?zone={$cpzone}");
$tab_array[] = array(gettext("File Manager"), true, "services_captiveportal_filemanager.php?zone={$cpzone}");
display_top_tabs($tab_array, true);

require('classes/Form.class.php');

if ($_GET['act'] == 'add') {

	$form = new Form(new Form_Button(
		'Submit',
		'Upload'
	));

	$form->setMultipartEncoding();

	$section = new Form_Section('Upload a new file');

	$section->addInput(new Form_Input(
		'zone',
		null,
		'hidden',
		$cpzone
	));

	$section->addInput(new Form_Input(
		'new',
		'File',
		'file'
	));


	$form->add($section);
	print($form);
}

if (is_array($a_cp[$cpzone]['element'])):
?>
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("Installed Files")?></h2></div>
		<div class="panel-body">
			<div class="table-responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th><?=gettext("Name"); ?></th>
							<th><?=gettext("Size"); ?></th>
							<th>
								<!-- Buttons -->
							</th>
						</tr>
					</thead>
					<tbody>
<?php
	$i = 0;
	foreach ($a_cp[$cpzone]['element'] as $element):
?>
						<tr>
							<td><?=htmlspecialchars($element['name'])?></td>
							<td><?=format_bytes($element['size'])?></td>
							<td>
								<a href="services_captiveportal_filemanager.php?zone=<?=$cpzone?>&amp;act=del&amp;id=<?=$i?>" class="btn btn-xs btn-danger">Delete</a>
							</td>
						</tr>
<?php
		$i++;
	endforeach;

	if($total_size > 0) :
?>
						<tr>
							<th>
								Total
							</th>
							<th>
								<?=format_bytes($total_size);?>
							</th>
							<th></th>
						</tr>
<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
<?php
endif;

?>
	   <nav class="action-buttons">
		   <button id="btnnotes" class="btn btn-default">Show Notes</button>
<?php if (!$_GET['act'] == 'add'): ?>
		   <a href="services_captiveportal_filemanager.php?zone=<?=$cpzone?>&amp;act=add" class="btn btn-success">Add</a>
<?php endif; ?>
	   </nav>
<?php
// The notes displayed on the page are large, the page content comparitively small. A "Note" button
// is provided so that you only see the notes if you ask for them
?>
<div class="help-block panel panel-default">
	<div class="panel-heading"><h2 class="panel-title">Notes</h2></div>
	<div class="panel-body">
	<?=gettext("Any files that you upload here with the filename prefix of captiveportal- will " .
	"be made available in the root directory of the captive portal HTTP(S) server. " .
	"You may reference them directly from your portal page HTML code using relative paths. " .
	"Example: you've uploaded an image with the name 'captiveportal-test.jpg' using the " .
	"file manager. Then you can include it in your portal page like this:")?><br /><br />
	<pre>&lt;img src=&quot;captiveportal-test.jpg&quot; width=... height=...&gt;</pre><br /><br />
	<?=gettext("In addition, you can also upload .php files for execution.	You can pass the filename " .
	"to your custom page from the initial page by using text similar to:")?><br /><br />
	<pre>&lt;a href="/captiveportal-aup.php?zone=$PORTAL_ZONE$&amp;redirurl=$PORTAL_REDIRURL$"&gt;<?=gettext("Acceptable usage policy"); ?>&lt;/a&gt;</pre><br /><br />
	<?=sprintf(gettext("The total size limit for all files is %s."), format_bytes($g['captiveportal_element_sizelimit']))?>
	</div>
</div>

<script>
//<![CDATA[
events.push(function(){

	var hidenotes = true;

	// Hides all elements of the specified class.
	function hideClass(s_class, hide) {
		if(hide)
			$('.' + s_class).hide();
		else
			$('.' + s_class).show();
	}

	hideClass('help-block', hidenotes);

	$(function () {
		$('#btnnotes').on('click', function () {
			hidenotes = !hidenotes;
			hideClass('notes', hidenotes);
		});
	});
});
</script>
<?php

include("foot.inc");