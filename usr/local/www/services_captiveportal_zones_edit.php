<?php
/*
	services_captiveportal_zones_edit.php
	Copyright (C) 2011 Ermal Luçi
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
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
##|*IDENT=page-services-captiveportal-editzones
##|*NAME=Services: Captive portal: Edit Zones page
##|*DESCR=Allow access to the 'Services: Captive portal: Edit Zones' page.
##|*MATCH=services_captiveportal_zones_edit.php*
##|-PRIV

require("guiconfig.inc");
require("functions.inc");
require_once("filter.inc");
require("shaper.inc");
require("captiveportal.inc");

$pgtitle = array(gettext("Services"), gettext("Captive portal"), gettext("Edit Zones"));
$shortcut_section = "captiveportal";

if (!is_array($config['captiveportal'])) {
	$config['captiveportal'] = array();
}
$a_cp =& $config['captiveportal'];

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "zone");
	$reqdfieldsn = array(gettext("Zone name"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (preg_match('/[^A-Za-z0-9_]/', $_POST['zone'])) {
		$input_errors[] = gettext("The zone name can only contain letters, digits, and underscores ( _ ).");
	}

	foreach ($a_cp as $cpkey => $cpent) {
		if ($cpent['zone'] == $_POST['zone']) {
			$input_errors[] = sprintf("[%s] %s.", $_POST['zone'], gettext("already exists"));
			break;
		}
	}

	if (!$input_errors) {
		$cpzone = strtolower($_POST['zone']);
		$a_cp[$cpzone] = array();
		$a_cp[$cpzone]['zone'] = str_replace(" ", "", $_POST['zone']);
		$a_cp[$cpzone]['descr'] = $_POST['descr'];
		$a_cp[$cpzone]['localauth_priv'] = true;
		write_config();

		header("Location: services_captiveportal.php?zone={$cpzone}");
		exit;
	}
}

include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

require('classes/Form.class.php');

$form = new Form(new Form_Button(
	'submit',
	'Continue'
));

$section = new Form_Section('Edit Captive Portal Zones');

$section->addInput(new Form_Input(
	'zone',
	'Zone name'
))->setPattern('[0-9A-Za-z_]+')->setHelp('Zone name. Can only contain letters, digits, and underscores (_).');

$section->addInput(new Form_Input(
	'descr',
	'Zone description'
))->setHelp('You may enter a description here for your reference (not parsed).');

$form->add($section);

print($form);

include("foot.inc");