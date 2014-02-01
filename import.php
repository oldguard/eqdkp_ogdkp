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

// Class file for OGDKP_Import
class OGDKP_Import extends EQdkp_Admin
{
    function OGDKP_Import()
    {
        parent::eqdkp_admin();
        
        $this->assoc_buttons(array(
                'parse' => array(
                        'name' => 'parse',
                        'process' => 'summarize',
                        'check' => 'a_raid_add' 
                ),
                'form' => array(
                        'name' => '',
                        'process' => 'display_form',
                        'check' => 'a_raid_add' 
                ),
                'persist' => array(
                        'name' => 'persist',
                        'process' => 'persist_raid',
                        'check' => 'a_raid_add' 
                ),
                'summary' => array(
                        'name' => 'summarize',
                        'process' => 'summarize',
                        'check' => 'a_raid_add' 
                ) 
        ));
    }
    
    // Shows the import form
    function display_form()
    {
        global $eqdkp, $tpl;
        
        $tpl->assign_vars(array(
                'ONLOAD' => ' onload="javascript:document.getElementById(\'xml\').focus();"',
                'F_IMPORT' => $_SERVER['SCRIPT_NAME'] 
        ));
        
        $eqdkp->set_vars(array(
                'page_title' => page_title("Import XML from OGDKP AddOn"),
                'template_path' => 'plugins/ogdkp/templates/',
                'template_file' => 'import.html',
                'display' => true 
        ));
    }
    
