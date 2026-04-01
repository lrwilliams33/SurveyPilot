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

    // Override the auto-generated duplicate submenu entry with the "Dashboard" label.
    add_submenu_page(
        'survey-pilot',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'survey-pilot',
        'sp_render_dashboard'
    );

    // Hidden page — accessible via Edit/Create buttons, not the sidebar.
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
        '1.8'
    );

    wp_enqueue_script(
        'survey-pilot-admin',
        SP_URL . 'assets/js/admin.js',
        [],
        '1.8',
        true
    );

    global $wpdb;
    $existing_titles = $wpdb->get_col("SELECT title FROM {$wpdb->prefix}survey_info");

    wp_localize_script('survey-pilot-admin', 'spAdmin', [
        'ajaxUrl'         => admin_url('admin-ajax.php'),
        'nonce'           => wp_create_nonce('sp_save_survey_order'),
        'exportNonce'     => wp_create_nonce('sp_export_survey_csv'),
        'surveyTitles'    => array_values($existing_titles),
    ]);
});

// Handle sort order save via AJAX
add_action('wp_ajax_sp_save_survey_order', 'sp_handle_save_survey_order');

function sp_handle_save_survey_order() {
    if (!check_ajax_referer('sp_save_survey_order', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    global $wpdb;
    $order = isset($_POST['order']) ? (array) $_POST['order'] : [];

    $survey_ids = array_values(array_filter(array_map('absint', $order)));
    if (empty($survey_ids)) {
        wp_send_json_success();
        return;
    }

    // Fetch current updated_at values so reordering doesn't touch them.
    $placeholders   = implode(',', array_fill(0, count($survey_ids), '%d'));
    $existing_rows  = $wpdb->get_results(
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->prepare(
            "SELECT id, updated_at FROM {$wpdb->prefix}survey_info WHERE id IN ($placeholders)",
            ...$survey_ids
        ),
        ARRAY_A
    );
    $updated_at_map = [];
    foreach ($existing_rows as $row) {
        $updated_at_map[ (int) $row['id'] ] = $row['updated_at'];
    }

    foreach ($order as $position => $survey_id) {
        $survey_id = absint($survey_id);
        if (!$survey_id) continue;
        $wpdb->update(
            $wpdb->prefix . 'survey_info',
            [
                'sort_order' => $position + 1,
                'updated_at' => $updated_at_map[ $survey_id ] ?? current_time('mysql'),
            ],
            ['id' => $survey_id],
            ['%d', '%s'],
            ['%d']
        );
    }

    wp_send_json_success();
}

// Handle CSV export via AJAX
add_action('wp_ajax_sp_export_survey_csv', 'sp_handle_export_survey_csv');

function sp_handle_export_survey_csv() {
    if (!check_ajax_referer('sp_export_survey_csv', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $survey_id = isset($_POST['survey_id']) ? absint($_POST['survey_id']) : 0;
    if (!$survey_id) {
        wp_send_json_error('Invalid survey ID');
    }

    global $wpdb;

    $survey = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}survey_info WHERE id = %d", $survey_id),
        ARRAY_A
    );
    if (!$survey) {
        wp_send_json_error('Survey not found');
    }

    // Questions ordered as they appear in the survey.
    $questions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, question_text, question_order
             FROM {$wpdb->prefix}survey_questions
             WHERE survey_id = %d
             ORDER BY question_order ASC",
            $survey_id
        ),
        ARRAY_A
    );

    // All responses + answers in one query.
    $raw = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT ri.id AS response_id, ri.user_id, ri.submitted_at,
                    ra.question_id, ra.answer_value
             FROM {$wpdb->prefix}survey_response_info ri
             LEFT JOIN {$wpdb->prefix}survey_response_answers ra ON ri.id = ra.response_id
             WHERE ri.survey_id = %d
             ORDER BY ri.id ASC",
            $survey_id
        ),
        ARRAY_A
    );

    // Group answers by response.
    $responses = [];
    foreach ($raw as $r) {
        $rid = (int) $r['response_id'];
        if (!isset($responses[$rid])) {
            $responses[$rid] = [
                'user_id'      => $r['user_id'],
                'submitted_at' => $r['submitted_at'],
                'answers'      => [],
            ];
        }
        if ($r['question_id'] !== null) {
            $responses[$rid]['answers'][ (int) $r['question_id'] ] = $r['answer_value'];
        }
    }

    // Build CSV rows.
    $rows   = [];
    $header = ['Response ID', 'User ID', 'Submitted At'];
    foreach ($questions as $q) {
        $header[] = 'Q' . $q['question_order'] . ': ' . $q['question_text'];
    }
    $rows[] = $header;

    foreach ($responses as $rid => $resp) {
        $row   = [];
        $row[] = $rid;
        $row[] = $resp['user_id'] !== null ? $resp['user_id'] : 'Guest';
        $row[] = $resp['submitted_at'];
        foreach ($questions as $q) {
            $row[] = $resp['answers'][ (int) $q['id'] ] ?? '';
        }
        $rows[] = $row;
    }

    // Serialize to CSV string.
    $csv = '';
    foreach ($rows as $row) {
        $cells = array_map(function ( $cell ) {
            $cell = (string) $cell;
            $cell = str_replace('"', '""', $cell);
            if ( strpbrk($cell, ",\"\n\r") !== false ) {
                $cell = '"' . $cell . '"';
            }
            return $cell;
        }, $row);
        $csv .= implode(',', $cells) . "\r\n";
    }

    $filename = sanitize_file_name($survey['title']) . '_responses.csv';

    wp_send_json_success([
        'csv'      => $csv,
        'filename' => $filename,
    ]);
}

