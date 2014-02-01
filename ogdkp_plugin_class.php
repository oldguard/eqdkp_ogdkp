<?php
/******************************
 * Old Guard EQdkp Plugin
 * Copyright 2013-2014
 * Licensed under the GNU GPL.  See COPYING for full terms.
 ******************************/

// Prevent direct access
if (!defined('EQDKP_INC'))
{
    header('HTTP/1.0 404 Not Found');
    exit();
}

global $table_prefix;
define('TABLE_SETTINGS', $table_prefix . 'ogdkp_settings');
define('TABLE_DECAY_TRACKING', $table_prefix . 'ogdkp_decay');
define('TABLE_DECAY_ADJ_MAP', $table_prefix . 'ogdkp_decay_adj_map');

define('VERSION', '1.0.0');

class OGDKP_Plugin_Class extends EQdkp_Plugin
{
    function OGDKP_Plugin_Class($pm)
    {
        global $eqdkp_root_path, $user, $SID;
        
        // Add the plugin to EQdkp
        $this->eqdkp_plugin($pm);
        
        // Configure plugin details
        $this->add_data(array(
                'name' => 'OGDKP',
                'code' => 'ogdkp',
                'path' => 'ogdkp',
                'contact' => 'Treyen',
                'template_path' => 'plugins/ogdkp/templates/',
                'version' => VERSION
        ));
        
        // Add menu options to the admin menu
        $this->add_menu('admin_menu', $this->buildMenu());
        
        // Add our custom log actions
        $this->add_log_action("Mass Decay Applied", "Mass Decay Applied");
        $this->add_log_action("Mass Decay Rollback", "Mass Decay Rollback");
        
        // SQL table creation and teardown follow
        
        // Create the settings table
        $sql = 'CREATE TABLE IF NOT EXISTS ' . TABLE_SETTINGS . '
                (
                    name varchar(50) not null,
                    value varchar(200) not null
                )
                Engine=InnoDB;';
        $this->add_sql(SQL_INSTALL, $sql);
        
        // Configure default values
        $defaults = array(
                'bank_character' => 'Bank/disenchant' 
        );
        
        // Insert the defaults
        foreach ($defaults as $name => $value)
        {
            $sql = "INSERT INTO " . TABLE_SETTINGS . " (name, value) VALUES ('$name', '$value')";
            $this->add_sql(SQL_INSTALL, $sql);
        }
        
        // Create the decay tracking tables during plugin installation
        $sql = 'CREATE TABLE IF NOT EXISTS ' . TABLE_DECAY_TRACKING . '
                (
                    decay_id mediumint unsigned not null auto_increment,
                    date int unsigned not null,
                    percentage tinyint unsigned not null,
                    reason varchar(255),
                    applied_by varchar(30) not null,
                    primary key (decay_id),
                    index (date)
                )
                Engine=InnoDB;';
        $this->add_sql(SQL_INSTALL, $sql);
        
        $sql = 'CREATE TABLE IF NOT EXISTS ' . TABLE_DECAY_ADJ_MAP . '
                (
                    decay_id mediumint unsigned not null,
                    adjustment_id mediumint unsigned not null,
                    index(decay_id, adjustment_id),
                    constraint foreign key (decay_id) references ' . TABLE_DECAY_TRACKING . '(decay_id)
                        on delete cascade on update cascade,
                    constraint foreign key (adjustment_id) references __adjustments(adjustment_id)
                        on delete cascade on update cascade					
                )
                Engine=InnoDB;';
        $this->add_sql(SQL_INSTALL, $sql);
        
        // Drop our tables tables during plugin uninstallation
        $this->add_sql(SQL_UNINSTALL, 'drop table if exists ' . TABLE_SETTINGS . ';');
        $this->add_sql(SQL_UNINSTALL, 'drop table if exists ' . TABLE_DECAY_ADJ_MAP . ';');
        $this->add_sql(SQL_UNINSTALL, 'drop table if exists ' . TABLE_DECAY_TRACKING . ';');
    }
    function buildMenu()
    {
        $path = $this->get_data('path');
        return array(
                'ogdkp' => array(
                        "OGDKP",
                        array(
                                'link' => plugin_path($this->get_data('path'), 'settings.php'),
                                'text' => 'Plugin Settings',
                                'check' => 'a_members_man' 
                        ),
                        array(
                                'link' => plugin_path($this->get_data('path'), 'import.php'),
                                'text' => 'Import from AddOn',
                                'check' => 'a_raid_add' 
                        ),
                        array(
                                'link' => plugin_path($this->get_data('path'), 'export.php'),
                                'text' => 'Export to AddOn',
                                'check' => 'u_member_list' 
                        ),
                        array(
                                'link' => plugin_path($this->get_data('path'), 'decay.php'),
                                'text' => 'Apply DKP Decay',
                                'check' => 'a_indivadj_add' 
                        ),
                        array(
                                'link' => plugin_path($this->get_data('path'), 'listdecay.php'),
                                'text' => 'DKP Decay History',
                                'check' => 'a_indivadj_del' 
                        ) 
                ) 
        );
    }
}