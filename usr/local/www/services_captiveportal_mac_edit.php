<?php
/*
	services_captiveportal_mac_edit.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	Copyright (C) 2004 Dinesh Nair <dinesh@alphaque.com>
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
##|*IDENT=page-services-captiveportal-editmacaddresses
##|*NAME=Services: Captive portal: Edit MAC Addresses page
##|*DESCR=Allow access to the 'Services: Captive portal: Edit MAC Addresses' page.
##|*MATCH=services_captiveportal_mac_edit.php*
##|-PRIV

function passthrumacscmp($a, $b) {
	return strcmp($a['mac'], $b['mac']);
}

function passthrumacs_sort() {
	global $config, $cpzone;

	usort($config['captiveportal'][$cpzone]['passthrumac'], "passthrumacscmp");
}

require("guiconfig.inc");
require("functions.inc");
require_once("filter.inc");
require("shaper.inc");
require("captiveportal.inc");

global $cpzone;
global $cpzoneid;

$pgtitle = array(gettext("Services"), gettext("Captive portal"), gettext("Edit MAC address rules"));
$shortcut_section = "captiveportal";

$cpzone = $_GET['zone'];
if (isset($_POST['zone'])) {
	$cpzone = $_POST['zone'];
}

if (empty($cpzone) || empty($config['captiveportal'][$cpzone])) {
	header("Location: services_captiveportal_zones.php");
	exit;
}

if (!is_array($config['captiveportal'])) {
	$config['captiveportal'] = array();
}
$a_cp =& $config['captiveportal'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (!is_array($a_cp[$cpzone]['passthrumac'])) {
	$a_cp[$cpzone]['passthrumac'] = array();
}
$a_passthrumacs = &$a_cp[$cpzone]['passthrumac'];

if (isset($id) && $a_passthrumacs[$id]) {
	$pconfig['action'] = $a_passthrumacs[$id]['action'];
	$pconfig['mac'] = $a_passthrumacs[$id]['mac'];
	$pconfig['bw_up'] = $a_passthrumacs[$id]['bw_up'];
	$pconfig['bw_down'] = $a_passthrumacs[$id]['bw_down'];
	$pconfig['descr'] = $a_passthrumacs[$id]['descr'];
	$pconfig['username'] = $a_passthrumacs[$id]['username'];
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "action mac");
	$reqdfieldsn = array(gettext("Action"), gettext("MAC address"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	$_POST['mac'] = strtolower(str_replace("-", ":", $_POST['mac']));

	if ($_POST['mac']) {
		if (is_macaddr($_POST['mac'])) {
			$iflist = get_interface_list();
			foreach ($iflist as $if) {
				if ($_POST['mac'] == strtolower($if['mac'])) {
					$input_errors[] = sprintf(gettext("The MAC address %s belongs to a local interface, you cannot use it here."), $_POST['mac']);
					break;
				}
			}
		} else {
			$input_errors[] = sprintf("%s. [%s]", gettext("A valid MAC address must be specified"), $_POST['mac']);
		}
	}
	if ($_POST['bw_up'] && !is_numeric($_POST['bw_up'])) {
		$input_errors[] = gettext("Upload speed needs to be an integer");
	}
	if ($_POST['bw_down'] && !is_numeric($_POST['bw_down'])) {
		$input_errors[] = gettext("Download speed needs to be an integer");
	}

	foreach ($a_passthrumacs as $macent) {
		if (isset($id) && ($a_passthrumacs[$id]) && ($a_passthrumacs[$id] === $macent)) {
			continue;
		}

		if ($macent['mac'] == $_POST['mac']) {
			$input_errors[] = sprintf("[%s] %s.", $_POST['mac'], gettext("already exists"));
			break;
		}
	}

	if (!$input_errors) {
		$mac = array();
		$mac['action'] = $_POST['action'];
		$mac['mac'] = $_POST['mac'];
		if ($_POST['bw_up']) {
			$mac['bw_up'] = $_POST['bw_up'];
		}
		if ($_POST['bw_down']) {
			$mac['bw_down'] = $_POST['bw_down'];
		}
		if ($_POST['username']) {
			$mac['username'] = $_POST['username'];
		}

		$mac['descr'] = $_POST['descr'];

		if (isset($id) && $a_passthrumacs[$id]) {
			$oldmac = $a_passthrumacs[$id];
			$a_passthrumacs[$id] = $mac;
		} else {
			$oldmac = $mac;
			$a_passthrumacs[] = $mac;
		}
		passthrumacs_sort();

		write_config();

		if (isset($config['captiveportal'][$cpzone]['enable'])) {
			$cpzoneid = $config['captiveportal'][$cpzone]['zoneid'];
			$rules = captiveportal_passthrumac_delete_entry($oldmac);
			$rules .= captiveportal_passthrumac_configure_entry($mac);
			$uniqid = uniqid("{$cpzone}_macedit");
			file_put_contents("{$g['tmp_path']}/{$uniqid}_tmp", $rules);
			mwexec("/sbin/ipfw -x {$cpzoneid} -q {$g['tmp_path']}/{$uniqid}_tmp");
			@unlink("{$g['tmp_path']}/{$uniqid}_tmp");
			unset($cpzoneid);
		}

		header("Location: services_captiveportal_mac.php?zone={$cpzone}");
		exit;
	}
}

// Get the MAC address
$ip = $_SERVER['REMOTE_ADDR'];
$mymac = `/usr/sbin/arp -an | grep '('{$ip}')' | head -n 1 | cut -d" " -f4`;
$mymac = str_replace("\n","",$mymac);

include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

require('classes/Form.class.php');

$form = new Form();

$section = new Form_Section('Edit MAC address rules');

$section->addInput(new Form_Select(
	'action',
	'Action',
	strtolower($pconfig['action']),
	array('pass' => 'Pass', 'block' => 'Block')
))->setHelp('Choose what to do with packets coming from this MAC address.');

$macaddress = new Form_Input(
	'mac',
	'MAC Address',
	'text',
	$pconfig['mac'],
	['placeholder' => 'xx:xx:xx:xx:xx:xx']
);

$btnmymac = new Form_Button(
	'btnmymac',
	'Copy My MAC'
	);

$btnmymac->removeClass('btn-primary')->addClass('btn-success btn-sm');

$group = new Form_Group('MAC controls');
$group->add($macaddress);
$group->add($btnmymac);
$group->setHelp('MAC address (6 hex octets separated by colons)');
$section->add($group);

$section->addInput(new Form_Input(
	'bw_up',
	'Bandwidth up',
	'text',
	$pconfig['bw_up']
))->setHelp('Enter an upload limit to be enforced on this MAC in Kbit/s');

$section->addInput(new Form_Input(
	'bw_down',
	'Bandwidth down',
	'text',
	$pconfig['bw_down']
))->setHelp('Enter a download limit to be enforced on this MAC in Kbit/s');

$section->addInput(new Form_Input(
	'zone',
	null,
	'hidden',
	$cpzone
));

if (isset($id) && $a_passthrumacs[$id]) {
	$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

if (isset($pconfig['username']) && $pconfig['username']) {
	$section->addInput(new Form_Input(
		'username',
		null,
		'hidden',
		$pconfig['username']
	));
}

$form->add($section);
print($form);
?>

<script>
//<![CDATA[
events.push(function(){
	// Make the ‘Copy My MAC’ button a plain button, not a submit button
	$("#btnmymac").prop('type','button');

	// On click, copy the hidden 'mymac' text to the 'mac' input
	$("#btnmymac").click(function() {
		$('#mac').val('<?=$mymac?>');
	});
});
//]]>
</script>

<?php include("foot.inc");