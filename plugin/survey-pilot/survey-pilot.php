<?php
/*
Plugin Name: SurveyPilot
Description: Custom survey builder plugin.
Version: 1.0
Author: Jack McKee, Landon Williams, Terry Lu
*/

if (!defined('ABSPATH')) {
    exit;
}


define('SP_PATH', plugin_dir_path(__FILE__));
define('SP_URL', plugin_dir_url(__FILE__));

//makes Dompdf available for PDF generation
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once SP_PATH . 'includes/database.php';
register_activation_hook(__FILE__, 'add_tables');

// Run DB migrations on admin load when schema version changes.
add_action('admin_init', function() {
    if (get_option('sp_db_version') !== '1.6') {
        add_tables();
        update_option('sp_db_version', '1.6');
    }
});

require_once SP_PATH . 'includes/admin.php';
require_once SP_PATH . 'includes/admin-pages.php';
//require_once SP_PATH . 'includes/admin-handlers.php';
require_once SP_PATH . 'includes/pdf-report.php';
require_once SP_PATH . 'includes/frontend.php';
require_once SP_PATH . 'includes/survey-service.php';
//require_once SP_PATH . 'includes/response-service.php';
//require_once SP_PATH . 'includes/analytics-service.php';
require_once SP_PATH . 'includes/email-settings.php';
