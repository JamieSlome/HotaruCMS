<?php
/**
 * The Widget class contains some useful methods when using widgets
 *
 * PHP version 5
 *
 * LICENSE: Hotaru CMS is free software: you can redistribute it and/or 
 * modify it under the terms of the GNU General Public License as 
 * published by the Free Software Foundation, either version 3 of 
 * the License, or (at your option) any later version. 
 *
 * Hotaru CMS is distributed in the hope that it will be useful, but WITHOUT 
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or 
 * FITNESS FOR A PARTICULAR PURPOSE. 
 *
 * You should have received a copy of the GNU General Public License along 
 * with Hotaru CMS. If not, see http://www.gnu.org/licenses/.
 * 
 * @category  Content Management System
 * @package   HotaruCMS
 * @author    Nick Ramsay <admin@hotarucms.org>
 * @copyright Copyright (c) 2009, Hotaru CMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link      http://www.hotarucms.org/
 */
    
class Widget
{    
    /**
     * Initialize widgets
     */
    public function initializeWidgets($h)
    {
        // Get settings from the database if they exist...
        $widgets_settings = $h->getSerializedSettings('widgets'); 
        
        $widgets = $this->getWidgets($h);
        
        if ($widgets) {
            $count = 1;
            foreach ($widgets as $widget) {
            
                // Assign order number if not already assigned one.
                if (!isset($widgets_settings['widgets'][$widget->widget_function]['order'])) {
                    $widgets_settings['widgets'][$widget->widget_function]['order'] = $count;
                }
                
                // Assign widget number if not already assigned one.
                if (!isset($widgets_settings['widgets'][$widget->widget_function]['block'])) {
                    $widgets_settings['widgets'][$widget->widget_function]['block'] = 1;
                }
                
                // Enable the widget if enabled status is not currently set...
                if (!isset($widgets_settings['widgets'][$widget->widget_function]['enabled'])) {
                    $widgets_settings['widgets'][$widget->widget_function]['enabled'] = true;
                }
                
                // But! Disable it if the plugin for that widget is not currently active.
                if (!$h->isActive($widget->widget_plugin) ) {
                    $widgets_settings['widgets'][$widget->widget_function]['enabled'] = false;
                }

                // Add plugin name, function suffix and arguments to widget_settings:
                $widgets_settings['widgets'][$widget->widget_function]['plugin'] = $widget->widget_plugin;
                $widgets_settings['widgets'][$widget->widget_function]['class'] = $h->getPluginClass($widget->widget_plugin);
                $widgets_settings['widgets'][$widget->widget_function]['function'] = $widget->widget_function;
                $widgets_settings['widgets'][$widget->widget_function]['args'] = $widget->widget_args;

                $count++;
            }
            
            $h->updateSetting('widgets_settings', serialize($widgets_settings), 'widgets');
        }
    }


    /**
     * Add widget
     *
     * @param string $plugin
     * @param string $function
     * @param string $value
     */
    public function addWidget($h, $plugin = '', $function = '', $args = '')
    {
        // Check if it exists so we don't add a duplicate
        $sql = "SELECT * FROM " . DB_PREFIX . "widgets WHERE widget_plugin = %s AND widget_function = %s AND widget_args = %s";
        $results = $h->db->get_results($h->db->prepare($sql, $plugin, $function, $args));
        
        if (!$results) {
            $sql = "INSERT INTO " . DB_PREFIX . "widgets (widget_plugin, widget_function, widget_args, widget_updateby) VALUES(%s, %s, %s, %d)";
            $h->db->query($h->db->prepare($sql, $plugin, $function, $args, $h->currentUser->id));
        }
        
        $h->db->query("OPTIMIZE TABLE " . DB_PREFIX . "widgets");
    }
    
    
    /**
     * Get widgets from widget db table
     *
     * @return array - of widget settings
     */
    public function getWidgets($h)
    {
        $exists = $h->db->table_exists('widgets');
        
        if (!$exists) { return false; }
        
        // Get settings from the database if they exist...
        $sql = "SELECT * FROM " . DB_PREFIX . 'widgets';
        $widgets_settings = $h->db->get_results($h->db->prepare($sql));
        return $widgets_settings;
    }
    

    /**
     * Get widgets from widgets_settings array
     *
     * USAGE: foreach ($widgets as $widget=>$details) 
     * { echo "Name: " . $widget; echo $details['order']; echo $details['args']; } 
     * 
     * @return array - of widgets
     */
    public function getArrayWidgets($h)
    {
        // Get settings from the database if they exist...
        $widgets_settings = $h->getSerializedSettings('widgets'); 
        
        if ($widgets_settings['widgets']) {
            $widgets = $widgets_settings['widgets'];    // associative array
                    
            $widgets = $this->orderWidgets($widgets);    // sorts plugins by "order"
    
            return $widgets;
        }
        return false;
    }
    
    
    /**
     * Delete a widget from the widget db table
     *
     * @param string $function 
     */
    public function deleteWidget($h, $function)
    {
        // Get settings from the database if they exist...
        $sql = "DELETE FROM " . DB_PREFIX . "widgets WHERE widget_function = %s";
        $h->db->query($h->db->prepare($sql, $function));
        
        $h->db->query("OPTIMIZE TABLE " . TABLE_WIDGETS);
    }

    /**
     * Sort the widgets by order number
     *
     * @param array $widgets
     * @return array - sorted widgets
     */
    public function orderWidgets($widgets)
    {
        return sksort($widgets, "order", "int", true);
    }
    

    /**
     * Get last block
     *
     * @param array $widgets
     * @return int the highest block value of all the widgets, i.e. the number of blocks. 
     */
    public function getLastWidgetBlock($widgets)
    {
        if (!$widgets) { return 1; }
        
        $highest = 1;
        foreach ($widgets as $widget => $details) {
            if (isset($details['block']) && ($details['block'] > $highest)) { $highest = $details['block']; }
        }
        return $highest;
    }
    
    
    /**
     * Get plugin name from widget function name
     *
     * @return string
     */
    public function getPluginFromFunction($h, $function)
    {
        // Get settings from the database if they exist...
        $sql = "SELECT widget_plugin FROM " . TABLE_WIDGETS . ' WHERE widget_function = %s';
        $widget_plugin = $h->db->get_var($h->db->prepare($sql, $function));
        return $widget_plugin;
    }
    
}

?>