    // Parse the XML document, then summarize the raid.
    function summarize()
    {
        global $eqdkp, $tpl, $in, $user, $db;
        
        $raid = $this->_parse_xml();
        
        $attendees = array();
        
        foreach ($raid->attendees->children() as $tag => $attendee)
            $attendees[] = (string) $attendee->name;
        
        // Build the attendee grid, balancing the members between columns to create semi-equal rows
        if (count($attendees) > 0)
        {
            $rows = ceil(sizeof($attendees) / $user->style['attendees_columns']);
            
            for($i = 0; $i < $rows; $i++)
            {
                $block_vars = array();
                for($j = 0; $j < $user->style['attendees_columns']; $j++)
                {
                    $offset = ($i + ($rows * $j));
                    $attendee = (isset($attendees[$offset])) ? $attendees[$offset] : NULL;
                    
                    if (!is_null($attendee))
                    {
                        $block_vars += array(
                                'COLUMN' . $j . '_NAME' => $attendee 
                        );
                    }
                    else
                    {
                        $block_vars += array(
                                'COLUMN' . $j . '_NAME' => '' 
                        );
                    }
                    
                    // Are we showing this column?
                    $s_column = 's_column' . $j;
                    ${ $s_column } = true;
                }
                $tpl->assign_block_vars('attendees_row', $block_vars);
            }
            $column_width = floor(100 / $user->style['attendees_columns']);
        }
        
        // Adjustments
        $adjustments = array();
        if ((boolean) $raid->adjustments)
        {
            foreach ($raid->adjustments->adjustment as $adjustment)
            {
                $player = (string) $adjustment->name;
                $note = (string) $adjustment->note;
                $value = (integer) $adjustment->value;
                
                if ($value > 0)
                    $adjClass = 'positive';
                elseif ($value < 0)
                    $adjClass = 'negative';
                else
                    $adjClass = 'neutral';
                
                $tpl->assign_block_vars('adjustments_row', array(
                        'PLAYER' => $player,
                        'REASON' => $note,
                        'VALUE' => $value,
                        'ROW_CLASS' => $eqdkp->switch_row_class(),
                        'ADJ_CLASS' => $adjClass 
                ));
            }
        }
        
        // Drops
        $lootCount = 0;
        foreach ($raid->attendees->attendee as $attendee)
        {
            foreach ($attendee->loot as $loot)
            {
                $tpl->assign_block_vars('items_row', array(
                        'ROW_CLASS' => $eqdkp->switch_row_class(),
                        'BUYER' => sanitize((string) $attendee->name),
                        'NAME' => (string) $loot->itemName,
                        'VALUE' => (integer) $loot->cost 
                ));
                
                $lootCount++;
            }
        }
        
        // Get the name of the bank character
        $sql = "SELECT value FROM __ogdkp_settings WHERE name = 'bank_character'";
        $rs = $db->query($sql);
        $row = $db->fetch_record($rs);
        
        $banker = $row['value'];
        
        $db->free_result($rs);
        
        foreach ($raid->bank->loot as $loot)
        {
            $tpl->assign_block_vars('items_row', array(
                    'ROW_CLASS' => $eqdkp->switch_row_class(),
                    'BUYER' => sanitize((string) $banker),
                    'NAME' => (string) $loot->itemName,
                    'VALUE' => (integer) $loot->cost 
            ));
            
            $lootCount++;
        }
        
        $tpl->assign_vars(array(
                'L_MEMBERS_PRESENT_AT' => sprintf($user->lang['members_present_at'], sanitize((string) $raid->name), date($user->style['date_notime_long'], (integer) $raid->timestamp)),
                'L_NOTE' => $user->lang['note'],
                'L_VALUE' => $user->lang['value'],
                'L_DROPS' => $user->lang['drops'],
                'L_BUYER' => $user->lang['buyer'],
                'L_ITEM' => $user->lang['item'],
                'L_SPENT' => $user->lang['spent'],
                'L_ATTENDEES' => $user->lang['attendees'],
                'L_ADD_RAID' => $user->lang['add_raid'],
                
                'ADJUSTMENTS' => (boolean) $raid->adjustments,
                
                'S_COLUMN0' => isset($s_column0),
                'S_COLUMN1' => isset($s_column1),
                'S_COLUMN2' => isset($s_column2),
                'S_COLUMN3' => isset($s_column3),
                'S_COLUMN4' => isset($s_column4),
                'S_COLUMN5' => isset($s_column5),
                'S_COLUMN6' => isset($s_column6),
                'S_COLUMN7' => isset($s_column7),
                'S_COLUMN8' => isset($s_column8),
                'S_COLUMN9' => isset($s_column9),
                
                'COLUMN_WIDTH' => (isset($column_width)) ? $column_width : 0,
                'COLSPAN' => $user->style['attendees_columns'],
                
                'RAID_NOTE' => sanitize($raid->note),
                'DKP_NAME' => $eqdkp->config['dkp_name'],
                'RAID_VALUE' => number_format((int) $raid->value, 2),
                'ATTENDEES_FOOTCOUNT' => sprintf($user->lang['viewraid_attendees_footcount'], count($raid->attendees->children())),
                'ADJUSTMENT_FOOTCOUNT' => sprintf($user->lang['viewmember_adjustment_footcount'], (boolean) $raid->adjustments ? count($raid->adjustments->children()) : 0),
                'ITEM_FOOTCOUNT' => sprintf($user->lang['viewraid_drops_footcount'], $lootCount),
                'F_IMPORT' => $_SERVER['SCRIPT_NAME'],
                'XML_DATA' => htmlentities($_POST['xml']) 
        ));
        
        $eqdkp->set_vars(array(
                'page_title' => page_title("Raid Summary"),
                'template_path' => 'plugins/ogdkp/templates/',
                'template_file' => 'summary.html',
                'display' => true 
        ));
    }
    
