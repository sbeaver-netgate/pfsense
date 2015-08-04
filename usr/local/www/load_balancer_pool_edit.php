<?php
/* $Id$ */
/*
	load_balancer_pool_edit.php
	part of pfSense (https://www.pfsense.org/)

	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	Copyright (C) 2005-2008 Bill Marquette <bill.marquette@gmail.com>.
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
##|*IDENT=page-loadbalancer-pool-edit
##|*NAME=Load Balancer: Pool: Edit page
##|*DESCR=Allow access to the 'Load Balancer: Pool: Edit' page.
##|*MATCH=load_balancer_pool_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("filter.inc");
require_once("util.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/load_balancer_pool.php');

if (!is_array($config['load_balancer']['lbpool'])) {
	$config['load_balancer']['lbpool'] = array();
}

$a_pool = &$config['load_balancer']['lbpool'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_pool[$id]) {
	$pconfig['name'] = $a_pool[$id]['name'];
	$pconfig['mode'] = $a_pool[$id]['mode'];
	$pconfig['descr'] = $a_pool[$id]['descr'];
	$pconfig['port'] = $a_pool[$id]['port'];
	$pconfig['retry'] = $a_pool[$id]['retry'];
	$pconfig['servers'] = &$a_pool[$id]['servers'];
	$pconfig['serversdisabled'] = &$a_pool[$id]['serversdisabled'];
	$pconfig['monitor'] = $a_pool[$id]['monitor'];
}

$changedesc = gettext("Load Balancer: Pool:") . " ";
$changecount = 0;

if ($_POST) {
	$changecount++;

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "name mode port monitor servers");
	$reqdfieldsn = array(gettext("Name"),gettext("Mode"),gettext("Port"),gettext("Monitor"),gettext("Server List"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	/* Ensure that our pool names are unique */
	for ($i=0; isset($config['load_balancer']['lbpool'][$i]); $i++)
		if (($_POST['name'] == $config['load_balancer']['lbpool'][$i]['name']) && ($i != $id))
			$input_errors[] = gettext("This pool name has already been used.  Pool names must be unique.");

	if (preg_match('/[ \/]/', $_POST['name']))
		$input_errors[] = gettext("You cannot use spaces or slashes in the 'name' field.");

	if (strlen($_POST['name']) > 16)
		$input_errors[] = gettext("The 'name' field must be 16 characters or less.");

	if (in_array($_POST['name'], $reserved_table_names))
		$input_errors[] = sprintf(gettext("The name '%s' is a reserved word and cannot be used."), $_POST['name']);

	if (is_alias($_POST['name']))
		$input_errors[] = sprintf(gettext("Sorry, an alias is already named %s."), $_POST['name']);

	if (!is_portoralias($_POST['port']))
		$input_errors[] = gettext("The port must be an integer between 1 and 65535, or a port alias.");

	// May as well use is_port as we want a positive integer and such.
	if (!empty($_POST['retry']) && !is_port($_POST['retry']))
		$input_errors[] = gettext("The retry value must be an integer between 1 and 65535.");

	if (is_array($_POST['servers'])) {
		foreach($pconfig['servers'] as $svrent) {
			if (!is_ipaddr($svrent) && !is_subnetv4($svrent)) {
				$input_errors[] = sprintf(gettext("%s is not a valid IP address or IPv4 subnet (in \"enabled\" list)."), $svrent);
			}
			else if (is_subnetv4($svrent) && subnet_size($svrent) > 64) {
				$input_errors[] = sprintf(gettext("%s is a subnet containing more than 64 IP addresses (in \"enabled\" list)."), $svrent);
			}
		}
	}
	
	if (is_array($_POST['serversdisabled'])) {
		foreach($pconfig['serversdisabled'] as $svrent) {
			if (!is_ipaddr($svrent) && !is_subnetv4($svrent)) {
				$input_errors[] = sprintf(gettext("%s is not a valid IP address or IPv4 subnet (in \"disabled\" list)."), $svrent);
			}
			else if (is_subnetv4($svrent) && subnet_size($svrent) > 64) {
				$input_errors[] = sprintf(gettext("%s is a subnet containing more than 64 IP addresses (in \"disabled\" list)."), $svrent);
			}
		}
	}
	
	$m = array();
	
	for ($i=0; isset($config['load_balancer']['monitor_type'][$i]); $i++)
		$m[$config['load_balancer']['monitor_type'][$i]['name']] = $config['load_balancer']['monitor_type'][$i];

	if (!isset($m[$_POST['monitor']]))
		$input_errors[] = gettext("Invalid monitor chosen.");

	if (!$input_errors) {
		$poolent = array();
		if(isset($id) && $a_pool[$id])
			$poolent = $a_pool[$id];
			
		if($poolent['name'] != "")
			$changedesc .= sprintf(gettext(" modified '%s' pool:"), $poolent['name']);
		
		update_if_changed("name", $poolent['name'], $_POST['name']);
		update_if_changed("mode", $poolent['mode'], $_POST['mode']);
		update_if_changed("description", $poolent['descr'], $_POST['descr']);
		update_if_changed("port", $poolent['port'], $_POST['port']);
		update_if_changed("retry", $poolent['retry'], $_POST['retry']);
		update_if_changed("servers", $poolent['servers'], $_POST['servers']);
		update_if_changed("serversdisabled", $poolent['serversdisabled'], $_POST['serversdisabled']);
		update_if_changed("monitor", $poolent['monitor'], $_POST['monitor']);

		if (isset($id) && $a_pool[$id]) {
			/* modify all virtual servers with this name */
			for ($i = 0; isset($config['load_balancer']['virtual_server'][$i]); $i++) {
				if ($config['load_balancer']['virtual_server'][$i]['lbpool'] == $a_pool[$id]['name'])
					$config['load_balancer']['virtual_server'][$i]['lbpool'] = $poolent['name'];
			}
			$a_pool[$id] = $poolent;
		} else
			$a_pool[] = $poolent;
		
		if ($changecount > 0) {
			/* Mark pool dirty */
			mark_subsystem_dirty('loadbalancer');
			write_config($changedesc);
		}

		header("Location: load_balancer_pool.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("Load Balancer"),gettext("Pool"),gettext("Edit"));
$shortcut_section = "relayd";

include("head.inc");
?>

<script type="text/javascript">
//<![CDATA[
events.push(function(){
	function clearcombo(){
	  for (var i=document.iform.serversSelect.options.length-1; i>=0; i--){
		document.iform.serversSelect.options[i] = null;
	  }
	  document.iform.serversSelect.selectedIndex = -1;
	}
	
	function AddServerToPool() {
		$('[name="servers[]"]').append(new Option($('#ipaddr').val(), $('#ipaddr').val()));
	}
	
	
	function AllServers(id, selectAll) {
	   var opts = document.getElementById(id).getElementsByTagName('option');
	   for (i = 0; i < opts.length; i++)
	   {
	       opts[i].selected = selectAll;
	   }
	}
	
	
	function RemoveServerFromPool(form, field)
	{
		var theSel = form[field];
		var selIndex = theSel.selectedIndex;
		if (selIndex != -1) {
			for(i=theSel.length-1; i>=0; i--)
			{
				if(theSel.options[i].selected)
				{
					theSel.options[i] = null;
				}
			}
			if (theSel.length > 0) {
				theSel.selectedIndex = selIndex == 0 ? 0 : selIndex - 1;
			}
		}
	}
	
	function addOption(theSel, theText, theValue)
	{
		var newOpt = new Option(theText, theValue);
		var selLength = theSel.length;
		theSel.options[selLength] = newOpt;
	}
	
	function deleteOption(theSel, theIndex)
	{ 
		var selLength = theSel.length;
		if(selLength>0)
		{
			theSel.options[theIndex] = null;
		}
	}
	
	function moveOptions(theSelFrom, theSelTo)
	{
		var selLength = theSelFrom.length;
		var selectedText = new Array();
		var selectedValues = new Array();
		var selectedCount = 0;
	
		var i;
	
		// Find the selected Options in reverse order
		// and delete them from the 'from' Select.
		for(i=selLength-1; i>=0; i--)
		{
			if(theSelFrom.options[i].selected)
			{
				selectedText[selectedCount] = theSelFrom.options[i].text;
				selectedValues[selectedCount] = theSelFrom.options[i].value;
				deleteOption(theSelFrom, i);
				selectedCount++;
			}
		}
	
		// Add the selected text/values in reverse order.
		// This will add the Options to the 'to' Select
		// in the same order as they were in the 'from' Select.
		for(i=selectedCount-1; i>=0; i--)
		{
			addOption(theSelTo, selectedText[i], selectedValues[i]);
		}
	}
	
	function checkPoolControls() {
		var active = document.iform.serversSelect;
		var inactive = document.iform.serversDisabledSelect;
		if (jQuery("#mode").val() == "failover") {
			if (jQuery("#serversSelect option").length > 0) {
				jQuery("#moveToEnabled").prop("disabled",true);
			} else {
				jQuery("#moveToEnabled").prop("disabled",false);
			}
		} else {
			jQuery("#moveToEnabled").prop("disabled",false);
		}
	}
	
	function enforceFailover() {
		if (jQuery("#mode").val() != "failover") {
			return;
		}
	
		var active = document.iform.serversSelect;
		var inactive = document.iform.serversDisabledSelect;
		var count = 0;
		var moveText = new Array();
		var moveVals = new Array();
		var i;
		if (active.length > 1) {
			// Move all but one entry to the disabled list
			for (i=active.length-1; i>0; i--) {
				moveText[count] = active.options[i].text;
				moveVals[count] = active.options[i].value;
				deleteOption(active, i);
				count++;
			}
			for (i=count-1; i>=0; i--) {
				addOption(inactive, moveText[i], moveVals[i]);
			}
		}
	}
	
	// functions up() and down() modified from http://www.babailiica.com/js/sorter/
	
	function up(obj) {
		var sel = new Array();
		for (var i=0; i<obj.length; i++) {
			if (obj[i].selected == true) {
				sel[sel.length] = i;
			}
		}
		for (i in sel) {
			if (sel[i] != 0 && !obj[sel[i]-1].selected) {
				var tmp = new Array(obj[sel[i]-1].text, obj[sel[i]-1].value);
				obj[sel[i]-1].text = obj[sel[i]].text;
				obj[sel[i]-1].value = obj[sel[i]].value;
				obj[sel[i]].text = tmp[0];
				obj[sel[i]].value = tmp[1];
				obj[sel[i]-1].selected = true;
				obj[sel[i]].selected = false;
			}
		}
	}
	
	function down(obj) {
		var sel = new Array();
		for (var i=obj.length-1; i>-1; i--) {
			if (obj[i].selected == true) {
				sel[sel.length] = i;
			}
		}
		
		for (i in sel) {
			if (sel[i] != obj.length-1 && !obj[sel[i]+1].selected) {
				var tmp = new Array(obj[sel[i]+1].text, obj[sel[i]+1].value);
				obj[sel[i]+1].text = obj[sel[i]].text;
				obj[sel[i]+1].value = obj[sel[i]].value;
				obj[sel[i]].text = tmp[0];
				obj[sel[i]].value = tmp[1];
				obj[sel[i]+1].selected = true;
				obj[sel[i]].selected = false;
			}
		}
	}

    // Make button a plain button, not a submit button
    $("#btnaddtopool").prop('type','button');
    
    // On click, copy the hidden 'mymac' text to the 'mac' input
    $("#btnaddtopool").click(function() {
        AddServerToPool(); 
//        enforceFailover(); 
//        checkPoolControls();
    });    	
	
});
//]]>
</script>

<?php 
if ($input_errors)
	print_input_errors($input_errors);

require('classes/Form.class.php');

$form = new Form(new Form_Button(
	'Submit',
	gettext("Save")
));

$section = new Form_Section('Add/edit Load Balancer - Pool entry');

$section->addInput(new Form_Input(
	'name',
	'Name',
	'text',
	$pconfig['name']
));

$section->addInput(new Form_Select(
	'mode',
	'Mode',
	$pconfig['mode'],
	array(
		'loadbalance' => 'Load Balance',
		'failover' => 'Manual Failover'
	)
));

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
));

