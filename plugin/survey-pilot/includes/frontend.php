<?php
// includes/frontend.php

/**
 * Shortcode: [survey_pilot name="My Survey"] to display a survey by its title.
 * The name attribute is required so the correct survey is shown on the page.
 * For step navigation the resolved id is passed via sp_survey_id in the URL.
 */

function sp_render_survey($atts) {
    global $wpdb;

    $atts = shortcode_atts(['name' => '', 'id' => ''], $atts, 'survey_pilot');
    $step = isset($_GET['sp_step']) ? sanitize_text_field($_GET['sp_step']) : 'start';

    // Resolve survey ID: name attribute → DB lookup; fallback to id attr or GET param.
    $sp_survey_id = 0;

    if (!empty($atts['name'])) {
        $survey_title = sanitize_text_field(wp_unslash($atts['name']));
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}survey_info WHERE title = %s LIMIT 1",
                $survey_title
            )
        );
        if ($row) {
            $sp_survey_id = (int) $row->id;
        }
    }

    if (!$sp_survey_id && !empty($atts['id'])) {
        $sp_survey_id = absint($atts['id']);
    }

    if (!$sp_survey_id) {
        $sp_survey_id = isset($_GET['sp_survey_id']) ? absint($_GET['sp_survey_id']) : 0;
    }

    ob_start();

    if ($sp_survey_id <= 0) {
        echo '<div class="sp-container"><p class="sp-notice">';
        echo esc_html__('Please specify which survey to display. Use the shortcode with the survey name, for example: [survey_pilot name="My Survey"]', 'survey-pilot');
        echo '</p></div>';
        return ob_get_clean();
    }

    switch ($step) {
        case 'info':
            $template_file = SP_PATH . 'templates/user-templates/user-info-page.php';
            break;

        case 'survey':
            $template_file = SP_PATH . 'templates/user-templates/user-survey-page.php';
            break;

        case 'confirmation':
            $template_file = SP_PATH . 'templates/user-templates/user-confirmation-page.php';
            break;

        case 'start':
        default:
            $template_file = SP_PATH . 'templates/user-templates/user-start-page.php';
            break;
    }

    if (file_exists($template_file)) {
        include $template_file;
    } else {
        echo '<div style="color:red;">SurveyPilot Error: Template file not found.<br>';
        echo 'Looking for: ' . esc_html($template_file) . '</div>';
    }

    return ob_get_clean();
}

// Register the shortcode
add_shortcode('survey_pilot', 'sp_render_survey');

//add wordpress hook, when we submit to admin-post.php with action sp_submit_survey, it will call the function sp_handle_submit_survey
//we are submitting to admin-post.php in user-survey-page.php, so we need to handle the form submission in this function
add_action('admin_post_sp_submit_survey', 'sp_handle_submit_survey');

add_action('wp_mail_failed', function ($wp_error) {
    error_log('SP: wp_mail_failed fired');
    error_log('SP: error message=' . $wp_error->get_error_message());
    error_log('SP: error data=' . print_r($wp_error->get_error_data(), true));
});

function sp_handle_submit_survey() {
    error_log('Submission handling started');
    //check to make sure the nonce token is valid, no attacker is submitting the form
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'sp_submit_survey')) {
        wp_die('Security check failed');
    }
    //get the survey ID and answers from the form submission
    $survey_id = isset($_POST['sp_survey_id']) ? absint($_POST['sp_survey_id']) : 0;
    $answers = isset($_POST['sp_answers']) ? (array) $_POST['sp_answers'] : [];

    if ($survey_id <= 0) {
        wp_die('Invalid survey ID');
    }

    // Merge session-stored answers with form submission answers
    // Session answers are from previous pages
    $session_key = 'sp_survey_answers_' . $survey_id;
    if (isset($_SESSION[$session_key]) && is_array($_SESSION[$session_key])) {
        $answers = array_merge($_SESSION[$session_key], $answers);
    }

    if (empty($answers)) {
        wp_die('No answers submitted');
    }

    $clean_answers = [];

    foreach ($answers as $question_id => $value) {
        $question_id = absint($question_id);
        $value = absint($value);

        if ($question_id > 0) {
            $clean_answers[$question_id] = $value;
        }
    }

    ksort($clean_answers, SORT_NUMERIC);

    if(!is_user_logged_in()) {
        wp_die('You must be logged in to submit the survey.');
    }

    $user_id = get_current_user_id();
    //post the survey responses to the respective database tables
    $response_id = sp_save_survey_submission($survey_id, $clean_answers, $user_id);

    if (is_wp_error($response_id)) {
        error_log('SP: Error saving survey submission: ' . $response_id->get_error_message());
        wp_die('Error submitting survey: ' . $response_id->get_error_message());
    }

    //send the survey response email to the user
    sp_send_survey_email($response_id, $survey_id, $user_id);

    //after saving the survey response and sending the email, redirect the user to the confirmation page
    $redirect = wp_get_referer();

    if (!$redirect) {
        $redirect = home_url('/');
    }

    // Clear session data for this survey
    $session_key = 'sp_survey_answers_' . $survey_id;
    unset($_SESSION[$session_key]);

    wp_safe_redirect(add_query_arg([
        'sp_survey_id' => $survey_id,
        'sp_step'      => 'confirmation',
    ], $redirect));

exit;
}

