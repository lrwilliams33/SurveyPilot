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
require_once SP_PATH . 'includes/survey-layout.php';
register_activation_hook(__FILE__, 'add_tables');

// Run DB migrations on admin load when schema version changes.
add_action('admin_init', function () {
    $v = get_option('sp_db_version', '');
    if ($v === '1.9') {
        return;
    }
    if ($v !== '1.8') {
        if (function_exists('sp_run_survey_pilot_db_upgrade_to_18')) {
            sp_run_survey_pilot_db_upgrade_to_18();
        } else {
            add_tables();
        }
    }
    if (function_exists('sp_run_survey_pilot_db_upgrade_to_19')) {
        sp_run_survey_pilot_db_upgrade_to_19();
    }
    update_option('sp_db_version', '1.9');
}, 5);

require_once SP_PATH . 'includes/admin.php';
require_once SP_PATH . 'includes/admin-pages.php';
require_once SP_PATH . 'includes/pdf-report.php';
require_once SP_PATH . 'includes/frontend.php';
require_once SP_PATH . 'includes/email-settings.php';
