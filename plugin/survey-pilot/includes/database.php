<?php

// Create or update SurveyPilot database tables
function add_tables(){
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Master table that tracks big-picture survey info (title, description, instructions, etc.)
    $survey_info = $wpdb->prefix . 'survey_info';
    $sql_survey_info = "CREATE TABLE $survey_info (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        survey_description TEXT NULL,
        instructions TEXT NULL,
        send_email_message TINYINT(1) NOT NULL DEFAULT 0,
        email_message TEXT NULL,
        send_pdf_report TINYINT(1) NOT NULL DEFAULT 0,
        pdf_report_logo_attachment_id BIGINT UNSIGNED NULL DEFAULT NULL,
        survey_layout LONGTEXT NULL,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Table that tracks survey questions (question text, scale info, order, etc.)
    $survey_questions = $wpdb->prefix . 'survey_questions';
    $sql_survey_questions = "CREATE TABLE $survey_questions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        survey_id BIGINT UNSIGNED NOT NULL,
        question_text TEXT NOT NULL,
        scale_min TINYINT UNSIGNED NOT NULL,
        scale_max TINYINT UNSIGNED NOT NULL,
        scale_labels LONGTEXT NULL,
        question_order INT UNSIGNED NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // Table that tracks big-picture survey responses (user info, submission time, etc.)
    $survey_response_info = $wpdb->prefix . 'survey_response_info';
    $sql_survey_response_info = "CREATE TABLE $survey_response_info (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        survey_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NULL,
        submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // Table that tracks individual survey answers (question, response value, etc.)
    $survey_response_answers = $wpdb->prefix . 'survey_response_answers';
    $sql_survey_response_answers = "CREATE TABLE $survey_response_answers (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        response_id BIGINT UNSIGNED NOT NULL,
        question_id BIGINT UNSIGNED NOT NULL,
        answer_value TINYINT UNSIGNED NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    dbDelta($sql_survey_info);
    dbDelta($sql_survey_questions);
    dbDelta($sql_survey_response_info);
    dbDelta($sql_survey_response_answers);
}

function sp_make_slug($text) {
    $slug = strtolower($text);
    $slug = preg_replace('/[^a-z0-9_]+/', '_', $slug); 
    $slug = trim($slug, '_');
    if ($slug === '') $slug = 'survey';
    return $slug;
}

// Add a survey to the survey_info table
function sp_add_survey_info_row($title, $description = null, $instructions = null, $send_email_message = 0, $email_message = null, $send_pdf_report = 0, $survey_layout = null, $pdf_report_logo_attachment_id = null) {
    global $wpdb;

    $title        = is_string($title) ? trim($title) : '';
    $description  = is_string($description) ? trim($description) : null;
    $instructions = is_string($instructions) ? trim($instructions) : null;
    $send_email_message = $send_email_message ? 1 : 0;
    $email_message = ($email_message !== null && $email_message !== '')
        ? trim((string) $email_message)
        : null;
    $send_pdf_report = $send_pdf_report ? 1 : 0;
    $survey_layout = ($survey_layout !== null && $survey_layout !== '') ? (string) $survey_layout : null;
    $pdf_logo_id = ($pdf_report_logo_attachment_id !== null && (int) $pdf_report_logo_attachment_id > 0)
        ? (int) $pdf_report_logo_attachment_id
        : null;

    $insert_status = $wpdb->insert(
        $wpdb->prefix . 'survey_info',
        [
            'title'                           => $title,
            'survey_description'              => $description,
            'instructions'                    => $instructions,
            'send_email_message'              => $send_email_message,
            'email_message'                   => $email_message,
            'send_pdf_report'                 => $send_pdf_report,
            'pdf_report_logo_attachment_id'   => $pdf_logo_id,
            'survey_layout'                   => $survey_layout,
        ],
        [
            '%s',
            '%s',
            '%s',
            '%d',
            '%s',
            '%d',
            '%d',
            '%s',
        ]
    );

    if ($insert_status === false) {
        return new WP_Error('db_insert_error', 'Failed to insert survey info into the database');
    }

    $insert_id = $wpdb->insert_id;

    // Use the survey's own ID as its default sort order (will be overridden if admin uses custom order)
    $wpdb->update(
        $wpdb->prefix . 'survey_info',
        ['sort_order' => $insert_id],
        ['id'         => $insert_id],
        ['%d'],
        ['%d']
    );

    return $insert_id;
}


