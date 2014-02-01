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

class OGDKP_RollbackDecay extends EQdkp_Admin
{
    function OGDKP_RollbackDecay()
    {
        parent::eqdkp_admin();
        
        $this->assoc_buttons(array(
                'rollback' => array(
                        'name' => 'rollback',
                        'process' => 'rollback',
                        'check' => 'a_indivadj_del' 
                ),
                'form' => array(
                        'name' => '',
                        'process' => 'display_form',
                        'check' => 'a_indivadj_del' 
                ) 
        ));
    }
    function display_form()
    {
        global $eqdkp, $db, $tpl, $user;
        
        $rs = $db->query('SELECT * FROM __ogdkp_decay ORDER BY date DESC');
        
        $decayCount = 0;
        while ($row = $db->fetch_record($rs))
        {
            $tpl->assign_block_vars('decay_row', array(
                    'ROW_CLASS' => $eqdkp->switch_row_class(),
                    'ID' => $row['decay_id'],
                    'DATE' => date($user->style['date_notime_short'], $row['date']),
                    'PERCENTAGE' => $row['percentage'],
                    'REASON' => $row['reason'],
                    'APPLIED_BY' => $row['applied_by'] 
            ));
            
            $decayCount++;
        }
        
        $db->free_result($rs);
        
        $tpl->assign_vars(array(
                'F_DECAY_ROLLBACK' => $_SERVER['SCRIPT_NAME'],
                'L_ROLLBACK' => 'Rollback Selected' 
        ));
        
        $eqdkp->set_vars(array(
                'page_title' => page_title("Mass Decay History"),
                'template_path' => 'plugins/ogdkp/templates/',
                'template_file' => 'listdecay.html',
                'display' => true 
        ));
    }
    function rollback()
    {
        global $db, $in, $user;
        
        $selectedIds = $in->getArray('selected', 'int');
        
        $successMessage = "";
        
        for($i = 0; $i < count($selectedIds); $i++)
            $selectedIds[$i] = $db->escape($selectedIds[$i]);
        
        $imploded = implode(',', $selectedIds);
        
        $db->sql_transaction('begin');
        
        // Load all the decays we're rolling back from the database to ensure they're in the proper order.
        $sql = "SELECT decay_id, reason, percentage, date
                FROM __ogdkp_decay
                WHERE decay_id IN ($imploded)
                ORDER BY date DESC, decay_id DESC";
        $decayRs = $db->query($sql);
        
        while ($row = $db->fetch_record($decayRs))
        {
            $id = $row['decay_id'];
            $reason = $row['reason'];
            $percentage = (integer) $row['percentage'];
            $date = (integer) $row['date'];
            
            // Make sure there are no decays after this one
            $rs = $db->query("SELECT MAX(date) AS maxDate FROM __ogdkp_decay");
            $row = $db->fetch_record($rs);
            $maxDate = (integer) $row['maxDate'];
            
            $db->free_result($rs);
            
            if ($maxDate > $date)
            {
                $message = sprintf("There was a problem removing the mass decay of %d%% recorded on %s. " 
                        . "Newer decay events exist; decays must be removed sequentially from newest to oldest.", 
                        $percentage, date($user->style['date_time'], $date));
                
                $db->sql_transaction('rollback');
                $this->admin_die($message);
            }
            
            // Get the names of the members we're rolling back
            $sql = "SELECT a.member_name
                    FROM __adjustments a
                    INNER JOIN __ogdkp_decay_adj_map m
                    ON a.adjustment_id = m.adjustment_id
                    INNER JOIN __ogdkp_decay t
                    ON m.decay_id = t.decay_id
                    WHERE t.decay_id = '$id'";
            
            $rs = $db->query($sql);
            $members = array();
            
            while ($row = $db->fetch_record($rs))
            {
                $members[] = $row['member_name'];
            }
            sort($members);
            
            $db->free_result($rs);
            
            // Refund the DKP
            $sql = "UPDATE __members m
                    INNER JOIN __adjustments a
                    ON m.member_name = a.member_name
                    INNER JOIN __ogdkp_decay_adj_map map
                    ON a.adjustment_id = map.adjustment_id
                    INNER JOIN __ogdkp_decay t
                    ON map.decay_id = t.decay_id
                    SET m.member_adjustment = m.member_adjustment - a.adjustment_value
                    WHERE t.decay_id = '$id'";
            
            $db->query($sql);
            
            // Delete the adjustment records.
            $sql = "DELETE a
                    FROM __adjustments AS a
                    INNER JOIN __ogdkp_decay_adj_map AS m
                    WHERE a.adjustment_id = m.adjustment_id
                    AND m.decay_id = '$id'";
            
            $db->query($sql);
            
            // Delete the decay record. InnoDB foreign key cascading will delete the map table entries.
            $sql = "DELETE FROM __ogdkp_decay WHERE decay_id = '$id'";
            $db->query($sql);
            
            // Log the rollback
            $log_action = array(
                    'header' => 'Mass Decay Rollback',
                    '{L_ADJUSTMENT}' => $percentage . "%",
                    '{L_REASON}' => $reason,
                    '{L_MEMBERS}' => implode(', ', $members),
                    '{L_ADDED_BY}' => $this->admin_user 
            );
            $this->log_insert(array(
                    'log_type' => $log_action['header'],
                    'log_action' => $log_action 
            ));
            
            $successMessage .= sprintf("Successfully removed the mass decay of %d%% that was applied on %s.<br/>", 
                    $percentage, date($user->style['date_time'], $date));
        }
        
        $db->free_result($decayRs);
        
        $db->sql_transaction('commit');
        
        $this->admin_die($successMessage);
    }
    
    function error_check()
    {
        global $in;
        
        if ($in->get('rollback', false))
        {
            $selected = $in->getArray('selected', 'int');
            if (count($selected) == 0)
                $this->fv->errors['selected'] = 'You must make a selection.';
        }
        
        return $this->fv->is_error();
    }
}

$rollback = new OGDKP_RollbackDecay();
$rollback->process();
?>