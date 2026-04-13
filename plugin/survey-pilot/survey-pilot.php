<?php
/*
Plugin Name: SurveyPilot
Description: Plugin for building, publishing, and managing Likert scale surveys. Optional email notifications and PDF reports provide clear summaries of participant responses and comparisons across respondents.
Version: 1.0
Author: Jack McKee, Landon Williams, Terry Lu
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: survey-pilot
*/

if (!defined('ABSPATH')) {
    exit;
}

// Filesystem and URL roots used across plugin code
define('SP_PATH', plugin_dir_path(__FILE__));
define('SP_URL', plugin_dir_url(__FILE__));

// Make domPDF available for PDF generation
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once SP_PATH . 'includes/database.php';
require_once SP_PATH . 'includes/survey-layout.php';

// Create database tables when plugin is activated
register_activation_hook(__FILE__, 'add_tables');

require_once SP_PATH . 'includes/admin.php';
require_once SP_PATH . 'includes/admin-pages.php';
require_once SP_PATH . 'includes/pdf-report.php';
require_once SP_PATH . 'includes/frontend.php';
require_once SP_PATH . 'includes/email-settings.php';