// Add a question to the survey_questions table
function sp_add_survey_question_row(
    $survey_id,
    $question_text,
    $question_order,
    $scale_min = 1,
    $scale_max = 5,
    $scale_labels = null
) {
    global $wpdb;

    $survey_id      = intval($survey_id);
    $question_text  = is_string($question_text) ? trim($question_text) : '';
    $scale_min      = intval($scale_min);
    $scale_max      = intval($scale_max);
    $scale_labels   = ($scale_labels !== null && $scale_labels !== '')
        ? trim((string) $scale_labels)
        : null;
    $question_order = intval($question_order);

    if ($survey_id <= 0) {
        return new WP_Error('invalid_survey_id', 'Invalid survey ID provided');
    }

    if (trim($question_text) === '') {
        return new WP_Error('empty_question_text', 'Question text cannot be empty');
    }

    if ($scale_min < 1 || $scale_max < 1 || $scale_min > $scale_max) {
        return new WP_Error('invalid_scale', 'Scale min and max values are invalid');
    }

    if ($question_order <= 0) {
        return new WP_Error('invalid_question_order', 'Question order must be a non-negative integer');
    }

    $insert_status = $wpdb->insert(
        $wpdb->prefix . 'survey_questions',
        [
            'survey_id'      => $survey_id,
            'question_text'  => $question_text,
            'scale_min'      => $scale_min,
            'scale_max'      => $scale_max,
            'scale_labels'   => $scale_labels,
            'question_order' => $question_order,
        ],
        [
            '%d',
            '%s',
            '%d',
            '%d',
            '%s',
            '%d',
        ]
    );

    if ($insert_status === false) {
        return new WP_Error('db_insert_error', 'Failed to insert survey question into the database');
    }

    return $wpdb->insert_id;
}

// Create a new survey response record in survey_response_info
function sp_create_response_info($survey_id, $user_id = null) {
    global $wpdb;

    $table = $wpdb->prefix . 'survey_response_info';

    $survey_id = absint($survey_id);
    $user_id = ($user_id !== null && absint($user_id) > 0) ? absint($user_id) : null;

    if ($user_id === null) {
        $user_id = get_current_user_id();
    }

    if ($survey_id <= 0 || $user_id == null) {
        return new WP_Error('sp_bad_survey_id_or_user_id', 'Invalid survey_id or user_id.');
    }

    $ok = $wpdb->insert(
        $table,
        [
            'survey_id' => $survey_id,
            'user_id' => $user_id,
        ],
        ['%d', '%d']
    );

    if ($ok === false) {
        return new WP_Error('sp_db_error', 'Failed to create response record.', $wpdb->last_error);
    }

    return (int) $wpdb->insert_id; 
}