    // Persists the raid to the DB
    function persist_raid()
    {
        global $db, $pm, $user, $eqdkp;
        
        $raid = $this->_parse_xml();
        
        $db->sql_transaction('begin');
        
        // Escape
        
        // Create an event (if necessary)
        $sql = sprintf("SELECT COUNT(*) AS numEvents FROM __events WHERE event_name = '%s'", $db->escape((string) $raid->name));
        $rs = $db->query($sql);
        $row = $db->fetch_record($rs);
        
        if ((integer) $row['numEvents'] == 0)
            $this->_add_event((string) $raid->name);
        
        $db->free_result($rs);
        
        // Insert the raid
        $db->query("INSERT INTO __raids :params", array(
                'raid_name' => (string) $raid->name,
                'raid_date' => (integer) $raid->timestamp,
                'raid_note' => (string) $raid->note,
                'raid_value' => (integer) $raid->value,
                'raid_added_by' => $this->admin_user 
        ));
        
        $raidId = $db->insert_id();
        $timestamp = (integer) $raid->timestamp;
        
        // Adds attendees to __raid_attendees; adds/updates member entries as necessary
        $this->_process_attendees($raid, $raidId);
        
        // Update firstraid / lastraid / raidcount
        $this->_update_member_cache($raid);
        
        // Call plugin add hooks
        $pm->do_hooks('/admin/addraid.php?action=add');
        
        // Log the raid add event
        $log_action = array(
                'header' => '{L_ACTION_RAID_ADDED}',
                'id' => $raidId,
                '{L_EVENT}' => (string) $raid->name,
                '{L_ATTENDEES}' => implode(', ', $this->_build_attendee_array($raid)),
                '{L_NOTE}' => (string) $raid->note,
                '{L_VALUE}' => (float) $raid->value,
                '{L_ADDED_BY}' => $this->admin_user 
        );
        $this->log_insert(array(
                'log_type' => $log_action['header'],
                'log_action' => $log_action 
        ));
        
        $success_message = sprintf($user->lang['admin_add_raid_success'], date($user->style['date_notime_short'], $this->time), sanitize((string) $raid->name)) . '<br />';
        
        // Process any adjustments
        $adjustments = $this->_group_adjustments($raid);
        foreach ($adjustments as $adj)
        {
            $group_key = $this->gen_group_key($timestamp, $adj['note'], $adj['value']);
            foreach ($adj['players'] as $player)
            {
                Utility::add_new_adjustment($player, $timestamp, $adj['note'], $adj['value'], $group_key, $this->admin_user);
            }
            
            // Log the adjustment
            $log_action = array(
                    'header' => '{L_ACTION_INDIVADJ_ADDED}',
                    '{L_ADJUSTMENT}' => $adj['value'],
                    '{L_REASON}' => $adj['note'],
                    '{L_MEMBERS}' => implode(', ', $adj['players']),
                    '{L_ADDED_BY}' => $this->admin_user 
            );
            $this->log_insert(array(
                    'log_type' => $log_action['header'],
                    'log_action' => $log_action 
            ));
            
            $success_message .= sprintf($user->lang['admin_add_iadj_success'], $eqdkp->config['dkp_name'], sanitize($adj['value']), sanitize(implode(', ', $adj['players']))) . "<br/>";
        }
        
        // Store any items looted during the raid
        foreach ($raid->attendees->children() as $attendee)
        {
            if ((boolean) $attendee->loot)
            {
                for($i = 0; $i < count($attendee->loot); $i++)
                {
                    $loot = $attendee->loot[$i];
                    $itemName = (string) $loot->itemName;
                    $cost = (integer) $loot->cost;
                    $raider = (string) $attendee->name;
                    
                    // Charge the raider for the item
                    $sql = sprintf("UPDATE __members
                                    SET `member_spent` = `member_spent` + '%d'
                                    WHERE `member_name` = '%s'
                                    ", $db->escape($cost), $db->escape($raider));
                    
                    $db->query($sql);
                    
                    $success_message .= $this->_add_item($itemName, $raider, $cost, $raidId, $timestamp);
                }
            }
        }
        
        // Get the name of the bank character
        $sql = "SELECT value FROM __ogdkp_settings WHERE name = 'bank_character'";
        $rs = $db->query($sql);
        $row = $db->fetch_record($rs);
        
        $banker = $row['value'];
        
        $db->free_result($rs);
        
        // Store any banked/DE'd items
        foreach ($raid->bank->children() as $loot)
        {
            $itemName = (string) $loot->itemName;
            $success_message .= $this->_add_item($itemName, $banker, 0, $raidId, $timestamp);
        }
        
        $db->sql_transaction('commit');
        
        $links = array(
                $user->lang['list_raids'] => raid_path(),
                $user->lang['list_indivadj'] => iadjustment_path(),
                $user->lang['list_members'] => member_path() 
        );
        $this->admin_die($success_message, $links);
    }
    function _add_event($name)
    {
        global $db, $pm;
        
        // Insert event
        $query = $db->build_query('INSERT', array(
                'event_name' => $name,
                'event_value' => 0.00,
                'event_added_by' => $this->admin_user 
        ));
        
        $db->query("INSERT INTO __events {$query}");
        $this_event_id = $db->insert_id();
        
        // Call plugin update hooks
        $pm->do_hooks('/admin/addevent.php?action=add');
        
        // Logging
        $log_action = array(
                'header' => '{L_ACTION_EVENT_ADDED}',
                'id' => $this_event_id,
                '{L_NAME}' => $name,
                '{L_VALUE}' => 0.00,
                '{L_ADDED_BY}' => $this->admin_user 
        );
        
        $this->log_insert(array(
                'log_type' => $log_action['header'],
                'log_action' => $log_action 
        ));
    }
    function _add_item($itemName, $raider, $cost, $raidId, $timestamp)
    {
        global $db, $user;
        
        $groupKey = $this->gen_group_key($itemName, $timestamp, $raidId);
        
        // Add the item to the items table
        $params = array(
                'item_name' => $itemName,
                'item_buyer' => $raider,
                'raid_id' => $raidId,
                'item_value' => $cost,
                'item_date' => $timestamp,
                'item_group_key' => $groupKey,
                'item_added_by' => $this->admin_user 
        );
        
        $db->query("INSERT INTO __items :params", $params);
        
        $logAction = array(
                'header' => '{L_ACTION_ITEM_ADDED}',
                '{L_NAME}' => $itemName,
                '{L_BUYERS}' => $raider,
                '{L_RAID_ID}' => $raidId,
                '{L_VALUE}' => $cost,
                '{L_ADDED_BY}' => $this->admin_user 
        );
        $this->log_insert(array(
                'log_type' => $logAction['header'],
                'log_action' => $logAction 
        ));
        
        return sprintf($user->lang['admin_add_item_success'], sanitize($itemName), sanitize($raider), sanitize($cost)) . "<br />";
    }
    
    // Validates the XML, then converts it to a SimpleXMLElement object
    function _parse_xml()
    {
        global $in;
        
        $doc = new DOMDocument();
        $doc->loadXML($in->get('xml'));
        
        if (!$doc->schemaValidate("import.xsd"))
        {
            $error = libxml_get_last_error();
            message_die("XML Error: " . $error->message);
        }
        
        $xml = simplexml_import_dom($doc);
        return $xml;
    }
    function _build_attendee_array($raid)
    {
        $attendeeArray = array();
        foreach ($raid->attendees->children() as $tag => $attendee)
            $attendeeArray[] = (string) $attendee->name;
        
        return $attendeeArray;
    }
    
    // A simple function to group identical adjuments together
    function _group_adjustments($raid)
    {
        $groups = array();
        if ((boolean) $raid->adjustments)
        {
            foreach ($raid->adjustments->children() as $adjustment)
            {
                $name = (string) $adjustment->name;
                $note = (string) $adjustment->note;
                $value = (integer) $adjustment->value;
                
                // Create a simple hash key
                $key = strtolower(trim($note)) . (string) $value;
                
                if (!isset($groups[$key]))
                    $groups[$key] = array(
                            'note' => $note,
                            'value' => $value,
                            'players' => array() 
                    );
                
                $groups[$key]['players'][$name] = true;
            }
        }
        
        // Turn our hash back into a useful array
        $ret = array();
        
        foreach ($groups as $k => $v)
        {
            $v['players'] = array_keys($v['players']);
            $ret[] = $v;
        }
        
        return $ret;
    }
    
    // Borrowed from addraid.php
    /**
     * For each attendee on a raid, add a record in __raid_attendees and add or
     * update their __members row
     *
     * @param string $att_array
     *            Array of attendees as prepared by {@link _prepare_attendees}
     * @param string $raid_id
     *            Raid ID
     * @param string $raid_value
     *            Raid value to give each attendee
     * @return void
     * @access private
     */
    function _process_attendees($raid, $raid_id)
    {
        global $db, $user;
        
        $raid_id = intval($raid_id);
        $raid_value = floatval((float) $raid->value);
        
        $att_array = $this->_build_attendee_array($raid);
        
        // Gather data about our attendees that we'll need to rebuild their records
        // This has to be done because REPLACE INTO deletes the record
        // before re-inserting it, meaning we lose the member's data and the
        // default database values get used (BAD!)
        $att_data = array();
        $sql = "SELECT *
                FROM __members
                WHERE (`member_name` IN ('" . $db->escape("','", $att_array) . "'))
                ORDER BY member_name";
        $result = $db->query($sql);
        while ($row = $db->fetch_record($result))
        {
            $att_data[$row['member_name']] = $row;
        }
        $db->free_result($result);
        
        foreach ($att_array as $attendee)
        {
            // Add each attendee to the attendees table for this raid
            $sql = "REPLACE INTO __raid_attendees (raid_id, member_name)
			VALUES ('{$raid_id}', '" . $db->escape($attendee) . "')";
            $db->query($sql);
            
            // Set the bare-minimum values for a new member
            $row = array(
                    'member_name' => $attendee 
            );
            
            // Update existing member data
            if (isset($att_data[$attendee]))
            {
                // Inject our saved data into our row that gets updated
                $row = array_merge($row, $att_data[$attendee]);
                
                // Some of our values need to be updated, so do that!
                $row['member_earned'] = floatval($row['member_earned']) + $raid_value;
                
                $db->query("UPDATE __members SET :params WHERE (`member_name` = '" . $db->escape($attendee) . "')", $row);
            }
            // Add new member
            else
            {
                $row['member_earned'] = $raid_value;
                
                $db->query("INSERT INTO __members :params", $row);
            }
        }
    }
    
    // Borrowed from addraid.php
    /**
     * Recalculates and updates the first and last raids and raid counts for each
     * member in $att_array
     *
     * @param string $att_array
     *            Array of raid attendees
     * @return void
     * @access private
     */
    function _update_member_cache($raid)
    {
        global $db;
        
        $att_array = $this->_build_attendee_array($raid);
        
        $sql = "SELECT m.member_name, MIN(r.raid_date) AS firstraid,
                MAX(r.raid_date) AS lastraid, COUNT(r.raid_id) AS raidcount
                FROM __members AS m
                LEFT JOIN __raid_attendees AS ra ON m.member_name = ra.member_name
                LEFT JOIN __raids AS r on ra.raid_id = r.raid_id
                WHERE (m.`member_name` IN ('" . $db->escape("','", $att_array) . "'))
                GROUP BY m.member_name";
        
        $result = $db->query($sql);

        while ($row = $db->fetch_record($result))
        {
            $db->query("UPDATE __members SET :params WHERE (`member_name` = '" . $db->escape($row['member_name']) . "')", array(
                    'member_firstraid' => $row['firstraid'],
                    'member_lastraid' => $row['lastraid'],
                    'member_raidcount' => $row['raidcount'] 
            ));
        }
        $db->free_result($result);
    }
    
    // Go through the member list to see if there are any new toon names contained in the import. Useful as a visual
    // aid to prevent adding alts accidentally.
    // TODO Implement this
    function _find_missing_members($attendees)
    {
        global $db;
        
        $sql = "SELECT member_name
                FROM __members
                WHERE `member_name` IN ('" . $db->escape("','", $attendees) . "')";
        
        $missing = array_flip($attendees);
        
        $rs = $db->query($sql);
        
        while ($row = $db->fetch_record($rs))
            unset($missing[$row['member_name']]);
        
        $db->free_result($rs);
        
        return array_keys($missing);
    }
}

$import = new OGDKP_Import();
$import->process();
?>