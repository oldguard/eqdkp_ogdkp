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
require_once ('utility_class.php');
class OGDKP_Decay extends EQdkp_Admin
{
    function OGDKP_Decay()
    {
        parent::eqdkp_admin();
        
        $this->assoc_buttons(array(
                'decay' => array(
                        'name' => 'decay',
                        'process' => 'decay',
                        'check' => 'a_indivadj_add' 
                ),
                'form' => array(
                        'name' => '',
                        'process' => 'display_form',
                        'check' => 'a_indivadj_add' 
                ) 
        ));
    }
    function display_form()
    {
        global $eqdkp, $tpl;
        
        $tpl->assign_vars(array(
                'ONLOAD' => ' onload="javascript:document.getElementById(\'decay\').focus();"',
                'F_DECAY' => $_SERVER['SCRIPT_NAME'] 
        ));
        
        $eqdkp->set_vars(array(
                'page_title' => page_title("Apply Percentage Decay to DKP Standings"),
                'template_path' => 'plugins/ogdkp/templates/',
                'template_file' => 'decay.html',
                'display' => true 
        ));
    }
    function decay()
    {
        global $eqdkp, $db, $in, $user;
        
        $decay = intval($in->get('percentage'));
        $reason = $in->get('reason');
        
        $successMessage = '';
        
        // Create an entry in the decay tracking table
        $db->sql_transaction('begin');
        $db->query('INSERT INTO __ogdkp_decay :params', array(
                'date' => $this->time,
                'reason' => $reason,
                'percentage' => $decay,
                'applied_by' => $this->admin_user 
        ));
        
        $decayId = $db->insert_id();
        
        $sql = "SELECT member_name, member_earned - member_spent + member_adjustment as dkp
				FROM __members
				HAVING dkp > 1
				";
        
        $rs = $db->query($sql);
        
        $players = array();
        while ($row = $db->fetch_record($rs))
        {
            $player = $row['member_name'];
            $players[] = $player;
            
            // Calculate how much to deduct from the player's total, rounding down
            $current = $row['dkp'];
            $adjustment = -(floor($current * $decay / 100));
            
            // Make sure we deduct at least one point
            if ($adjustment == 0)
                $adjustment = -1;
            
            $groupKey = $this->gen_group_key($this->time, $decay, $reason);
            $adjId = Utility::add_new_adjustment($player, $this->time, $reason, $adjustment, $groupKey, $this->admin_user);
            
            $db->query('INSERT INTO __ogdkp_decay_adj_map :params', array(
                    'decay_id' => $decayId,
                    'adjustment_id' => $adjId 
            ));
            
            $successMessage .= sprintf($user->lang['admin_update_iadj_success'], $eqdkp->config['dkp_name'], sanitize($adjustment), sanitize($player)) . "<br/>";
        }
        
        $db->free_result($rs);
        
        $db->sql_transaction('commit');
        
        sort($players);
        
        // Log the mass adjustment
        $log_action = array(
                'header' => 'Mass Decay Applied',
                '{L_ADJUSTMENT}' => $decay . "%",
                '{L_REASON}' => $reason,
                '{L_MEMBERS}' => implode(', ', $players),
                '{L_ADDED_BY}' => $this->admin_user 
        );
        $this->log_insert(array(
                'log_type' => $log_action['header'],
                'log_action' => $log_action 
        ));
        
        $this->admin_die($successMessage);
    }
}

$decay = new OGDKP_Decay();
$decay->process();
?>