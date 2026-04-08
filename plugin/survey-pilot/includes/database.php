<?php


function add_tables(){
    //load global variable for prefix
    global $wpdb;
    //get proper character set for database naming syntax
    $charset_collate = $wpdb->get_charset_collate();
    //load functions from upgrade.php to use dbDelta for creating/updating tables
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    //create master table to track all surveys and their corresponding tables
    $survey_info = $wpdb->prefix . 'survey_info';
    $sql_survey_info = "CREATE TABLE $survey_info (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        survey_description TEXT NULL,
        instructions TEXT NULL,
        send_email_message TINYINT(1) NOT NULL DEFAULT 0,
        email_message TEXT NULL,
        send_pdf_report TINYINT(1) NOT NULL DEFAULT 0,
        survey_layout LONGTEXT NULL,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

     //create table to track survey questions, linked to a specific survey by survey_id foreign key
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

     //create table to track survey responses and user info, linked to a specific survey by survey_id foreign key
    $survey_response_info = $wpdb->prefix . 'survey_response_info';
    $sql_survey_response_info = "CREATE TABLE $survey_response_info (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        survey_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NULL,
        submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

     //create table to track survey answers, linked to a specific survey question by question_id foreign key and to a specific survey response by response_id foreign key
    $survey_response_answers = $wpdb->prefix . 'survey_response_answers';
    $sql_survey_response_answers = "CREATE TABLE $survey_response_answers (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        response_id BIGINT UNSIGNED NOT NULL,
        question_id BIGINT UNSIGNED NOT NULL,
        answer_value TINYINT UNSIGNED NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    //execute the SQL statement to create the master table
    dbDelta($sql_survey_info);
    dbDelta($sql_survey_questions);
    dbDelta($sql_survey_response_info);
    dbDelta($sql_survey_response_answers);
}

/**
 * Migrate stored surveys to survey_layout-only schema and drop legacy columns.
 * Requires includes/survey-layout.php to be loaded first.
 */
function sp_run_survey_pilot_db_upgrade_to_18() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    add_tables();

    if (function_exists('sp_build_survey_layout_from_legacy_survey_row')) {
        $table = $wpdb->prefix . 'survey_info';
        $surveys = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
        if (is_array($surveys)) {
            foreach ($surveys as $row) {
                if (!empty($row['survey_layout'])) {
                    continue;
                }
                $questions = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}survey_questions WHERE survey_id = %d ORDER BY question_order ASC, id ASC",
                        $row['id']
                    ),
                    ARRAY_A
                );
                $json = sp_build_survey_layout_from_legacy_survey_row($row, is_array($questions) ? $questions : []);
                $wpdb->update(
                    $table,
                    ['survey_layout' => $json],
                    ['id' => $row['id']],
                    ['%s'],
                    ['%d']
                );
            }
        }
    }

    $info = $wpdb->prefix . 'survey_info';
    $has_ph = $wpdb->get_results("SHOW COLUMNS FROM {$info} LIKE 'page_headers'");
    if (!empty($has_ph)) {
        $wpdb->query("ALTER TABLE {$info} DROP COLUMN page_headers");
    }

    $qt = $wpdb->prefix . 'survey_questions';
    $has_pn = $wpdb->get_results("SHOW COLUMNS FROM {$qt} LIKE 'page_number'");
    if (!empty($has_pn)) {
        $wpdb->query("ALTER TABLE {$qt} DROP COLUMN page_number");
    }

    add_tables();
}

/*Helper function to create a slug, which is the extension for a survey table name that follows the wp prefix.
This slug will be used to create a valid database name extension
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

/*
Following functions are for adding rows to the tables
*/

//This function adds into the survey_info table to store created surveys
function sp_add_survey_info_row($title, $description = null, $instructions = null, $send_email_message = 0, $email_message = null, $send_pdf_report = 0, $survey_layout = null) {
    global $wpdb;

    // Store raw text; rely on escaping on output to prevent XSS.
    $title        = is_string($title) ? trim($title) : '';
    $description  = is_string($description) ? trim($description) : null;
    $instructions = is_string($instructions) ? trim($instructions) : null;
    $send_email_message = $send_email_message ? 1 : 0;
    $email_message = ($email_message !== null && $email_message !== '')
        ? trim((string) $email_message)
        : null;
    $send_pdf_report = $send_pdf_report ? 1 : 0;
    $survey_layout = ($survey_layout !== null && $survey_layout !== '') ? (string) $survey_layout : null;

    $insert_status = $wpdb->insert(
        $wpdb->prefix . 'survey_info',
        [
            'title'               => $title,
            'survey_description'  => $description,
            'instructions'        => $instructions,
            'send_email_message'  => $send_email_message,
            'email_message'       => $email_message,
            'send_pdf_report'     => $send_pdf_report,
            'survey_layout'       => $survey_layout,
        ],
        [
            '%s',
            '%s',
            '%s',
            '%d',
            '%s',
            '%d',
            '%s',
        ]
    );

    if ($insert_status === false) {
        return new WP_Error('db_insert_error', 'Failed to insert survey info into the database');
    }

    $insert_id = $wpdb->insert_id;

    // Use the survey's own ID as its default sort_order so new surveys
    // appear in a predictable position within custom ordering.
    $wpdb->update(
        $wpdb->prefix . 'survey_info',
        ['sort_order' => $insert_id],
        ['id'         => $insert_id],
        ['%d'],
        ['%d']
    );

    return $insert_id;
}


//This function adds a row to the survey_questions table for a given survey, with question text and scale info
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
    // Store raw text; escape on output.
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

//This function creates a new submission record in survey_response_info
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

//This function creates a record for answers in the answers database table
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

function sp_save_survey_submission($survey_id, array $answers, $user_id = null) {
    global $wpdb;

    $survey_id = absint($survey_id);
    if ($survey_id <= 0) {
        return new WP_Error('sp_bad_survey_id', 'Invalid survey_id.');
    }

    if (empty($answers)) {
        return new WP_Error('sp_no_answers', 'No answers submitted.');
    }

    //make sure that the submission table is updated and the answers table,
    //we don't want a sql error that fails halfway and only updates one table
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

/**
 * Update survey_info fields (title/description/instructions) and always bump updated_at.
 */
function sp_update_survey_info_row($survey_id, $title, $description = null, $instructions = null, $send_email_message = 0, $email_message = null, $send_pdf_report = 0, $survey_layout = null) {
    global $wpdb;

    $survey_id = intval($survey_id);
    if ($survey_id <= 0) {
        return new WP_Error('invalid_survey_id', 'Invalid survey ID provided');
    }

    // Store raw text; rely on escaping on output to prevent XSS.
    $title        = is_string($title) ? trim($title) : '';
    $description  = is_string($description) ? trim($description) : null;
    $instructions = is_string($instructions) ? trim($instructions) : null;
    $send_email_message = $send_email_message ? 1 : 0;
    $email_message = ($email_message !== null && $email_message !== '')
        ? trim((string) $email_message)
        : null;
    $send_pdf_report = $send_pdf_report ? 1 : 0;
    $survey_layout = ($survey_layout !== null && $survey_layout !== '') ? (string) $survey_layout : null;

    $update_status = $wpdb->update(
        $wpdb->prefix . 'survey_info',
        [
            'title'               => $title,
            'survey_description'  => $description,
            'instructions'        => $instructions,
            'send_email_message'  => $send_email_message,
            'email_message'       => $email_message,
            'send_pdf_report'     => $send_pdf_report,
            'survey_layout'       => $survey_layout,
            'updated_at'          => current_time('mysql'),
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

