<?php

add_action('admin_menu', function() {
    add_menu_page(
        'SurveyPilot',
        'SurveyPilot',
        'manage_options',
        'survey-pilot-dashboard',
        'sp_render_dashboard',
        'dashicons-forms',
        30
    );

    // Override the auto-generated duplicate submenu entry with the "Dashboard" label.
    add_submenu_page(
        'survey-pilot-dashboard',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'survey-pilot-dashboard',
        'sp_render_dashboard'
    );

    // Hidden page — accessible via Edit/Create buttons, not the sidebar.
    add_submenu_page(
        null,
        'Create Survey',
        'Create Survey',
        'manage_options',
        'survey-pilot-create-survey',
        'sp_render_create_survey_page'
    );
});

add_action('admin_enqueue_scripts', function() {
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
    if (!in_array($page, ['survey-pilot-dashboard', 'survey-pilot-create-survey', 'survey-pilot-email-settings'], true)) {
        return;
    }

    wp_enqueue_style('dashicons');

    wp_enqueue_style(
        'survey-pilot-admin',
        SP_URL . 'assets/css/admin.css',
        ['dashicons'],
        '2.63'
    );

    wp_enqueue_script(
        'survey-pilot-admin',
        SP_URL . 'assets/js/admin.js',
        [],
        '2.55',
        true
    );

    global $wpdb;
    $existing_titles = $wpdb->get_col("SELECT title FROM {$wpdb->prefix}survey_info");

    wp_localize_script('survey-pilot-admin', 'spAdmin', [
        'ajaxUrl'         => admin_url('admin-ajax.php'),
        'nonce'           => wp_create_nonce('sp_save_survey_order'),
        'exportNonce'     => wp_create_nonce('sp_export_survey_csv'),
        'testEmailNonce'  => wp_create_nonce('sp_send_test_email'),
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
            "SELECT id, question_text, question_order, scale_min, scale_max
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
        $scale_min = isset($q['scale_min']) ? (int) $q['scale_min'] : 1;
        $scale_max = isset($q['scale_max']) ? (int) $q['scale_max'] : 5;
        if ($scale_min <= 0) {
            $scale_min = 1;
        }
        if ($scale_max < $scale_min) {
            $scale_max = $scale_min;
        }
        $header[] = 'Q' . $q['question_order'] . ' (' . $scale_min . '-' . $scale_max . '): ' . $q['question_text'];
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

/**
 * Upload a .jpg / .jpeg / .png for the PDF report header. Uses WordPress upload + attachment APIs.
 *
 * @return int|null|WP_Error Attachment ID, null if no file submitted, WP_Error on failure.
 */
function sp_upload_survey_pdf_logo_file() {
    if (empty($_FILES['sp_pdf_report_logo']) || !is_array($_FILES['sp_pdf_report_logo'])) {
        return null;
    }

    $file = $_FILES['sp_pdf_report_logo'];

    if (empty($file['name']) || (isset($file['error']) && (int) $file['error'] === UPLOAD_ERR_NO_FILE)) {
        return null;
    }

    if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
        return new WP_Error('sp_logo_upload', __('The logo file could not be uploaded.', 'survey-pilot'));
    }

    if (!current_user_can('upload_files')) {
        return new WP_Error('sp_logo_cap', __('You do not have permission to upload files.', 'survey-pilot'));
    }

    $max_bytes = 2 * 1024 * 1024;
    if (isset($file['size']) && (int) $file['size'] > $max_bytes) {
        return new WP_Error('sp_logo_size', __('Logo must be 2 MB or smaller.', 'survey-pilot'));
    }

    $allowed_mimes = [
        'jpg|jpeg' => 'image/jpeg',
        'png'      => 'image/png',
    ];

    $checked = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed_mimes);
    if (empty($checked['ext']) || empty($checked['type'])) {
        return new WP_Error('sp_logo_type', __('Only .jpg, .jpeg, or .png images are allowed for the PDF logo.', 'survey-pilot'));
    }

    if (!in_array($checked['type'], ['image/jpeg', 'image/png'], true)) {
        return new WP_Error('sp_logo_type', __('Only .jpg, .jpeg, or .png images are allowed for the PDF logo.', 'survey-pilot'));
    }

    $imginfo = @getimagesize($file['tmp_name']);
    if ($imginfo === false || !isset($imginfo[2])) {
        return new WP_Error('sp_logo_invalid', __('The file is not a valid image.', 'survey-pilot'));
    }

    $allowed_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG];
    if (!in_array((int) $imginfo[2], $allowed_types, true)) {
        return new WP_Error('sp_logo_invalid', __('Only .jpg, .jpeg, or .png images are allowed for the PDF logo.', 'survey-pilot'));
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $upload = wp_handle_upload(
        $file,
        [
            'test_form' => false,
            'mimes'     => $allowed_mimes,
        ]
    );

    if (isset($upload['error'])) {
        return new WP_Error('sp_logo_upload', $upload['error']);
    }

    if (empty($upload['file']) || !is_string($upload['file'])) {
        return new WP_Error('sp_logo_upload', __('The logo file could not be saved.', 'survey-pilot'));
    }

    $attachment = [
        'post_mime_type' => $checked['type'],
        'post_title'     => sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];

    $attach_id = wp_insert_attachment($attachment, $upload['file']);
    if (is_wp_error($attach_id) || !$attach_id) {
        if (file_exists($upload['file'])) {
            wp_delete_file($upload['file']);
        }
        return new WP_Error('sp_logo_attachment', __('Could not create the media attachment for the logo.', 'survey-pilot'));
    }

    $meta = wp_generate_attachment_metadata($attach_id, $upload['file']);
    if (!empty($meta) && !is_wp_error($meta)) {
        wp_update_attachment_metadata($attach_id, $meta);
    }

    return (int) $attach_id;
}

// Handle Create / Edit / Duplicate Survey Submission
add_action('admin_post_sp_create_survey', 'sp_handle_create_survey');
add_action('admin_post_sp_edit_survey', 'sp_handle_edit_survey');
add_action('admin_post_sp_duplicate_survey', 'sp_handle_duplicate_survey');

function sp_handle_create_survey() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

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
    // Store raw text (no HTML execution) and rely on escaping on output.
    $survey_title = isset($_POST['sp_survey_title']) ? trim((string) wp_unslash($_POST['sp_survey_title'])) : '';
    $description  = isset($_POST['sp_survey_description']) ? trim((string) wp_unslash($_POST['sp_survey_description'])) : null;
    $instructions = isset($_POST['sp_survey_instructions']) ? trim((string) wp_unslash($_POST['sp_survey_instructions'])) : null;

    global $wpdb;
    $duplicate = $wpdb->get_var(
        $wpdb->prepare("SELECT id FROM {$wpdb->prefix}survey_info WHERE title = %s LIMIT 1", $survey_title)
    );
    if ($duplicate) {
        wp_die('A survey with that name already exists. Please choose a different title.', 'Duplicate Title', ['back_link' => true]);
    }

    $send_email_message = !empty($_POST['sp_email_messaging']) ? 1 : 0;
    $email_message      = isset($_POST['sp_email_message']) ? trim((string) wp_unslash($_POST['sp_email_message'])) : null;
    $send_pdf_report    = ($send_email_message && !empty($_POST['sp_send_pdf_report'])) ? 1 : 0;

    if ($send_email_message && empty(trim($email_message ?? ''))) {
        wp_die('Message is required if "Send Email Message" is checked.', 'Validation Error', ['back_link' => true]);
    }

    $layout_result = sp_process_survey_layout_from_post(
        isset($_POST['sp_survey_layout']) ? $_POST['sp_survey_layout'] : null,
        $_POST['sp_questions'] ?? null,
        $_POST['sp_page_headers'] ?? null
    );
    if (is_wp_error($layout_result)) {
        wp_die(esc_html($layout_result->get_error_message()), 'Validation Error', ['back_link' => true]);
    }

    $logo_attachment_id = null;
    if ($send_pdf_report) {
        $upload_res = sp_upload_survey_pdf_logo_file();
        if (is_wp_error($upload_res)) {
            wp_die(esc_html($upload_res->get_error_message()), 'Validation Error', ['back_link' => true]);
        }
        if ($upload_res !== null && (int) $upload_res > 0) {
            $logo_attachment_id = (int) $upload_res;
        }
    }

    $survey_id = sp_add_survey_info_row(
        $survey_title,
        $description,
        $instructions,
        $send_email_message,
        $email_message,
        $send_pdf_report,
        $layout_result,
        $logo_attachment_id
    );

    if (is_wp_error($survey_id)) {
        if ($logo_attachment_id) {
            wp_delete_attachment($logo_attachment_id, true);
        }
        wp_die('Failed to create survey.');
    }

    sp_save_survey_questions_from_post($survey_id);

    wp_redirect(admin_url('admin.php?page=survey-pilot-dashboard'));
    exit;
}

function sp_handle_edit_survey() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

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
    $survey_title = isset($_POST['sp_survey_title']) ? trim((string) wp_unslash($_POST['sp_survey_title'])) : '';
    $description  = isset($_POST['sp_survey_description']) ? trim((string) wp_unslash($_POST['sp_survey_description'])) : null;
    $instructions = isset($_POST['sp_survey_instructions']) ? trim((string) wp_unslash($_POST['sp_survey_instructions'])) : null;

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

    $send_email_message = !empty($_POST['sp_email_messaging']) ? 1 : 0;
    $email_message      = isset($_POST['sp_email_message']) ? trim((string) wp_unslash($_POST['sp_email_message'])) : null;
    $send_pdf_report    = ($send_email_message && !empty($_POST['sp_send_pdf_report'])) ? 1 : 0;

    if ($send_email_message && empty(trim($email_message ?? ''))) {
        wp_die('Message is required if "Send Email Message" is checked.', 'Validation Error', ['back_link' => true]);
    }

    $locked_check = sp_validate_locked_survey_edit($survey_id);
    if (is_wp_error($locked_check)) {
        wp_die(esc_html($locked_check->get_error_message()), 'Validation Error', ['back_link' => true]);
    }

    $layout_result = sp_process_survey_layout_from_post(
        isset($_POST['sp_survey_layout']) ? $_POST['sp_survey_layout'] : null,
        $_POST['sp_questions'] ?? null,
        $_POST['sp_page_headers'] ?? null
    );
    if (is_wp_error($layout_result)) {
        wp_die(esc_html($layout_result->get_error_message()), 'Validation Error', ['back_link' => true]);
    }

    $current_logo_row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT pdf_report_logo_attachment_id FROM {$wpdb->prefix}survey_info WHERE id = %d",
            $survey_id
        ),
        ARRAY_A
    );
    $old_logo_id = isset($current_logo_row['pdf_report_logo_attachment_id'])
        ? (int) $current_logo_row['pdf_report_logo_attachment_id']
        : 0;

    $upload_res = null;
    if ($send_pdf_report) {
        $upload_res = sp_upload_survey_pdf_logo_file();
        if (is_wp_error($upload_res)) {
            wp_die(esc_html($upload_res->get_error_message()), 'Validation Error', ['back_link' => true]);
        }
    }

    $new_logo_id = $old_logo_id;
    if ($send_pdf_report && $upload_res !== null && (int) $upload_res > 0) {
        if ($old_logo_id > 0 && $old_logo_id !== (int) $upload_res) {
            wp_delete_attachment($old_logo_id, true);
        }
        $new_logo_id = (int) $upload_res;
    } elseif ($send_pdf_report && !empty($_POST['sp_remove_pdf_report_logo'])) {
        if ($old_logo_id > 0) {
            wp_delete_attachment($old_logo_id, true);
        }
        $new_logo_id = 0;
    }

    $logo_for_db = $new_logo_id > 0 ? $new_logo_id : null;

    $update_result = sp_update_survey_info_row(
        $survey_id,
        $survey_title,
        $description,
        $instructions,
        $send_email_message,
        $email_message,
        $send_pdf_report,
        $layout_result,
        $logo_for_db
    );

    if (is_wp_error($update_result)) {
        wp_die('Failed to update survey.');
    }

    sp_replace_survey_questions_from_post($survey_id);

    wp_redirect(admin_url('admin.php?page=survey-pilot-dashboard'));
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

    $dup_logo = isset($original['pdf_report_logo_attachment_id'])
        ? (int) $original['pdf_report_logo_attachment_id']
        : 0;
    $dup_logo = $dup_logo > 0 ? $dup_logo : null;

    $new_survey_id = sp_add_survey_info_row(
        $new_title,
        $original['survey_description'],
        $original['instructions'],
        isset($original['send_email_message']) ? (int) $original['send_email_message'] : 0,
        isset($original['email_message']) ? $original['email_message'] : null,
        isset($original['send_pdf_report']) ? (int) $original['send_pdf_report'] : 0,
        isset($original['survey_layout']) ? $original['survey_layout'] : null,
        $dup_logo
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
                $question['scale_labels']
            );
        }
    }

    wp_redirect(admin_url('admin.php?page=survey-pilot-dashboard'));
    exit;
}

