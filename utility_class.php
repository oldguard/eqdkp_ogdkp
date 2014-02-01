<?php
/******************************
 * Old Guard EQdkp Plugin
 * Copyright 2013-2014
 * Licensed under the GNU GPL.  See COPYING for full terms.
 ******************************/

if (!defined('EQDKP_INC'))
{
    header('HTTP/1.0 404 Not Found');
    exit();
}

// Class for utility methods used in multiple files
class Utility
{
    // Borrowed from addiadj.php
    static function add_new_adjustment($member_name, $time, $reason, $value, $group_key, $user)
    {
        global $db;
        
        // Add the adjustment to the member

        // This is different than the SQL copied from addiadj.php -- it creates the user if they don't already exist
        $sql = "INSERT INTO __members (`member_name`, `member_adjustment`)
                VALUES ('" . $db->escape($member_name) . "','" . $db->escape($value) . "')
                ON DUPLICATE KEY UPDATE `member_adjustment` = `member_adjustment` + " . $db->escape($value);
        
        $db->query($sql);
        unset($sql);
        
        // Add the adjustment to the database
        $query = $db->build_query('INSERT', array(
                'adjustment_value' => $value,
                'adjustment_date' => $time,
                'member_name' => $member_name,
                'adjustment_reason' => $reason,
                'adjustment_group_key' => $group_key,
                'adjustment_added_by' => $user 
        ));
        
        $db->query("INSERT INTO __adjustments {$query}");
        return $db->insert_id();
    }
}
?>