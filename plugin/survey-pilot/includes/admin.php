<?php

add_action('admin_menu', function() {
    add_menu_page(
        'SurveyPilot Dashboard',
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

add_action('admin_enqueue_scripts', function() {
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
    if (!in_array($page, ['survey-pilot', 'sp-create-survey'], true)) {
        return;
    }

    wp_enqueue_style(
        'survey-pilot-admin',
        SP_URL . 'assets/css/admin.css',
        [],
        '1.0'
    );

    wp_enqueue_script(
        'survey-pilot-admin',
        SP_URL . 'assets/js/admin.js',
        [],
        '1.0',
        true
    );
});

// Handle Create Survey Submission
add_action('admin_post_sp_create_survey', 'sp_handle_create_survey');
add_action('admin_post_sp_edit_survey', 'sp_handle_edit_survey');

function sp_handle_create_survey() {
    if (!isset($_POST['sp_survey_title'], $_POST['_wpnonce']) ||
        !wp_verify_nonce($_POST['_wpnonce'], 'sp_create_survey_nonce')) {
        wp_die('Security check failed');
    }

    // Make sure the function exists
    if (!function_exists('sp_add_survey_info_row')) {
        wp_die('Survey creation function is missing.');
    }
    if (!function_exists('sp_add_survey_question_row')) {
        wp_die('Survey question creation function is missing.');
    }

    // Create the survey
    $survey_title = sanitize_text_field($_POST['sp_survey_title']);
    $description = isset($_POST['sp_survey_description']) ? sanitize_textarea_field($_POST['sp_survey_description']) : null;
    $instructions = isset($_POST['sp_survey_instructions']) ? sanitize_textarea_field($_POST['sp_survey_instructions']) : null;
    
    $survey_id = sp_add_survey_info_row($survey_title, $description, $instructions);

    if (is_wp_error($survey_id)) {
        wp_die('Failed to create survey.');
    }

    sp_save_survey_questions_from_post($survey_id);

    wp_redirect(admin_url('admin.php?page=survey-pilot&created=1'));
    exit;
}

function sp_handle_edit_survey() {
    if (!isset($_POST['sp_survey_id'], $_POST['sp_survey_title'], $_POST['_wpnonce']) ||
        !wp_verify_nonce($_POST['_wpnonce'], 'sp_edit_survey_nonce')) {
        wp_die('Security check failed');
    }

    if (!function_exists('sp_update_survey_info_row')) {
        wp_die('Survey update function is missing.');
    }
    if (!function_exists('sp_add_survey_question_row')) {
        wp_die('Survey question function is missing.');
    }

    $survey_id = intval($_POST['sp_survey_id']);
    $survey_title = sanitize_text_field($_POST['sp_survey_title']);
    $description = isset($_POST['sp_survey_description']) ? sanitize_textarea_field($_POST['sp_survey_description']) : null;
    $instructions = isset($_POST['sp_survey_instructions']) ? sanitize_textarea_field($_POST['sp_survey_instructions']) : null;

    $update_result = sp_update_survey_info_row($survey_id, $survey_title, $description, $instructions);

    if (is_wp_error($update_result)) {
        wp_die('Failed to update survey.');
    }

    sp_replace_survey_questions_from_post($survey_id);

    wp_redirect(admin_url('admin.php?page=survey-pilot&updated=1'));
    exit;
}

function sp_save_survey_questions_from_post($survey_id) {
    if (!isset($_POST['sp_questions']) || !is_array($_POST['sp_questions'])) {
        return;
    }

    $questions = $_POST['sp_questions'];
    $order = 1;

    foreach ($questions as $question) {
        $question_text = isset($question['text']) ? trim(wp_unslash($question['text'])) : '';
        if ($question_text === '') {
            continue;
        }

        $question_title = isset($question['title']) ? wp_unslash($question['title']) : null;
        $scale_rows = isset($question['scale']) && is_array($question['scale']) ? $question['scale'] : [];

        $values = [];
        $labels = [];

        foreach ($scale_rows as $row) {
            $value = isset($row['value']) ? intval($row['value']) : null;
            if ($value === null || $value <= 0) {
                continue;
            }

            $values[] = $value;
            $label_text = isset($row['label']) ? trim(wp_unslash($row['label'])) : '';
            $labels[$value] = $label_text;
        }

        if (!empty($values)) {
            sort($values);
            $values = array_unique($values);
            $scale_min = (int) reset($values);
            $scale_max = (int) end($values);
        } else {
            $scale_min = 1;
            $scale_max = 5;
        }

        // Store every scale value from min to max, with empty string for missing labels.
        $labels_complete = [];
        for ($v = $scale_min; $v <= $scale_max; $v++) {
            $labels_complete[$v] = isset($labels[$v]) ? $labels[$v] : '';
        }
        $scale_labels = wp_json_encode($labels_complete);

        sp_add_survey_question_row(
            $survey_id,
            $question_text,
            $order,
            $scale_min,
            $scale_max,
            $question_title,
            $scale_labels
        );

        $order++;
    }
}

function sp_replace_survey_questions_from_post($survey_id) {
    global $wpdb;

    $survey_id = intval($survey_id);
    if ($survey_id <= 0) {
        return;
    }

    $questions_table = $wpdb->prefix . 'survey_questions';
    $wpdb->delete($questions_table, ['survey_id' => $survey_id], ['%d']);

    sp_save_survey_questions_from_post($survey_id);
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

        if ($survey_id <= 0) {
            wp_die('Invalid survey ID');
        }

        $survey_info_table = $wpdb->prefix . 'survey_info';
        $survey_questions_table = $wpdb->prefix . 'survey_questions';
        $survey_response_info_table = $wpdb->prefix . 'survey_response_info';
        $survey_response_answers_table = $wpdb->prefix . 'survey_response_answers';

        // Delete answers associated with this survey's responses
        $wpdb->query(
            $wpdb->prepare(
                "DELETE a FROM $survey_response_answers_table a
                 INNER JOIN $survey_response_info_table r ON a.response_id = r.id
                 WHERE r.survey_id = %d",
                $survey_id
            )
        );

        // Delete response info rows
        $wpdb->delete($survey_response_info_table, ['survey_id' => $survey_id], ['%d']);

        // Delete questions for this survey
        $wpdb->delete($survey_questions_table, ['survey_id' => $survey_id], ['%d']);

        // Finally delete the survey itself
        $wpdb->delete($survey_info_table, ['id' => $survey_id], ['%d']);

        wp_redirect(admin_url('admin.php?page=survey-pilot&deleted=1'));
        exit;
    }
});