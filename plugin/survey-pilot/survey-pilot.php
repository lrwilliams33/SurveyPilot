<?php
/*
Plugin Name: Survey Pilot
Description: Custom survey plugin
Version: 1.0
Author: Jack McKee, Landon Williams, Terry Lu
*/

//Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

register_activation_hook(__FILE__, 'add_master_table');

function add_master_table(){
    //load global variable for prefix
    global $wpdb;
    //get proper character set for database naming syntax
    $charset_collate = $wpdb->get_charset_collate();
    //load functions from upgrade.php to use dbDelta for creating/updating tables
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    //create master table to track all surveys and their corresponding tables
    $master_table = $wpdb->prefix . 'survey_pilot_master_table';
    $sql_add_master_table = "CREATE TABLE $master_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(120) NOT NULL,
        table_name VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug),
        UNIQUE KEY table_name (table_name)
    ) $charset_collate;";
    
    //execute the SQL statement to create the master table
    dbDelta($sql_add_master_table);
}

/*Helper function to create a slug, which is the extension for a survey table name that follows the wp prefix.
This slug will be used in the sp_create_admin_survey_table function to create a new table per admin survey created
*/

function sp_make_slug($text) {
    //converts slug to lowercase
    $slug = strtolower($text);
    //replaces anything not alphanumeric or an underscore with an underscore
    $slug = preg_replace('/[^a-z0-9_]+/', '_', $slug); 
    //trims leading and trailing underscores
    $slug = trim($slug, '_');
    //if extension is empty, have a filler slug name 
    if ($slug === '') $slug = 'survey';
    return $slug;
}

//This will create a new table for each survey created by the admin, which stores rows of survey taker submissions
function sp_create_admin_survey_helper($slug){
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    //Create table with the global wp variable, slug extension, and indexing on the user_id for faster lookups of survey taker data
    $table_name = $wpdb->prefix . 'survey_pilot_' . $slug;
    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NULL,
        survey_data LONGTEXT NOT NULL,
        submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset_collate;";

    
    dbDelta($sql);
    return $table_name;
}


//This function creates the actual survey table in the database for the admin, using helper functions from above
function sp_create_admin_survey($text){
    global $wpdb;
    $slug = sp_make_slug($text);
    //creates the survey table in the db
    $table_name = sp_create_admin_survey_helper($slug);

    //inserts a new row into the master table to track the survey and its corresponding table
    $wpdb->insert(
        $wpdb->prefix . 'survey_pilot_master_table',
        [
            'title' => $text,
            'slug' => $slug,
            'table_name' => $table_name
        ]
    );
}

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
            $template_file = SP_PLUGIN_PATH . 'templates/user-templates/user-info-page.php';
            break;

        case 'survey':
            $template_file = SP_PLUGIN_PATH . 'templates/user-templates/user-survey-page.php';
            break;

        case 'confirmation':
            $template_file = SP_PLUGIN_PATH . 'templates/user-templates/user-confirmation-page.php';
            break;

        case 'start':
        default:
            $template_file = SP_PLUGIN_PATH . 'templates/user-templates/user-start-page.php';
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
