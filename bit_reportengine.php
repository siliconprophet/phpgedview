<?php
/**
 * Report Engine
 *
 * Processes PGV XML Reports and generates a report
 *
 * phpGedView: Genealogy Viewer
 * Copyright (C) 2002 to 2005  John Finlay and Others
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @package PhpGedView
 * @subpackage Reports
 * @version $Id: bit_reportengine.php,v 1.1 2007/05/31 13:56:58 lsces Exp $
 */

/**
 * Initialization
 */ 
require_once( '../bit_setup_inc.php' );

// Is package installed and enabled
$gBitSystem->verifyPackage( 'phpgedview' );

include_once( PHPGEDVIEW_PKG_PATH.'BitGEDCOM.php' );

$gContent = new BitGEDCOM();

require_once("includes/functions_charts.php");
//require($factsfile["english"]);
//if (file_exists($factsfile[$LANGUAGE])) require($factsfile[$LANGUAGE]);

//-- try to increase the time limit because reports can take a long time
@set_time_limit($TIME_LIMIT*2);

/**
 * function to get the values for the given tag
 */
function get_tag_values($tag) {
	global $tags, $values;

	$indexes = $tags[$tag];
	$vals = array();
	foreach($indexes as $indexval => $i) {
		$vals[] = $values[$i];
	}
	return $vals;
}

if (!isset($_REQUEST['action'])) $action = "choose";
else $action = $_REQUEST['action'];
if (!isset($_REQUEST['report'])) $report = "";
else $report = $_REQUEST['report'];
if (!isset($output)) $output = "PDF";
if (!isset($vars)) $vars = array();
if (!isset($varnames)) $varnames = array();
if (!isset($type)) $type = array();

//-- setup the arrays
$newvars = array();
foreach($vars as $name=>$var) {
	$var = clean_input($var);
	$newvars[$name]["id"] = $var;
	if (!empty($type[$name]) && (($type[$name]=="INDI")||($type[$name]=="FAM")||($type[$name]=="SOUR"))) {
		$gedcom = find_gedcom_record($var);
		if (empty($gedcom)) $action="setup";
		if ($type[$name]=="FAM") {
			if (preg_match("/0 @.*@ INDI/", $gedcom)>0) {
				$fams = find_sfamily_ids($var);
				if (!empty($fams[0])) {
					$gedcom = find_family_record($fams[0]);
					if (!empty($gedcom)) $vars[$name] = $fams[0];
					else $action="setup";
				}
			}
		}
		$newvars[$name]["gedcom"] = $gedcom;
	}
}
$vars = $newvars;

foreach($varnames as $indexval => $name) {
	if (!isset($vars[$name])) {
		$vars[$name]["id"] = "";
	}
}

if ($report == "") {
	$reports = get_report_list();
}
if (!empty($report)) {
	$r = basename($report);
	//	if (!isset($reports[$r]["access"])) $action = "choose";
	//	else if ($reports[$r]["access"]<getUserAccessLevel(getUserName())) $action = "choose";
}

//-- choose a report to run
//-- setup report to run
if ($action=="setup") {
	//-- make sure the report exists
	if (!file_exists(PHPGEDVIEW_PKG_PATH.$report)) {
		$gBitSmarty->assign('errors', 'The specified report cannot be found' );
		$action = 'choose';
	}
	else {
		require_once("includes/reportheader.php");
		$report_array = array();
		//-- start the sax parser
		$xml_parser = xml_parser_create();
		//-- make sure everything is case sensitive
		xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, false);
		//-- set the main element handler functions
		xml_set_element_handler($xml_parser, "startElement", "endElement");
		//-- set the character data handler
		xml_set_character_data_handler($xml_parser, "characterData");

		//-- open the file
		if (!($fp = fopen(PHPGEDVIEW_PKG_PATH.$report, "r"))) {
		   die("could not open XML input");
		}
		//-- read the file and parse it 4kb at a time
		while ($data = fread($fp, 4096)) {
			if (!xml_parse($xml_parser, $data, feof($fp))) {
				die(sprintf($data."\nXML error: %s at line %d", xml_error_string(xml_get_error_code($xml_parser)), xml_get_current_line_number($xml_parser)));
			}
		}
		xml_parser_free($xml_parser);

		$gBitSmarty->assign( "pagetitle", tra( 'Report options selection' ) );
		$gBitSmarty->assign_by_ref( "report", $report );
		$gBitSmarty->assign_by_ref( "report_array", $report_array );
		$gBitSystem->display( 'bitpackage:phpgedview/report_setup.tpl', tra( 'Report options selection' ) );
	}
}
//-- run the report
else if ($action=="run") {
	//-- load the report generator
	if ($output=="HTML") require("includes/reporthtml.php");
	else if ($output=="PDF") require("includes/reportpdf.php");

	//-- start the sax parser
	$xml_parser = xml_parser_create();
	//-- make sure everything is case sensitive
	xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, false);
	//-- set the main element handler functions
	xml_set_element_handler($xml_parser, "startElement", "endElement");
	//-- set the character data handler
	xml_set_character_data_handler($xml_parser, "characterData");

	//-- open the file
	if (!($fp = fopen($report, "r"))) {
	   die("could not open XML input");
	}
	//-- read the file and parse it 4kb at a time
	while ($data = fread($fp, 4096)) {
		if (!xml_parse($xml_parser, $data, feof($fp))) {
			die(sprintf($data."\nXML error: %s at line %d", xml_error_string(xml_get_error_code($xml_parser)), xml_get_current_line_number($xml_parser)));
		}
	}
	xml_parser_free($xml_parser);

}

if ($action=="choose") {
	$reports = get_report_list(true);

	$gBitSmarty->assign( "pagetitle", tra( 'Report selection' ) );
	$gBitSmarty->assign_by_ref( "reports", strtoupper(basename($report)) );
	$gBitSystem->display( 'bitpackage:phpgedview/report_menu.tpl', tra( 'Report selection' ) );
}
?>