<?php
/*
	captive_portal_status.widget.php
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	All rights reserved.

	Copyright (C) 2007 Sam Wenham
	All rights reserved.

	status_captiveportal.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

$nocsrf = true;

require_once("globals.inc");
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("captiveportal.inc");

?>

<?php

if (!is_array($config['captiveportal'])) {
	$config['captiveportal'] = array();
}
$a_cp =& $config['captiveportal'];

$cpzone = $_GET['zone'];
if (isset($_POST['zone'])) {
	$cpzone = $_POST['zone'];
}

if (isset($cpzone) && !empty($cpzone) && isset($a_cp[$cpzone]['zoneid'])) {
	$cpzoneid = $a_cp[$cpzone]['zoneid'];
}

if (($_GET['act'] == "del") && !empty($cpzone) && isset($cpzoneid)) {
	captiveportal_disconnect_client($_GET['id']);
}
unset($cpzone);

flush();

function clientcmp($a, $b) {
	global $order;
	return strcmp($a[$order], $b[$order]);
}

$cpdb_all = array();

$showact = isset($_GET['showact']) ? 1 : 0;

foreach ($a_cp as $cpzone => $cp) {
	$cpdb = captiveportal_read_db();
	foreach ($cpdb as $cpent) {
		$cpent[10] = $cpzone;
		if ($showact == 1) {
			$cpent[11] = captiveportal_get_last_activity($cpent[2], $cpentry[3]);
		}
		$cpdb_all[] = $cpent;
	}
}

if ($_GET['order']) {
	if ($_GET['order'] == "ip") {
		$order = 2;
	} else if ($_GET['order'] == "mac") {
		$order = 3;
	} else if ($_GET['order'] == "user") {
		$order = 4;
	} else if ($_GET['order'] == "lastact") {
		$order = 5;
	} else if ($_GET['order'] == "zone") {
		$order = 10;
	} else {
		$order = 0;
	}
	usort($cpdb_all, "clientcmp");
}
?>
<table class="table">
	<thead>
	<tr>
		<th><a href="?order=ip&amp;showact=<?=$showact;?>">IP address</a></td>
		<th><a href="?order=mac&amp;showact=<?=$showact;?>">MAC address</a></td>
		<th><a href="?order=user&amp;showact=<?=$showact;?>"><?=gettext("Username");?></a></td>
<?php if ($showact == 1): ?>
		<th><a href="?order=start&amp;showact=<?=$showact;?>"><?=gettext("Session start");?></a></td>
		<th><a href="?order=start&amp;showact=<?=$showact;?>"><?=gettext("Last activity");?></a></td>
<?php endif; ?>
	</tr>
	</thead>
	<tbody>
<?php foreach ($cpdb_all as $cpent): ?>
	<tr>
		<td><?=$cpent[2];?></td>
		<td><?=$cpent[3];?></td>
		<td><?=$cpent[4];?></td>
<?php if ($showact == 1): ?>
		<td><?=date("m/d/Y H:i:s", $cpent[0]);?></td>
		<td><?php if ($cpent[11] && ($cpent[11] > 0)) echo date("m/d/Y H:i:s", $cpent[11]);?></td>
<?php endif; ?>
		<td>
			<a href="?order=<?=htmlspecialchars($_GET['order']);?>&amp;showact=<?=$showact;?>&amp;act=del&amp;zone=<?=$cpent[10];?>&amp;id=<?=$cpent[5];?>" class="btn btn-xs btn-danger">
				delete
			</a>
		</td>
	</tr>
<?php
endforeach;
?>
	</tbody>
</table>