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

    if(!is_user_logged_in()) {
        wp_die('You must be logged in to submit the survey.');
    }

    $user_id = get_current_user_id();
    //post the survey responses to the respective database tables
    $response_id = sp_save_survey_submission($survey_id, $clean_answers, $user_id);
    //send the survey response email to the user
    sp_send_survey_email($response_id, $survey_id, $user_id);

    if (is_wp_error($response_id)) {
        wp_die($response_id->get_error_message());
    }

    //after saving the survey response and sending the email, redirect the user to the confirmation page
    $redirect = wp_get_referer();

    if (!$redirect) {
        $redirect = home_url('/');
    }

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
            "SELECT q.question_text, q.scale_labels, a.answer_value
             FROM $answers_table a
             JOIN $questions_table q
             ON a.question_id = q.id
             WHERE a.response_id = %d
             ORDER BY q.question_order ASC",
            $response_id
        )
    );


    //generate email content with the survey title, questions and answers in a table format  
    $message = '<h2>Survey Report</h2>';
    $message .= '<p>Thank you for completing the survey: <strong>' . esc_html($survey_title) . '</strong></p>';

    $message .= '<table style="border-collapse:collapse;width:100%;">';
    $message .= '<tr>
                    <th style="border:1px solid #ccc;padding:8px;text-align:left;">Question</th>
                    <th style="border:1px solid #ccc;padding:8px;text-align:left;">Answer</th>
                </tr>';

    //for each question and answer, we need to check if the question has scale labels
    //if it does we need to convert the answer value to the corresponding label before displaying in the email        
    foreach ($results as $row) {

        $labels = json_decode($row->scale_labels, true);

        $answer_text = $row->answer_value;

        if (isset($labels[$row->answer_value])) {
            $answer_text = $labels[$row->answer_value];
        }

        $message .= '<tr>';
        $message .= '<td style="border:1px solid #ccc;padding:8px;">' . esc_html($row->question_text) . '</td>';
        $message .= '<td style="border:1px solid #ccc;padding:8px;">' . esc_html($answer_text) . '</td>';
        $message .= '</tr>';
    }

    $message .= '</table>';

    $subject = 'Your Survey Submission: ' . $survey_title;

    $headers = ['Content-Type: text/html; charset=UTF-8'];

    error_log('SP: sending to email=' . $user_email);
    error_log('SP: about to call wp_mail');
    $sent = wp_mail($user_email, $subject, $message, $headers);
    error_log('SP: wp_mail sent: ' . ($sent ? 'true' : 'false'));
}