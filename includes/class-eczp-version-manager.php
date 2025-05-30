<?php
/**
 * Version Manager for Echezona Payments
 *
 * @package Echezona_Payments
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ECZP_Version_Manager
 */
class ECZP_Version_Manager {
    /**
     * Current version of the plugin
     *
     * @var string
     */
    private $version;

    /**
     * Constructor
     */
    public function __construct() {
        $this->version = $this->get_version();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_init', array($this, 'check_version'));
    }

    /**
     * Get current version
     *
     * @return string
     */
    public function get_version() {
        return defined('ECZP_VERSION') ? ECZP_VERSION : '1.0.0';
    }

    /**
     * Check version and update if necessary
     */
    public function check_version() {
        $current_version = get_option('eczp_version', '1.0.0');
        
        if (version_compare($current_version, $this->version, '<')) {
            $this->update_version($current_version, $this->version);
        }
    }

    /**
     * Update version and run necessary updates
     *
     * @param string $old_version Old version number
     * @param string $new_version New version number
     */
    private function update_version($old_version, $new_version) {
        // Run version-specific updates
        $this->run_version_updates($old_version, $new_version);
        
        // Update stored version
        update_option('eczp_version', $new_version);
        
        // Clear any cached data
        wp_cache_flush();
    }

    /**
     * Run version-specific updates
     *
     * @param string $old_version Old version number
     * @param string $new_version New version number
     */
    private function run_version_updates($old_version, $new_version) {
        // Example of version-specific updates
        if (version_compare($old_version, '1.1.0', '<')) {
            // Run updates for versions before 1.1.0
        }
        
        if (version_compare($old_version, '1.1.3', '<')) {
            // Run updates for versions before 1.1.3
        }
    }
}

// Initialize the version manager
new ECZP_Version_Manager(); 