<?php
/******************************
 * Old Guard EQdkp Plugin
 * Copyright 2013-2014
 * Licensed under the GNU GPL.  See COPYING for full terms.
 ******************************/

define('EQDKP_INC', true);
define('IN_ADMIN', true);
$eqdkp_root_path = './../../';

require_once ($eqdkp_root_path . 'common.php');

global $eqdkp, $tpl;

function generateStandings()
{
    global $db;
    
    $sql = "SELECT member_name, member_earned - member_spent + member_adjustment as dkp
            FROM __members m
            INNER JOIN __member_ranks r
            ON m.member_rank_id = r.rank_id
            AND r.rank_hide = '0'";
    
    $rs = $db->query($sql);
    
    $standings = array();
    while ($row = $db->fetch_record($rs))
        $standings[] = $row['member_name'] . ':' . intval($row['dkp']);
    
    $db->free_result($rs);
    
    return implode(':', $standings);
}

$tpl->assign_vars(array(
        'ONLOAD' => ' onload="javascript:document.getElementById(\'export\').focus();document.getElementById(\'export\').select();"',
        'STANDINGS_EXPORT' => generateStandings() 
));

$eqdkp->set_vars(array(
        'page_title' => page_title("Export String for OGDKP WoW AddOn"),
        'template_path' => 'plugins/ogdkp/templates/',
        'template_file' => 'export.html',
        'display' => true 
));