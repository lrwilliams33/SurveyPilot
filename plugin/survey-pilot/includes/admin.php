<?php

add_action('admin_menu', function() {
    add_menu_page(
        'SurveyPilot',
        'SurveyPilot',
        'manage_options',
        'survey-pilot',
        'sp_render_dashboard',
        'dashicons-forms',
        6
    );

    add_submenu_page(
        null,
        'Create Survey',
        'Create Survey',
        'manage_options',
        'sp-create-survey',
        'sp_render_create_survey_page'
    );
});

// Handle Create Survey Submission
add_action('admin_post_sp_create_survey', 'sp_handle_create_survey');

function sp_handle_create_survey() {
    if (!isset($_POST['sp_survey_title'], $_POST['_wpnonce']) ||
        !wp_verify_nonce($_POST['_wpnonce'], 'sp_create_survey_nonce')) {
        wp_die('Security check failed');
    }

    // Make sure the function exists
    if (!function_exists('sp_create_admin_survey')) {
        wp_die('Survey creation function is missing.');
    }

    // Create the survey
    $survey_title = sanitize_text_field($_POST['sp_survey_title']);
    sp_create_admin_survey($survey_title);

    wp_redirect(admin_url('admin.php?page=survey-pilot&created=1'));
    exit;
}

// Handle Delete Action
add_action('admin_init', function() {
    if (!isset($_GET['action'], $_GET['id'])) return;
    $action = sanitize_text_field($_GET['action']);
    $survey_id = intval($_GET['id']);
    global $wpdb;

    if ($action === 'delete') {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'sp_delete_survey_' . $survey_id)) {
            wp_die('Security check failed');
        }

        $table_name = $wpdb->get_var($wpdb->prepare(
            "SELECT table_name FROM {$wpdb->prefix}survey_pilot_master_table WHERE id = %d",
            $survey_id
        ));

        if ($table_name) {
            $wpdb->query("DROP TABLE IF EXISTS $table_name");
        }

        $wpdb->delete("{$wpdb->prefix}survey_pilot_master_table", ['id' => $survey_id]);

        wp_redirect(admin_url('admin.php?page=survey-pilot&deleted=1'));
        exit;
    }
});

// Handle Create Survey Submission
add_action('admin_post_sp_create_survey', function() {
    if (!isset($_POST['sp_survey_title'], $_POST['_wpnonce']) ||
        !wp_verify_nonce($_POST['_wpnonce'], 'sp_create_survey_nonce')) {
        wp_die('Security check failed');
    }

    sp_create_admin_survey(sanitize_text_field($_POST['sp_survey_title']));
    wp_redirect(admin_url('admin.php?page=survey-pilot&created=1'));
    exit;
});