function sp_save_survey_questions_from_post($survey_id) {
    if (!isset($_POST['sp_questions']) || !is_array($_POST['sp_questions'])) {
        return;
    }

    $questions = sp_normalize_questions_post_array($_POST['sp_questions']);
    $order = 1;

    foreach ($questions as $question) {
        $question_text = isset($question['text']) ? trim(wp_unslash($question['text'])) : '';
        if ($question_text === '') {
            continue;
        }

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

    if (!isset($_POST['sp_questions']) || !is_array($_POST['sp_questions'])) {
        return;
    }

    $questions_table = $wpdb->prefix . 'survey_questions';
    $questions = sp_normalize_questions_post_array($_POST['sp_questions']);
    $order = 1;
    $submitted_ids = [];

    foreach ($questions as $question) {
        $question_text = isset($question['text']) ? trim(wp_unslash($question['text'])) : '';
        if ($question_text === '') {
            $order++;
            continue;
        }

        $existing_id  = isset($question['id']) ? absint($question['id']) : 0;
        $scale_rows   = isset($question['scale']) && is_array($question['scale']) ? $question['scale'] : [];

        $values = [];
        $labels = [];

        foreach ($scale_rows as $row) {
            $value = isset($row['value']) ? intval($row['value']) : null;
            if ($value === null || $value <= 0) continue;
            $values[] = $value;
            $labels[$value] = isset($row['label']) ? trim(wp_unslash($row['label'])) : '';
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

        $labels_complete = [];
        for ($v = $scale_min; $v <= $scale_max; $v++) {
            $labels_complete[$v] = isset($labels[$v]) ? $labels[$v] : '';
        }
        $scale_labels = wp_json_encode($labels_complete);

        if ($existing_id > 0) {
            // Verify this question actually belongs to this survey before updating.
            $owner = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT survey_id FROM $questions_table WHERE id = %d LIMIT 1",
                    $existing_id
                )
            );

            if ((int) $owner === $survey_id) {
                $wpdb->update(
                    $questions_table,
                    [
                        // Store raw text; escape when rendering in admin/frontend.
                        'question_text'  => $question_text,
                        'scale_min'      => $scale_min,
                        'scale_max'      => $scale_max,
                        'scale_labels'   => $scale_labels,
                        'question_order' => $order,
                    ],
                    ['id' => $existing_id],
                    ['%s', '%d', '%d', '%s', '%d'],
                    ['%d']
                );
                $submitted_ids[] = $existing_id;
                $order++;
                continue;
            }
        }

        // No valid existing ID — insert as new question.
        $new_id = sp_add_survey_question_row(
            $survey_id,
            $question_text,
            $order,
            $scale_min,
            $scale_max,
            $scale_labels
        );

        if ($new_id && !is_wp_error($new_id)) {
            $submitted_ids[] = $new_id;
        }

        $order++;
    }

    // Delete questions that were removed in the editor (not in the submitted list).
    if (!empty($submitted_ids)) {
        $placeholders = implode(',', array_fill(0, count($submitted_ids), '%d'));
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $questions_table WHERE survey_id = %d AND id NOT IN ($placeholders)",
                array_merge([$survey_id], $submitted_ids)
            )
        );
    } else {
        // All questions were removed.
        $wpdb->delete($questions_table, ['survey_id' => $survey_id], ['%d']);
    }
}

// Handle Delete Action
add_action('admin_init', function() {
    if (!isset($_GET['action'], $_GET['id'])) return;
    $action = sanitize_text_field($_GET['action']);
    $survey_id = intval($_GET['id']);
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

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

        $pdf_logo_attachment_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT pdf_report_logo_attachment_id FROM {$survey_info_table} WHERE id = %d",
                $survey_id
            )
        );
        $other_surveys_share_logo = 0;
        if ($pdf_logo_attachment_id > 0) {
            $other_surveys_share_logo = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$survey_info_table} WHERE pdf_report_logo_attachment_id = %d AND id != %d",
                    $pdf_logo_attachment_id,
                    $survey_id
                )
            );
        }

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

        // Remove Media Library attachment only if no other survey still references it (e.g. duplicate shares one file).
        if ($pdf_logo_attachment_id > 0 && $other_surveys_share_logo === 0) {
            wp_delete_attachment($pdf_logo_attachment_id, true);
        }

        wp_redirect(admin_url('admin.php?page=survey-pilot-dashboard'));
        exit;
    }
}); 