$section->addInput(new Form_Input(
	'port',
	'Port',
	'text',
	$pconfig['port']
))->setHelp('This is the port your servers are listening on. You may also specify a port alias listed in Firewall -> Aliases here.');

$section->addInput(new Form_Input(
	'retry',
	'Retry',
	'number',
	$pconfig['retry'],
	['min' => '1', 'max' => '65536']
))->setHelp('Optionally specify how many times to retry checking a server before declaring it down.');

$form->add($section);

$section = new Form_Section('Add item to the pool');

$monitorlist = array();

foreach ($config['load_balancer']['monitor_type'] as $monitor) 
	$monitorlist[$monitor['name']] = $monitor['name'];

if(count($config['load_balancer']['monitor_type'])) {
	$section->addInput(new Form_Select(
		'monitor',
		'Monitor',
		$pconfig['monitor'],
		$monitorlist
	));
} else {
	$section->addInput(new Form_StaticText(
		'Monitor',
		'Please add a monitor IP address on the monitors tab if you wish to use this feature."'
	));
}

$group = new Form_Group('Server IP Address');

$group->add(new Form_IpAddress(
	'ipaddr',
	'IP Address',
	$pconfig['ipaddr']
));

$group->add(new Form_Button(
	'btnaddtopool',
	'Add to pool'
))->removeClass('btn-primary')->addClass('btn-default');