// Save a single survey question's answer to survey_response_answers
function sp_add_response_answer($response_id, $survey_id, $question_id, $answer_value) {
    global $wpdb;

    $answers_table = $wpdb->prefix . 'survey_response_answers';
    $questions_table = $wpdb->prefix . 'survey_questions';

    $response_id = absint($response_id);
    $survey_id = absint($survey_id);
    $question_id = absint($question_id);
    $answer_value = absint($answer_value);

    if ($response_id <= 0 || $question_id <= 0 || $survey_id <= 0) {
        return new WP_Error('sp_bad_ids', 'Invalid response_id, survey_id, or question_id.');
    }

    $question = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$questions_table} WHERE id = %d AND survey_id = %d", $question_id, $survey_id),
        ARRAY_A
    );

    if (!$question) {
        return new WP_Error('sp_bad_question', 'Question not found.');
    }

    $min = (int) $question['scale_min'];
    $max = (int) $question['scale_max'];

    if ($answer_value < $min || $answer_value > $max) {
        return new WP_Error('sp_bad_answer', 'Answer value out of range.');
    }

    $ok = $wpdb->insert(
        $answers_table,
        [
            'response_id' => $response_id,
            'question_id' => $question_id,
            'answer_value' => $answer_value,
        ],
        ['%d', '%d', '%d']
    );

    if ($ok === false) {
        return new WP_Error('sp_db_error', 'Failed to save answer.', $wpdb->last_error);
    }

    return (int) $wpdb->insert_id;
}

// Save a complete survey submission in one transaction (all answers)
function sp_save_survey_submission($survey_id, array $answers, $user_id = null) {
    global $wpdb;

    $survey_id = absint($survey_id);
    if ($survey_id <= 0) {
        return new WP_Error('sp_bad_survey_id', 'Invalid survey_id.');
    }

    if (empty($answers)) {
        return new WP_Error('sp_no_answers', 'No answers submitted.');
    }

    $wpdb->query('START TRANSACTION');

    $response_id = sp_create_response_info($survey_id, $user_id);
    if (is_wp_error($response_id)) {
        $wpdb->query('ROLLBACK');
        return $response_id;
    }

    foreach ($answers as $question_id => $answer_value) {
        $res = sp_add_response_answer($response_id, $survey_id,$question_id, $answer_value);
        if (is_wp_error($res)) {
            $wpdb->query('ROLLBACK');
            return $res;
        }
    }

    $wpdb->query('COMMIT');
    return $response_id;
}

// Update survey_info fields
function sp_update_survey_info_row($survey_id, $title, $description = null, $instructions = null, $send_email_message = 0, $email_message = null, $send_pdf_report = 0, $survey_layout = null, $pdf_report_logo_attachment_id = null) {
    global $wpdb;

    $survey_id = intval($survey_id);
    if ($survey_id <= 0) {
        return new WP_Error('invalid_survey_id', 'Invalid survey ID provided');
    }

    $title        = is_string($title) ? trim($title) : '';
    $description  = is_string($description) ? trim($description) : null;
    $instructions = is_string($instructions) ? trim($instructions) : null;
    $send_email_message = $send_email_message ? 1 : 0;
    $email_message = ($email_message !== null && $email_message !== '')
        ? trim((string) $email_message)
        : null;
    $send_pdf_report = $send_pdf_report ? 1 : 0;
    $survey_layout = ($survey_layout !== null && $survey_layout !== '') ? (string) $survey_layout : null;
    $pdf_logo_id = ($pdf_report_logo_attachment_id !== null && (int) $pdf_report_logo_attachment_id > 0)
        ? (int) $pdf_report_logo_attachment_id
        : null;

    $update_status = $wpdb->update(
        $wpdb->prefix . 'survey_info',
        [
            'title'                           => $title,
            'survey_description'              => $description,
            'instructions'                    => $instructions,
            'send_email_message'              => $send_email_message,
            'email_message'                   => $email_message,
            'send_pdf_report'                 => $send_pdf_report,
            'pdf_report_logo_attachment_id'   => $pdf_logo_id,
            'survey_layout'                   => $survey_layout,
            'updated_at'                      => current_time('mysql'),
        ],
        [
            'id' => $survey_id
        ],
        [
            '%s',
            '%s',
            '%s',
            '%d',
            '%s',
            '%d',
            '%d',
            '%s',
            '%s',
        ],
        [
            '%d'
        ]
    );

    if ($update_status === false) {
        return new WP_Error('db_update_error', 'Failed to update survey info in the database');
    }

    return $update_status;
}