//send email to the user function
function sp_send_survey_email($response_id, $survey_id, $user_id) {
    global $wpdb;
    error_log('Email function started');

    //use get_userdata to access wp_users table and fetch email of user id
    $user = get_userdata($user_id);

    if (!$user) {
        return;
    }
    
    $user_email = $user->user_email;

    $survey_table = $wpdb->prefix . 'survey_info';
    $questions_table = $wpdb->prefix . 'survey_questions';
    $answers_table = $wpdb->prefix . 'survey_response_answers';

    //get the survey title for the survey submitted
    $survey = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT title FROM $survey_table WHERE id = %d",
            $survey_id
        )
    );

    $survey_title = $survey ? $survey->title : 'Survey';

    //get questions and answers for the submitted survey response
    //join the answers and questions table and fetch information using the response id 
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT q.question_text, q.scale_labels, q.page_number, a.answer_value
             FROM $answers_table a
             JOIN $questions_table q
             ON a.question_id = q.id
             WHERE a.response_id = %d
             ORDER BY q.question_order ASC",
            $response_id
        )
    );

    global $wpdb;


    $population_means = [];

    if ($survey_id > 0) {

        $answers_table = $wpdb->prefix . 'survey_response_answers';
        $questions_table = $wpdb->prefix . 'survey_questions';

        $population_results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                q.page_number,
                AVG(a.answer_value) AS avg_score
            FROM $answers_table a
            JOIN $questions_table q 
                ON a.question_id = q.id
            WHERE q.survey_id = %d
            GROUP BY q.page_number
        ", $survey_id));

        foreach ($population_results as $row) {
            $population_means[$row->page_number] = (float)$row->avg_score;
        }

        $all_individual_results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                a.response_id,
                q.page_number,
                AVG(a.answer_value) AS user_composite
            FROM $answers_table a
            JOIN $questions_table q 
                ON a.question_id = q.id
            WHERE q.survey_id = %d
            GROUP BY a.response_id, q.page_number
        ", $survey_id));

        $formatted_individual_results = [];
        foreach ($all_individual_results as $row) {
            $formatted_individual_results[$row->page_number][$row->response_id] = (float)$row->user_composite;
        }
    }


    //generate email content with the survey title, questions and answers in a table format  
    $message .= '<p>Thank you for completing the survey: <strong>' . esc_html($survey_title) . '</strong></p>';
    $message .= '<p>Attached below is a PDF report summarizing your responses and how they compare to others.</p>';

    $subject = 'Your Survey Submission: ' . $survey_title;

    $headers = ['Content-Type: text/html; charset=UTF-8'];

    //attachments field contains our PDF attachment from the upload_dir directory
    $attachments = [];
    //call the survey PDF generation function 
    $pdf_path = sp_generate_survey_pdf($survey_title, $response_id, $results, $population_means, $formatted_individual_results);
    if (!is_wp_error($pdf_path)) {
        $attachments[] = $pdf_path;
    }
    else{
        error_log('SP: PDF generation failed: ' . $pdf_path->get_error_message());
        if (is_wp_error($pdf_path) && $pdf_path->get_error_code() === 'sp_no_dompdf') {
            error_log('SP: Dompdf library is missing. Please run composer install to include dependencies.');
        }
    }

    error_log('SP: sending to email=' . $user_email);
    error_log('SP: about to call wp_mail');
    $sent = wp_mail($user_email, $subject, $message, $headers, $attachments);
    error_log('SP: wp_mail sent: ' . ($sent ? 'true' : 'false'));

    //since we uploaded files into wordpress for PDF attachements, we delete these files after sending them in the email
    if (!empty($attachments)) {
        foreach ($attachments as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}

/**
 * AJAX handler to save individual survey answers to session
 * Called via AJAX when each radio button is selected
 */
function sp_save_answer_ajax() {
    // Verify nonce for security (only for authenticated users)
    if (is_user_logged_in()) {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sp_submit_survey')) {
            error_log('SP AJAX: Nonce verification failed for logged-in user');
            wp_send_json_error('Security check failed');
        }
    }

    // Get and validate parameters
    $survey_id = isset($_POST['survey_id']) ? absint($_POST['survey_id']) : 0;
    $question_id = isset($_POST['question_id']) ? absint($_POST['question_id']) : 0;
    $answer_value = isset($_POST['answer_value']) ? absint($_POST['answer_value']) : 0;

    error_log('SP AJAX: survey_id=' . $survey_id . ', question_id=' . $question_id . ', answer_value=' . $answer_value);

    if ($survey_id <= 0 || $question_id <= 0 || $answer_value < 0) {
        wp_send_json_error('Invalid parameters');
    }

    // Initialize session storage array if it doesn't exist
    $session_key = 'sp_survey_answers_' . $survey_id;
    if (!isset($_SESSION[$session_key])) {
        $_SESSION[$session_key] = [];
    }

    // Save the answer to session
    $_SESSION[$session_key][$question_id] = $answer_value;

    error_log('SP AJAX: Answer saved. Session data: ' . print_r($_SESSION[$session_key], true));

    wp_send_json_success('Answer saved');
}

// Register AJAX action for both logged in and logged out users
add_action('wp_ajax_sp_save_answer', 'sp_save_answer_ajax');
add_action('wp_ajax_nopriv_sp_save_answer', 'sp_save_answer_ajax');