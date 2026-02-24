<?php

function sp_render_dashboard() {
    include SP_PATH . 'templates/admin/dashboard.php';
}

function sp_render_create_survey_page() {
    global $wpdb;

    $survey = null;
    $is_edit = false;
    $questions = [];

    if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
        $survey_id = intval($_GET['id']);

        if ($survey_id > 0) {
            $survey = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}survey_info WHERE id = %d",
                    $survey_id
                ),
                ARRAY_A
            );

            if ($survey) {
                $is_edit = true;

                $questions = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}survey_questions WHERE survey_id = %d ORDER BY question_order ASC, id ASC",
                        $survey_id
                    ),
                    ARRAY_A
                );
            }
        }
    }

    include SP_PATH . 'templates/admin/create-survey.php';
}