<?php
/*
Plugin Name: Survey Pilot
Description: Custom survey plugin
Version: 1.0
Author: Jack McKee, Landon Williams, Terry Lu
*/

add_action('admin_menu', function () {
    add_menu_page(
        'SurveyPilot',
        'SurveyPilot',
        'manage_options',
        'survey-pilot',
        function () {
            echo '<h1>Hello from SurveyPilot</h1>';
        }
    );
});


// Define plugin path
if (!defined('SP_PLUGIN_PATH')) {
    define('SP_PLUGIN_PATH', plugin_dir_path(__FILE__));
}

// Shortcode to display the survey to survey taker
add_shortcode('survey_pilot', function () {

    $step = isset($_GET['sp_step']) ? sanitize_text_field($_GET['sp_step']) : 'start';

    ob_start();

    switch ($step) {
        case 'info':
            $template_file = SP_PLUGIN_PATH . 'templates/user-info-page.php';
            break;

        case 'survey':
            $template_file = SP_PLUGIN_PATH . 'templates/user-survey-page.php';
            break;

        case 'confirmation':
            $template_file = SP_PLUGIN_PATH . 'templates/user-confirmation-page.php';
            break;

        case 'start':
        default:
            $template_file = SP_PLUGIN_PATH . 'templates/user-start-page.php';
            break;
    }

    if (file_exists($template_file)) {
        include $template_file;
    } else {
        echo '<div style="color:red;">SurveyPilot Error: Template file not found.<br>';
        echo 'Looking for: ' . esc_html($template_file) . '</div>';
    }

    return ob_get_clean();
});
