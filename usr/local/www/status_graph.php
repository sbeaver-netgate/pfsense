<?php
/* $Id$ */
/*
	status_graph.php
	Part of pfSense
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	Copyright (C) 2004 Scott Ullrich
	All rights reserved.

	Originally part of m0n0wall (http://m0n0.ch/wall)
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
/*
	pfSense_MODULE:	routing
*/

##|+PRIV
##|*IDENT=page-status-trafficgraph
##|*NAME=Status: Traffic Graph page
##|*DESCR=Allow access to the 'Status: Traffic Graph' page.
##|*MATCH=status_graph.php*
##|*MATCH=bandwidth_by_ip.php*
##|*MATCH=graph.php*
##|*MATCH=ifstats.php*
##|-PRIV

require("guiconfig.inc");

if ($_POST['width']) {
	$width = $_POST['width'];
} else {
	$width = "100%";
}

if ($_POST['height']) {
	$height = $_POST['height'];
} else {
	$height = "200";
}

// Get configured interface list
$ifdescrs = get_configured_interface_with_descr();
if (isset($config['ipsec']['enable'])) {
	$ifdescrs['enc0'] = "IPsec";
}
foreach (array('server', 'client') as $mode) {
	if (is_array($config['openvpn']["openvpn-{$mode}"])) {
		foreach ($config['openvpn']["openvpn-{$mode}"] as $id => $setting) {
			if (!isset($setting['disable'])) {
				$ifdescrs['ovpn' . substr($mode, 0, 1) . $setting['vpnid']] = gettext("OpenVPN") . " ".$mode.": ".htmlspecialchars($setting['description']);
			}
		}
	}
}

if ($_POST['if']) {
	$curif = $_POST['if'];
	$found = false;
	foreach ($ifdescrs as $descr => $ifdescr) {
		if ($descr == $curif) {
			$found = true;
			break;
		}
	}
	if ($found === false) {
		header("Location: status_graph.php");
		exit;
	}
} else {
	if (empty($ifdescrs["wan"])) {
		/* Handle the case when WAN has been disabled. Use the first key in ifdescrs. */
		reset($ifdescrs);
		$curif = key($ifdescrs);
	} else {
		$curif = "wan";
	}
}
if ($_POST['sort']) {
	$cursort = $_POST['sort'];
} else {
	$cursort = "";
}
if ($_POST['filter']) {
	$curfilter = $_POST['filter'];
} else {
	$curfilter = "";
}
if ($_POST['hostipformat']) {
	$curhostipformat = $_POST['hostipformat'];
} else {
	$curhostipformat = "";
}

function iflist() {
	global $ifdescrs;

	$iflist = array();

	foreach ($ifdescrs as $ifn => $ifd) {
		$iflist[$ifn] = $ifd;
	}

	return($iflist);
}

$pgtitle = array(gettext("Status"),gettext("Traffic Graph"));

include("head.inc");

require('classes/Form.class.php');

$form = new Form(false);
$form->addClass('auto-submit');

$section = new Form_Section('Graph settings');

$group = new Form_Group('');

$group->add(new Form_Select(
	'if',
	null,
	$curif,
	iflist()
))->setHelp('Interface');

$group->add(new Form_Select(
	'sort',
	null,
	$cursort,
	array (
		'in'	=> 'Bandwidth In',
		'out'	=> 'Bandwidth Out'
	)
))->setHelp('Sort by');

$group->add(new Form_Select(
	'filter',
	null,
	$curfilter,
	array (
		'local'	=> 'Local',
		'remote'=> 'Remote',
		'all'	=> 'All'
	)
))->setHelp('Filter');

$group->add(new Form_Select(
	'hostipformat',
	null,
	$curhostipformat,
	array (
		''			=> 'IP Address',
		'hostname'	=> 'Host Name',
		'fqdn'		=> 'FQDN'
	)
))->setHelp('Display');

$section->add($group);

$form->add($section);
print $form;

?>
<script>

function updateBandwidth(){
	$.ajax(
		'/bandwidth_by_ip.php',
		{
			type: 'get',
			data: $(document.forms[0]).serialize(),
			success: function (data) {
				var hosts_split = data.split("|");

				$('#top10-hosts').empty();

				//parse top ten bandwidth abuser hosts
				for (var y=0; y<10; y++){
					if ((y < hosts_split.length) && (hosts_split[y] != "") && (hosts_split[y] != "no info")) {
						hostinfo = hosts_split[y].split(";");

						$('#top10-hosts').append('<tr>'+
							'<td>'+ hostinfo[0] +'</td>'+
							'<td>'+ hostinfo[1] +' Bits/sec</td>'+
							'<td>'+ hostinfo[2] +' Bits/sec</td>'+
						'</tr>');
					}
				}
			},
	});
}

events.push(function(){
	$('form.auto-submit').on('change', function(){
		$(this).submit();
	});

	setInterval('updateBandwidth()', 1000);

	updateBandwidth();
});
</script>
<?php

/* link the ipsec interface magically */
if (isset($config['ipsec']['enable']) || isset($config['ipsec']['client']['enable'])) {
	$ifdescrs['enc0'] = "IPsec";
}

?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">Traffic graph</h2>
	</div>
	<div class="panel-body">
		<div class="col-sm-6">
			<object data="graph.php?ifnum=<?=htmlspecialchars($curif);?>&amp;ifname=<?=rawurlencode($ifdescrs[htmlspecialchars($curif)]);?>">
				<param name="id" value="graph" />
				<param name="type" value="image/svg+xml" />
				<param name="width" value="<? echo $width; ?>" />
				<param name="height" value="<? echo $height; ?>" />
				<param name="pluginspage" value="http://www.adobe.com/svg/viewer/install/auto" />
			</object>
		</div>
		<div class="col-sm-6">
			<table class="table table-striped table-condensed">
				<thead>
					<tr>
						<th><?=(($curhostipformat=="") ? gettext("Host IP") : gettext("Host Name or IP")); ?></th>
						<th><?=gettext("Bandwidth In"); ?></th>
						<th><?=gettext("Bandwidth Out"); ?></th>
					</tr>
				</thead>
				<tbody id="top10-hosts">
					<!-- to be added by javascript -->
				</tbody>
			</table>
		</div>
	</div>
</div>
<?php include("foot.inc");