$section->add($group);

$form->add($section);

$section = new Form_Section('Current pool members');

$group = new Form_Group('Members');

$group->add(new Form_Select(
	'serversdisabled',
	null,
	$pconfig['serversdisabled'],
	is_array($pconfig['serversdisabled']) ? array_combine($pconfig['serversdisabled'], $pconfig['serversdisabled']) : array(),
	true
))->setHelp('Disabled');

$group->add(new Form_Select(
	'servers',
	null,
	$pconfig['servers'],
	is_array($pconfig['servers']) ? array_combine($pconfig['servers'], $pconfig['servers']) : array(),
	true
))->setHelp('Enabled (Default)');
	
$section->add($group);

$group = new Form_Group('');

$group->add(new Form_Button(
	'button1',
	'Remove'
))->removeClass('btn-primary')->addClass('btn-default btn-sm');

$group->add(new Form_Button(
	'button1',
	'Remove'
))->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->add($group);

$group = new Form_Group('');

$group->add(new Form_Button(
	'Remove',
	'Move to enabled list >'
))->removeClass('btn-primary')->addClass('btn-default btn-sm');

$group->add(new Form_Button(
	'button1',
	'< Move to disabled list'
))->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->add($group);

$form->add($section);

print($form);

 include("foot.inc"); 