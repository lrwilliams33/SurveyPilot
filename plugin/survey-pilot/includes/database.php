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
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

     //create table to track survey questions, linked to a specific survey by survey_id foreign key
    $survey_questions = $wpdb->prefix . 'survey_questions';
    $sql_survey_questions = "CREATE TABLE $survey_questions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        survey_id BIGINT UNSIGNED NOT NULL,
        question_title VARCHAR(255) NULL,
        question_text TEXT NOT NULL,
        scale_min TINYINT UNSIGNED NOT NULL,
        scale_max TINYINT UNSIGNED NOT NULL,
        scale_labels LONGTEXT NULL,
        question_order INT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
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

function sp_add_survey_info_row($title, $description = null, $instructions = null) {
    global $wpdb;

    $title = sanitize_text_field($title);
    $description = sanitize_textarea_field($description);
    $instructions = sanitize_textarea_field($instructions);

    $insert_status = $wpdb->insert(
        $wpdb->prefix . 'survey_info',
        [
            'title' => $title,
            'survey_description' => $description,
            'instructions' => $instructions
        ],
        [
            '%s',
            '%s',
            '%s'
        ]
    );

    if ($insert_status === false) {
        //alter these to be redirects, we don't have anything yet to display msgs
        return new WP_Error('db_insert_error', 'Failed to insert survey info into the database');
    }
    
    //redirect, not msg
    return $wpdb->insert_id;
}

function sp_update_survey_info_row($survey_id, $title, $description = null, $instructions = null) {
    global $wpdb;

    $survey_id = intval($survey_id);
    if ($survey_id <= 0) {
        return new WP_Error('invalid_survey_id', 'Invalid survey ID provided');
    }

    $title = sanitize_text_field($title);
    $description = sanitize_textarea_field($description);
    $instructions = sanitize_textarea_field($instructions);

    $update_status = $wpdb->update(
        $wpdb->prefix . 'survey_info',
        [
            'title' => $title,
            'survey_description' => $description,
            'instructions' => $instructions
        ],
        [
            'id' => $survey_id
        ],
        [
            '%s',
            '%s',
            '%s'
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

function sp_add_survey_question_row(
    $survey_id, 
    $question_text,
    $question_order,
    $scale_min = 1, 
    $scale_max = 5, 
    $question_title = null, 
    $scale_labels = null
    ) 
    {
    global $wpdb;

    $survey_id = intval($survey_id);
    $question_title = ($question_title !== null && $question_title !== '') ? sanitize_text_field($question_title) : null;
    $question_text = sanitize_textarea_field($question_text);
    $scale_min = intval($scale_min);
    $scale_max = intval($scale_max);
    $scale_labels = ($scale_labels !== null && $scale_labels !== '') ? sanitize_textarea_field($scale_labels) : null;
    $question_order = intval($question_order);

    if ($survey_id <= 0) {
        return new WP_Error('invalid_survey_id', 'Invalid survey ID provided');
    }

    if (trim($question_text) === '') {
        return new WP_Error('empty_question_text', 'Question text cannot be empty');
    }

    if ($scale_min < 1 || $scale_max < 1 || $scale_min >= $scale_max) {
        return new WP_Error('invalid_scale', 'Scale min and max values are invalid');
    }

    if ($question_order <= 0) {
        return new WP_Error('invalid_question_order', 'Question order must be a non-negative integer');
    }

    $insert_status = $wpdb->insert(
        $wpdb->prefix . 'survey_questions',
        [
            'survey_id' => $survey_id,
            'question_title' => $question_title,
            'question_text' => $question_text,
            'scale_min' => $scale_min,
            'scale_max' => $scale_max,
            'scale_labels' => $scale_labels,
            'question_order' => $question_order
        ],
        [
            '%d',
            '%s',
            '%s',
            '%d',
            '%d',
            '%s',
            '%d'
        ]
    );

    if ($insert_status === false) {
        return new WP_Error('db_insert_error', 'Failed to insert survey question into the database');
    }

    return $wpdb->insert_id;
}


