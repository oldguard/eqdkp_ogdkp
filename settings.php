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

class OGDKP_Settings extends EQdkp_Admin
{
    function OGDKP_Settings()
    {
        parent::eqdkp_admin();
        
        $this->assoc_buttons(array(
                'form' => array(
                        'name' => '',
                        'process' => 'display_form',
                        'check' => 'a_members_man' 
                ),
                'save' => array(
                        'name' => 'save',
                        'process' => 'save',
                        'check' => 'a_members_man' 
                ) 
        ));
    }

    function display_form()
    {
        global $eqdkp, $tpl, $db;
        
        $sql = "SELECT value FROM __ogdkp_settings WHERE name = 'bank_character'";
        $rs = $db->query($sql);
        $row = $db->fetch_record($rs);
        
        if ($row)
            $banker = $row['value'];
        else
            $banker = DEFAULT_BANK_CHARACTER;
        
        $db->free_result($rs);
        
        $tpl->assign_vars(array(
                'F_SETTINGS' => $_SERVER['SCRIPT_NAME'],
                'BANK_CHARACTER' => $banker,
                'FV_BANK_CHARACTER' => $this->fv->generate_error('bank_character') 
        ));
        
        $eqdkp->set_vars(array(
                'page_title' => page_title("OGDKP Plugin Settings"),
                'template_path' => 'plugins/ogdkp/templates/',
                'template_file' => 'settings.html',
                'display' => true 
        ));
    }

    function save()
    {
        // TODO Make sure the bank character exists as a member
        global $in, $db;
        
        $settings = array(
                'bank_character' => $in->get('bank_character') 
        );
        
        foreach ($settings as $name => $value)
        {
            $sql = sprintf("UPDATE __ogdkp_settings SET value = '%s' WHERE name = '$name'", $db->escape($value));
            $db->query($sql);
        }
        
        $this->admin_die("Successfully updated the plugin settings.");
    }

    function error_check()
    {
        global $in;
        
        if ($in->get('save', false))
        {
            $this->fv->is_filled('bank_character', 'You must specify a bank character.');
        }
        
        return $this->fv->is_error();
    }
}

$settings = new OGDKP_Settings();
$settings->process();
?>