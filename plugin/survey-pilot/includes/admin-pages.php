<?php

if (!defined('ABSPATH')) {
    exit;
}

// Render admin dashboard
function sp_render_dashboard() {
    include SP_PATH . 'templates/admin-templates/dashboard.php';
}

// Render create survey screen (or edit survey screen)
function sp_render_create_survey_page() {
    global $wpdb;

    $survey = null;
    $is_edit = false;
    $questions = [];

    $sp_survey_response_count = 0;

    // If edit survey screen is requested
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

                $sp_survey_response_count = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}survey_response_info WHERE survey_id = %d",
                        $survey_id
                    )
                );
            }
        }
    }

    include SP_PATH . 'templates/admin-templates/create-survey.php';
}
