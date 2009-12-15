<?php
/**
 * Plugin Functions
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
class PluginFunctions
{
    /**
     * Look for and run actions at a given plugin hook
     *
     * @param string $hook name of the plugin hook
     * @param string $folder name of plugin folder
     * @param array $parameters mixed values passed from plugin hook
     * @return array | bool
     */
    public function pluginHook($hotaru, $hook = '', $folder = '', $parameters = array(), $exclude = array())
    {
        if (!$hook) { return false; }
        
        $where = '';
        
        if ($folder) {
            $where .= "AND (" . TABLE_PLUGINS . ".plugin_folder = %s) ";
        }

        $hotaru->db->cache_queries = true;    // start using cache

        $sql = "SELECT " . TABLE_PLUGINS . ".plugin_enabled, " . TABLE_PLUGINS . ".plugin_folder, " . TABLE_PLUGINS . ".plugin_class, " . TABLE_PLUGINS . ".plugin_extends, " . TABLE_PLUGINS . ".plugin_type, " . TABLE_PLUGINHOOKS . ".plugin_hook  FROM " . TABLE_PLUGINHOOKS . ", " . TABLE_PLUGINS . " WHERE (" . TABLE_PLUGINHOOKS . ".plugin_hook = %s) AND (" . TABLE_PLUGINS . ".plugin_folder = " . TABLE_PLUGINHOOKS . ".plugin_folder) " . $where . "ORDER BY " . TABLE_PLUGINHOOKS . ".phook_id";

        $plugins = $hotaru->db->get_results($hotaru->db->prepare($sql, $hook, $folder));

        $hotaru->db->cache_queries = false;    // stop using cache

        if (!$plugins) { return false; }

        foreach ($plugins as $plugin)
        {
            if (!$plugin->plugin_enabled) { continue; } // if the plugin isn't active, skip this iteration
            
            if ($plugin->plugin_folder &&  $plugin->plugin_hook && ($plugin->plugin_enabled == 1)
                && !in_array($plugin->plugin_folder, $exclude)) 
            {

                if (!file_exists(PLUGINS . $plugin->plugin_folder . "/" . $plugin->plugin_folder . ".php"))  { continue; }
                
                /*  loop through all the plugins that use this hook. Include any necessary parent classes
                    and skip to th enext iteration if this class has children. */
                    
                foreach ($plugins as $key => $value) {
                    // If this plugin class is a child, include the parent class
                    if ($value->plugin_enabled && $value->plugin_class == $plugin->plugin_extends) {
                        include_once(PLUGINS . $value->plugin_folder . "/" . $value->plugin_folder . ".php");
                    }
                    
                    // If this plugin class has children, skip it because we will use the children instead
                    if ($value->plugin_enabled && $value->plugin_extends == $plugin->plugin_class) { 
                        continue 2; // skip to next iteration of outer foreach loop
                    }
                }
                
                // include this plugin's file (even child classes need the parent class)
                include_once(PLUGINS . $plugin->plugin_folder . "/" . $plugin->plugin_folder . ".php");
                
                $tempPluginObject = new $plugin->plugin_class();        // create a temporary object of the plugin class
                $tempPluginObject->hotaru = $hotaru;                    // assign $hotaru to the object
                $hotaru->pluginFolder = $plugin->plugin_folder;         // assign plugin folder to $hotaru
                
                // call the method that matches this hook
                if (method_exists($tempPluginObject, $hook)) {
                    $rClass = new ReflectionClass($plugin->plugin_class);
                    $rMethod = $rClass->getMethod($hook);
                    // echo $rMethod->class;                            // the method's class
                    // echo get_class($tempPluginObject);               // the object's class
                    $hotaru->getPluginFolderFromClass($rMethod->class); // give Hotaru the right plugin folder name
                    $hotaru->readPlugin();                              // fill Hotaru's plugin properties
                    $result = $tempPluginObject->$hook($parameters);
                } else {
                    $hotaru->readPlugin();                              // fill Hotaru's plugin properties
                    $result = $hotaru->$hook($parameters);              // fall back on default function in Hotaru.php
                }
                
                if ($result) {
                    $return_array[$plugin->plugin_class . "_" . $hook] = $result; // name the result Class + hook name
                }
            }
        }

        if (isset($return_array))
        {
            // return an array of return values from each function, 
            // e.g. $return_array['usr_users'] = something
            return $return_array;
        } 

        return false;
    }
    
    
    /**
     * Get number of active plugins
     *
     * @return int|false
     */
    public function numActivePlugins($db)
    {
        $enabled = $db->get_var($db->prepare("SELECT count(*) FROM " . TABLE_PLUGINS . " WHERE plugin_enabled = %d", 1));
        if ($enabled > 0) { return $enabled; } else { return false; }
    }
    
    
    /**
     * Get a plugin's folder from its class name
     *
     * This is called from the pluginHook function. It looks like overkill, but all the details
     * get stored in memory and are used by other functions via readPost() below.
     *
     * @param string $class plugin class name
     * @return string|false
     */
    public function getPluginFolderFromClass($hotaru, $class = "")
    {    
        if (!$hotaru->allPluginDetails) { //not in memory
            $this->getAllPluginDetails($hotaru->db); // get from database
        }
        
        if (!$hotaru->allPluginDetails) { 
            return false; // no plugin deatils for this plugin found anywhere
        }
        
        // get plugin details from memory
        foreach ($hotaru->allPluginDetails as $item => $key) {
            if ($key->plugin_class == $class) {
                return $key->plugin_folder;
            }
        }
        
        return false;
    }
    
    
    /**
     * Get a single plugin's details for Hotaru
     *
     * @param string $folder - plugin folder name, else $hotaru->pluginFolder is used
     */
    public function readPlugin($hotaru, $folder = '')
    {
        if (!$folder) { $folder = $hotaru->pluginFolder; } 
        
        if (!$hotaru->allPluginDetails) { //not in memory
            $this->getAllPluginDetails($hotaru->db); // get from database
        }
        
        if (!$hotaru->allPluginDetails) { 
            return false; // no plugin basics for this plugin found anywhere
        }
        
        // get plugin basics from memory
        foreach ($hotaru->allPluginDetails as $item => $key) {
            if ($key->plugin_folder == $hotaru->pluginFolder) {
                $hotaru->pluginId             = $key->plugin_id;        // plugin id
                $hotaru->pluginEnabled        = $key->plugin_enabled;   // activate (1), inactive (0)
                $hotaru->pluginName           = $key->plugin_name;      // plugin proper name
                $hotaru->pluginClass          = $key->plugin_class;     // plugin class name
                $hotaru->pluginExtends        = $key->plugin_extends;   // plugin class parent
                $hotaru->pluginType           = $key->plugin_type;      // plugin class type e.g. "avatar"
                $hotaru->pluginDesc           = $key->plugin_desc;      // plugin description
                $hotaru->pluginVersion        = $key->plugin_version;   // plugin version number
                $hotaru->pluginOrder          = $key->plugin_order;     // plugin order number
                $hotaru->pluginAuthor         = $key->plugin_author;    // plugin author
                $hotaru->pluginAuthorUrl      = $key->plugin_authorurl; // plugin author's website
                
                break;  // done what we need to do so break out of the loop...
            }
        }
        
        return true;
    }
    
    
    /**
     * Store all plugin details for ALL PLUGINS info in memory. This is a single query
     * per page load. Every thing else then draws what it needs from memory.
     */
    public function getAllPluginDetails($db)
    {
        $sql = "SELECT * FROM " . TABLE_PLUGINS;
        $hotaru->allPluginDetails = $db->get_results($db->prepare($sql));
    }
    
    
    /**
     * Determines if a plugin is enabled or not
     *
     * @param object $hotaru
     * @param string $folder plugin folder name
     * @return string
     */
    public function isActive($hotaru, $folder = '')
    {
        if (!$folder) { $folder = $hotaru->pluginFolder; } 
        
        $sql = "SELECT plugin_enabled FROM " . TABLE_PLUGINS . " WHERE plugin_folder = %s";
        $status = $hotaru->db->get_var($hotaru->db->prepare($sql, $folder));
        
        if ($status) { return true; } else { return false; }
    }
}
?>