// Collect page header text boxes from POST and return a JSON string (or null if none submitted).
// Keys are 1-based page numbers, values are the sanitized header strings (may be empty).
function sp_build_page_headers_json() {
    if (empty($_POST['sp_page_headers']) || !is_array($_POST['sp_page_headers'])) {
        return null;
    }

    $raw = $_POST['sp_page_headers'];
    $headers = [];
    foreach ($raw as $page_num => $value) {
        $page_num = absint($page_num);
        if ($page_num < 1) continue;
        $headers[$page_num] = sanitize_text_field(wp_unslash($value));
    }

    if (empty($headers)) {
        return null;
    }

    ksort($headers);
    return wp_json_encode($headers);
}

// Handle Create / Edit / Duplicate Survey Submission
add_action('admin_post_sp_create_survey', 'sp_handle_create_survey');
add_action('admin_post_sp_edit_survey', 'sp_handle_edit_survey');
add_action('admin_post_sp_duplicate_survey', 'sp_handle_duplicate_survey');

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

    if (empty($_POST['sp_questions']) || !is_array($_POST['sp_questions'])) {
        wp_die('A survey must have at least one question.', 'Validation Error', ['back_link' => true]);
    }

    // Create the survey
    $survey_title = sanitize_text_field(wp_unslash($_POST['sp_survey_title']));
    $description = isset($_POST['sp_survey_description']) ? sanitize_textarea_field(wp_unslash($_POST['sp_survey_description'])) : null;
    $instructions = isset($_POST['sp_survey_instructions']) ? sanitize_textarea_field(wp_unslash($_POST['sp_survey_instructions'])) : null;

    global $wpdb;
    $duplicate = $wpdb->get_var(
        $wpdb->prepare("SELECT id FROM {$wpdb->prefix}survey_info WHERE title = %s LIMIT 1", $survey_title)
    );
    if ($duplicate) {
        wp_die('A survey with that name already exists. Please choose a different title.', 'Duplicate Title', ['back_link' => true]);
    }

    $page_headers_json = sp_build_page_headers_json();

    $survey_id = sp_add_survey_info_row($survey_title, $description, $instructions, $page_headers_json);

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

    if (empty($_POST['sp_questions']) || !is_array($_POST['sp_questions'])) {
        wp_die('A survey must have at least one question.', 'Validation Error', ['back_link' => true]);
    }

    $survey_id = intval($_POST['sp_survey_id']);
    $survey_title = sanitize_text_field(wp_unslash($_POST['sp_survey_title']));
    $description = isset($_POST['sp_survey_description']) ? sanitize_textarea_field(wp_unslash($_POST['sp_survey_description'])) : null;
    $instructions = isset($_POST['sp_survey_instructions']) ? sanitize_textarea_field(wp_unslash($_POST['sp_survey_instructions'])) : null;

    global $wpdb;
    $duplicate = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}survey_info WHERE title = %s AND id != %d LIMIT 1",
            $survey_title,
            $survey_id
        )
    );
    if ($duplicate) {
        wp_die('A survey with that name already exists. Please choose a different title.', 'Duplicate Title', ['back_link' => true]);
    }

    $page_headers_json = sp_build_page_headers_json();

    $update_result = sp_update_survey_info_row($survey_id, $survey_title, $description, $instructions, $page_headers_json);

    if (is_wp_error($update_result)) {
        wp_die('Failed to update survey.');
    }

    sp_replace_survey_questions_from_post($survey_id);

    wp_redirect(admin_url('admin.php?page=survey-pilot&updated=1'));
    exit;
}

function sp_handle_duplicate_survey() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    if (!isset($_GET['survey_id'], $_GET['_wpnonce'])) {
        wp_die('Missing parameters');
    }

    $survey_id = intval($_GET['survey_id']);
    if ($survey_id <= 0 || !wp_verify_nonce($_GET['_wpnonce'], 'sp_duplicate_survey_' . $survey_id)) {
        wp_die('Security check failed');
    }

    global $wpdb;

    $survey_info_table = $wpdb->prefix . 'survey_info';
    $survey_questions_table = $wpdb->prefix . 'survey_questions';

    $original = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$survey_info_table} WHERE id = %d", $survey_id),
        ARRAY_A
    );

    if (!$original) {
        wp_die('Original survey not found.');
    }

    $original_title = $original['title'];
    $new_title = $original_title . ' (Copy)';

    $duplicate_exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$survey_info_table} WHERE title = %s LIMIT 1",
            $new_title
        )
    );
    if ($duplicate_exists) {
        wp_die(
            esc_html('"' . $new_title . '" already exists. Please rename or delete it before duplicating.'),
            'Cannot Duplicate Survey',
            ['back_link' => true]
        );
    }

    $new_survey_id = sp_add_survey_info_row(
        $new_title,
        $original['survey_description'],
        $original['instructions'],
        isset($original['page_headers']) ? $original['page_headers'] : null
    );

    if (is_wp_error($new_survey_id) || !$new_survey_id) {
        wp_die('Failed to duplicate survey.');
    }

    $questions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$survey_questions_table} WHERE survey_id = %d ORDER BY question_order ASC, id ASC",
            $survey_id
        ),
        ARRAY_A
    );

    if (!empty($questions)) {
        foreach ($questions as $question) {
            sp_add_survey_question_row(
                $new_survey_id,
                $question['question_text'],
                $question['question_order'],
                $question['scale_min'],
                $question['scale_max'],
                $question['scale_labels'],
                isset($question['page_number']) ? (int) $question['page_number'] : 1
            );
        }
    }

    wp_redirect(admin_url('admin.php?page=survey-pilot&duplicated=1'));
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

        $scale_rows = isset($question['scale']) && is_array($question['scale']) ? $question['scale'] : [];
        $page_number = isset($question['page']) ? max(1, intval($question['page'])) : 1;

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
            $scale_labels,
            $page_number
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