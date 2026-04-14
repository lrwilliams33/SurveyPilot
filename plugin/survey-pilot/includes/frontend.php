<?php

// Start a user session if not already started
function sp_ensure_session_started() {
    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent()) {
            ini_set('session.use_cookies', '1');
            ini_set('session.use_only_cookies', '1');
            session_start();
        } else {
            error_log('SP: Session could not start because headers were already sent.');
        }
    }
}
add_action('plugins_loaded', 'sp_ensure_session_started', 1);
add_action('init', 'sp_ensure_session_started', 1);

// Build session key used to track user's step/page state
function sp_get_flow_session_key($survey_id) {
    return 'sp_survey_flow_' . absint($survey_id);
}

// Get the first page of a survey
function sp_get_first_survey_page($survey_id) {
    global $wpdb;

    $questions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT *
             FROM {$wpdb->prefix}survey_questions
             WHERE survey_id = %d
             ORDER BY question_order ASC, id ASC",
            $survey_id
        ),
        ARRAY_A
    );

    if (!$questions) {
        return 1;
    }

    $survey_info = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT survey_layout FROM {$wpdb->prefix}survey_info WHERE id = %d",
            $survey_id
        ),
        ARRAY_A
    );

    $resolved = sp_user_resolve_survey_pages_and_headers(
        $questions,
        ($survey_info && !empty($survey_info['survey_layout'])) ? $survey_info['survey_layout'] : null
    );

    $all_page_numbers = $resolved['all_page_numbers'] ?? [];

    if (empty($all_page_numbers)) {
        return 1;
    }

    sort($all_page_numbers, SORT_NUMERIC);
    return (int) reset($all_page_numbers);
}

// Get the last page of a survey
function sp_get_last_survey_page($survey_id) {
    global $wpdb;

    $questions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT *
             FROM {$wpdb->prefix}survey_questions
             WHERE survey_id = %d
             ORDER BY question_order ASC, id ASC",
            $survey_id
        ),
        ARRAY_A
    );

    if (!$questions) {
        return 1;
    }

    $survey_info = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT survey_layout FROM {$wpdb->prefix}survey_info WHERE id = %d",
            $survey_id
        ),
        ARRAY_A
    );

    $resolved = sp_user_resolve_survey_pages_and_headers(
        $questions,
        ($survey_info && !empty($survey_info['survey_layout'])) ? $survey_info['survey_layout'] : null
    );

    $all_page_numbers = $resolved['all_page_numbers'] ?? [];

    if (empty($all_page_numbers)) {
        return 1;
    }

    sort($all_page_numbers, SORT_NUMERIC);
    return (int) end($all_page_numbers);
}

function sp_get_survey_flow($survey_id) {
    $survey_id = absint($survey_id);
    $session_key = sp_get_flow_session_key($survey_id);

    if (!isset($_SESSION[$session_key]) || !is_array($_SESSION[$session_key])) {
        $_SESSION[$session_key] = [
            'allowed_step' => 'start',
            'allowed_page' => sp_get_first_survey_page($survey_id),
            'completed'    => false,
        ];
    }

    return $_SESSION[$session_key];
}

function sp_set_survey_flow($survey_id, array $flow) {
    $survey_id = absint($survey_id);
    $session_key = sp_get_flow_session_key($survey_id);
    $_SESSION[$session_key] = $flow;
}

function sp_reset_survey_flow($survey_id) {
    $survey_id = absint($survey_id);
    $first_page = sp_get_first_survey_page($survey_id);

    sp_set_survey_flow($survey_id, [
        'allowed_step' => 'start',
        'allowed_page' => $first_page,
        'completed'    => false,
    ]);
}

function sp_unlock_info_step($survey_id) {
    $flow = sp_get_survey_flow($survey_id);
    $flow['allowed_step'] = 'info';
    sp_set_survey_flow($survey_id, $flow);
}

function sp_unlock_survey_step($survey_id) {
    $flow = sp_get_survey_flow($survey_id);
    $flow['allowed_step'] = 'survey';
    if (empty($flow['allowed_page'])) {
        $flow['allowed_page'] = sp_get_first_survey_page($survey_id);
    }
    sp_set_survey_flow($survey_id, $flow);
}

function sp_unlock_next_survey_page($survey_id, $current_page) {
    $flow = sp_get_survey_flow($survey_id);
    $last_page = sp_get_last_survey_page($survey_id);
    $next_page = min($last_page, absint($current_page) + 1);

    if ($next_page > (int) $flow['allowed_page']) {
        $flow['allowed_page'] = $next_page;
    }

    $flow['allowed_step'] = 'survey';
    sp_set_survey_flow($survey_id, $flow);
}

function sp_mark_survey_complete($survey_id) {
    $flow = sp_get_survey_flow($survey_id);
    $flow['allowed_step'] = 'confirmation';
    $flow['allowed_page'] = sp_get_last_survey_page($survey_id);
    $flow['completed'] = true;
    sp_set_survey_flow($survey_id, $flow);
}

function sp_validate_requested_flow($survey_id, $requested_step, $requested_page = 0) {
    $flow = sp_get_survey_flow($survey_id);
    $requested_step = in_array($requested_step, ['start', 'info', 'survey', 'confirmation'], true)
        ? $requested_step
        : 'start';

    $allowed_step = $flow['allowed_step'];
    $allowed_page = (int) $flow['allowed_page'];
    $completed    = !empty($flow['completed']);

    if ($completed && in_array($requested_step, ['info', 'survey'], true)) {
        return [
            'allowed' => false,
            'redirect_step' => 'confirmation',
        ];
    }

    if ($requested_step === 'start') {
        return ['allowed' => true];
    }

    if ($requested_step === 'info') {
        if (in_array($allowed_step, ['info', 'survey', 'confirmation'], true) || $completed) {
            return ['allowed' => true];
        }

        return [
            'allowed' => false,
            'redirect_step' => 'start',
        ];
    }

    if ($requested_step === 'survey') {
        if (!in_array($allowed_step, ['survey', 'confirmation'], true) && !$completed) {
            return [
                'allowed' => false,
                'redirect_step' => 'start',
            ];
        }

        $requested_page = absint($requested_page);
        if ($requested_page <= 0) {
            $requested_page = sp_get_first_survey_page($survey_id);
        }

        if ($requested_page > $allowed_page && !$completed) {
            return [
                'allowed' => false,
                'redirect_step' => 'survey',
                'redirect_page' => $allowed_page,
            ];
        }

        return ['allowed' => true];
    }

    if ($requested_step === 'confirmation') {
        if ($completed || $allowed_step === 'confirmation') {
            return ['allowed' => true];
        }

        if ($allowed_step === 'survey') {
            return [
                'allowed' => false,
                'redirect_step' => 'survey',
                'redirect_page' => $allowed_page,
            ];
        }

        if ($allowed_step === 'info') {
            return [
                'allowed' => false,
                'redirect_step' => 'info',
            ];
        }

        return [
            'allowed' => false,
            'redirect_step' => 'start',
        ];
    }

    return [
        'allowed' => false,
        'redirect_step' => 'start',
    ];
}

// Get the IDs of the questions for a specified page number
function sp_get_question_ids_for_page($survey_id, $page_number) {
    global $wpdb;

    $questions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT *
             FROM {$wpdb->prefix}survey_questions
             WHERE survey_id = %d
             ORDER BY question_order ASC, id ASC",
            $survey_id
        ),
        ARRAY_A
    );

    if (!$questions) {
        return [];
    }

    $survey_info = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT survey_layout FROM {$wpdb->prefix}survey_info WHERE id = %d",
            $survey_id
        ),
        ARRAY_A
    );

    $resolved = sp_user_resolve_survey_pages_and_headers(
        $questions,
        ($survey_info && !empty($survey_info['survey_layout'])) ? $survey_info['survey_layout'] : null
    );

    $pages = $resolved['pages'] ?? [];

    if (empty($pages[$page_number])) {
        return [];
    }

    $question_ids = [];
    foreach ($pages[$page_number] as $q) {
        if (!empty($q['id'])) {
            $question_ids[] = (int) $q['id'];
        }
    }

    return $question_ids;
}

// Render the survey shortcode and route user to the proper survey step
function sp_render_survey($atts) {
    global $wpdb;

    $atts = shortcode_atts(['name' => '', 'id' => ''], $atts, 'survey_pilot');
    $step = isset($_GET['sp_step']) ? sanitize_text_field($_GET['sp_step']) : 'start';
    $valid_steps = ['start', 'info', 'survey', 'confirmation'];

    $sp_survey_id = 0;

    if (!empty($atts['name'])) {
        $survey_title = trim((string) wp_unslash($atts['name']));
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

    if (!in_array($step, $valid_steps, true)) {
        $flow = sp_get_survey_flow($sp_survey_id);
        $redirect_step = isset($flow['allowed_step']) && in_array($flow['allowed_step'], $valid_steps, true)
            ? $flow['allowed_step']
            : 'start';

        $redirect_args = [
            'sp_survey_id' => $sp_survey_id,
            'sp_step'      => $redirect_step,
        ];

        if ($redirect_step === 'survey') {
            $redirect_args['sp_page'] = (int) ($flow['allowed_page'] ?? sp_get_first_survey_page($sp_survey_id));
        }

        wp_safe_redirect(add_query_arg($redirect_args, get_permalink()));
        exit;
    }

    if ($step === 'start' && isset($_GET['sp_survey_id'])) {
        sp_reset_survey_flow($sp_survey_id);
    }

    $requested_page = isset($_GET['sp_page']) ? absint($_GET['sp_page']) : 0;
    $validation = sp_validate_requested_flow($sp_survey_id, $step, $requested_page);

    if (!$validation['allowed']) {
        $redirect_args = [
            'sp_survey_id' => $sp_survey_id,
            'sp_step'      => $validation['redirect_step'],
        ];

        if (!empty($validation['redirect_page'])) {
            $redirect_args['sp_page'] = (int) $validation['redirect_page'];
        }

        wp_safe_redirect(add_query_arg($redirect_args, get_permalink()));
        exit;
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

add_shortcode('survey_pilot', 'sp_render_survey');

add_action('admin_post_sp_submit_survey', 'sp_handle_submit_survey');

add_action('wp_mail_failed', function ($wp_error) {
    error_log('SP: wp_mail_failed fired');
    error_log('SP: error message=' . $wp_error->get_error_message());
    error_log('SP: error data=' . print_r($wp_error->get_error_data(), true));
});

// Validate and save survey submission
function sp_handle_submit_survey() {
    error_log('Submission handling started');
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'sp_submit_survey')) {
        wp_die('Security check failed');
    }
    $survey_id = isset($_POST['sp_survey_id']) ? absint($_POST['sp_survey_id']) : 0;
    $answers = isset($_POST['sp_answers']) ? (array) $_POST['sp_answers'] : [];
    $navigation_action = isset($_POST['sp_navigation_action'])
        ? sanitize_text_field($_POST['sp_navigation_action'])
        : '';
    $current_page = isset($_POST['sp_current_page']) ? absint($_POST['sp_current_page']) : 0;
    $is_final_submission = isset($_POST['is_final_submission']) ? absint($_POST['is_final_submission']) : 0;
    $return_url = isset($_POST['sp_return_url']) ? esc_url_raw(wp_unslash($_POST['sp_return_url'])) : '';

    if (empty($return_url)) {
        $return_url = wp_get_referer();
    }

    $return_url = wp_validate_redirect($return_url, home_url('/'));

    if ($survey_id <= 0) {
        wp_die('Invalid survey ID');
    }

    $session_key = 'sp_survey_answers_' . $survey_id;
    if (isset($_SESSION[$session_key]) && is_array($_SESSION[$session_key])) {
        $answers = array_replace($_SESSION[$session_key], $answers);
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

    if (!$is_final_submission && $navigation_action === 'next') {
        $flow = sp_get_survey_flow($survey_id);
        $allowed_page = (int) ($flow['allowed_page'] ?? 1);
        $posted_answers = isset($_POST['sp_answers']) ? (array) $_POST['sp_answers'] : [];
        $redirect_base = $return_url;

        if ($current_page <= 0 || $current_page > $allowed_page) {
            wp_safe_redirect(add_query_arg([
                'sp_survey_id' => $survey_id,
                'sp_step'      => 'survey',
                'sp_page'      => $allowed_page,
            ], $redirect_base));
            exit;
        }

        $required_question_ids = sp_get_question_ids_for_page($survey_id, $current_page);
        if (empty($required_question_ids)) {
            wp_safe_redirect(add_query_arg([
                'sp_survey_id' => $survey_id,
                'sp_step'      => 'survey',
                'sp_page'      => $current_page,
                'sp_incomplete' => 1,
            ], $redirect_base));
            exit;
        }

        $missing_for_current_page = [];
        foreach ($required_question_ids as $qid) {
            $has_posted_answer = array_key_exists((string) $qid, $posted_answers) || array_key_exists($qid, $posted_answers);
            $has_clean_answer  = isset($clean_answers[$qid]);

            if (!$has_posted_answer && !$has_clean_answer) {
                $missing_for_current_page[] = (int) $qid;
            }
        }

        if (!empty($missing_for_current_page)) {
            $first_unanswered_id = (int) reset($missing_for_current_page);

            wp_safe_redirect(add_query_arg([
                'sp_survey_id'             => $survey_id,
                'sp_step'                  => 'survey',
                'sp_page'                  => $current_page,
                'sp_incomplete'            => 1,
                'sp_first_unanswered'      => $first_unanswered_id,
                'sp_first_unanswered_page' => $current_page,
            ], $redirect_base));
            exit;
        }

        $session_key = 'sp_survey_answers_' . $survey_id;
        if (!isset($_SESSION[$session_key]) || !is_array($_SESSION[$session_key])) {
            $_SESSION[$session_key] = [];
        }

        foreach ($clean_answers as $qid => $val) {
            $_SESSION[$session_key][$qid] = $val;
        }

        sp_unlock_next_survey_page($survey_id, $current_page);

        $next_page = $current_page + 1;
        $redirect = add_query_arg([
            'sp_survey_id' => $survey_id,
            'sp_step'      => 'survey',
            'sp_page'      => $next_page,
        ], $redirect_base);

        wp_safe_redirect($redirect);
        exit;
    }

    global $wpdb;
    $questions_table = $wpdb->prefix . 'survey_questions';

    $expected_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT id FROM $questions_table WHERE survey_id = %d ORDER BY question_order ASC, id ASC",
            $survey_id
        )
    );

    if (empty($expected_ids)) {
        wp_safe_redirect(add_query_arg([
            'sp_survey_id'        => $survey_id,
            'sp_step'             => 'survey',
            'sp_page'             => max(1, $current_page),
            'sp_submit_error'     => 1,
            'sp_submit_error_msg' => rawurlencode('Survey questions could not be loaded. Please try again.'),
        ], $return_url));
        exit;
    }

    $expected_ids = array_map('intval', $expected_ids);
    $expected_id_lookup = array_fill_keys($expected_ids, true);
    $clean_answers = array_intersect_key($clean_answers, $expected_id_lookup);
    $answered_ids = array_map('intval', array_keys($clean_answers));
    $missing_ids  = array_diff($expected_ids, $answered_ids);

    if (!empty($missing_ids)) {
        if (!function_exists('sp_get_question_id_to_page_map')) {
            wp_safe_redirect(add_query_arg([
                'sp_survey_id'        => $survey_id,
                'sp_step'             => 'survey',
                'sp_page'             => max(1, $current_page),
                'sp_submit_error'     => 1,
                'sp_submit_error_msg' => rawurlencode('Survey layout helper is missing. Please contact support.'),
            ], $return_url));
            exit;
        }

        $id_to_page = sp_get_question_id_to_page_map($survey_id);

        if (empty($id_to_page)) {
            wp_safe_redirect(add_query_arg([
                'sp_survey_id'        => $survey_id,
                'sp_step'             => 'survey',
                'sp_page'             => max(1, $current_page),
                'sp_submit_error'     => 1,
                'sp_submit_error_msg' => rawurlencode('Survey layout is not configured correctly. Please contact support.'),
            ], $return_url));
            exit;
        }

        $first_unanswered_id   = null;
        $first_unanswered_page = null;

        foreach ($expected_ids as $qid) {
            if (!in_array($qid, $missing_ids, true)) {
                continue;
            }

            $page_num = 1;
            if (isset($id_to_page[$qid]) && is_array($id_to_page[$qid]) && isset($id_to_page[$qid]['page'])) {
                $page_num = (int) $id_to_page[$qid]['page'];
            }

            if ($first_unanswered_page === null || $page_num < $first_unanswered_page) {
                $first_unanswered_page = $page_num;
                $first_unanswered_id   = (int) $qid;
            }
        }

        if ($first_unanswered_page === null) {
            $first_unanswered_page = max(1, $current_page);
        }

        $redirect = $return_url;

        $redirect = add_query_arg([
            'sp_survey_id'               => $survey_id,
            'sp_step'                    => 'survey',
            'sp_page'                    => max(1, $current_page),
            'sp_incomplete'              => 1,
            'sp_first_unanswered'        => (int) $first_unanswered_id,
            'sp_first_unanswered_page'   => (int) $first_unanswered_page,
        ], $redirect);

        wp_safe_redirect($redirect);
        exit;
    }

    if(!is_user_logged_in()) {
        wp_safe_redirect(add_query_arg([
            'sp_survey_id'        => $survey_id,
            'sp_step'             => 'survey',
            'sp_page'             => max(1, $current_page),
            'sp_submit_error'     => 1,
            'sp_submit_error_msg' => rawurlencode('You must be logged in to submit the survey.'),
        ], $return_url));
        exit;
    }

    $user_id = get_current_user_id();
    $response_id = sp_save_survey_submission($survey_id, $clean_answers, $user_id);

    if (is_wp_error($response_id)) {
        error_log('SP: Error saving survey submission: ' . $response_id->get_error_message());
        wp_safe_redirect(add_query_arg([
            'sp_survey_id'        => $survey_id,
            'sp_step'             => 'survey',
            'sp_page'             => max(1, $current_page),
            'sp_submit_error'     => 1,
            'sp_submit_error_msg' => rawurlencode('Error submitting survey. Please try again.'),
        ], $return_url));
        exit;
    }

    sp_send_survey_email($response_id, $survey_id, $user_id);

    sp_mark_survey_complete($survey_id);

    $redirect = $return_url;

    // Clear session data for this survey
    $session_key = 'sp_survey_answers_' . $survey_id;
    unset($_SESSION[$session_key]);

    wp_safe_redirect(add_query_arg([
        'sp_survey_id' => $survey_id,
        'sp_step'      => 'confirmation',
    ], $redirect));

exit;
}

// Send email message (and PDF report) if enabled
function sp_send_survey_email($response_id, $survey_id, $user_id) {
    global $wpdb;
    error_log('Email function started');

    $user = get_userdata($user_id);

    if (!$user) {
        return;
    }
    
    $user_email = $user->user_email;

    $survey_table = $wpdb->prefix . 'survey_info';
    $questions_table = $wpdb->prefix . 'survey_questions';
    $answers_table = $wpdb->prefix . 'survey_response_answers';

    $survey = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT title, send_email_message, email_message, send_pdf_report, pdf_report_logo_attachment_id FROM $survey_table WHERE id = %d",
            $survey_id
        )
    );

    // If email messaging is turned off for this survey, exit
    if (!$survey || empty((int) $survey->send_email_message)) {
        return;
    }

    $email_message = trim((string) $survey->email_message);
    if ($email_message === '') {
        return;
    }

    $include_pdf = !empty((int) $survey->send_pdf_report);

    $survey_title = $survey->title ? $survey->title : 'Survey';

    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT q.id AS question_id, q.question_text, q.scale_labels, a.answer_value
             FROM $answers_table a
             JOIN $questions_table q
             ON a.question_id = q.id
             WHERE a.response_id = %d
             ORDER BY q.question_order ASC",
            $response_id
        )
    );

    $id_to_page = function_exists('sp_get_question_id_to_page_map')
        ? sp_get_question_id_to_page_map($survey_id)
        : [];

    foreach ($results as $row) {
        $qid = (int) $row->question_id;
        $row->page_number = $id_to_page[ $qid ]['page'] ?? 1;
        $row->page_header = $id_to_page[ $qid ]['header'] ?? '';
    }

    $sample_means               = [];
    $formatted_individual_results   = [];

    if ($survey_id > 0) {

        $answers_table = $wpdb->prefix . 'survey_response_answers';
        $questions_table = $wpdb->prefix . 'survey_questions';

        $sample_raw = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT q.id AS question_id, a.answer_value
                 FROM $answers_table a
                 JOIN $questions_table q ON a.question_id = q.id
                 WHERE q.survey_id = %d",
                $survey_id
            )
        );

        $sums   = [];
        $counts = [];
        foreach ($sample_raw as $row) {
            $qid = (int) $row->question_id;
            $pn  = $id_to_page[ $qid ]['page'] ?? 1;
            if (!isset($sums[ $pn ])) {
                $sums[ $pn ]   = 0;
                $counts[ $pn ] = 0;
            }
            $sums[ $pn ]   += (int) $row->answer_value;
            $counts[ $pn ]++;
        }
        foreach ($sums as $pn => $total) {
            $sample_means[ $pn ] = $counts[ $pn ] > 0 ? (float) $total / $counts[ $pn ] : 0.0;
        }

        $individual_raw = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.response_id, q.id AS question_id, a.answer_value
                 FROM $answers_table a
                 JOIN $questions_table q ON a.question_id = q.id
                 WHERE q.survey_id = %d",
                $survey_id
            )
        );

        $resp_sums   = [];
        $resp_counts = [];
        foreach ($individual_raw as $row) {
            $rid = (int) $row->response_id;
            $qid = (int) $row->question_id;
            $pn  = $id_to_page[ $qid ]['page'] ?? 1;
            if (!isset($resp_sums[ $rid ])) {
                $resp_sums[ $rid ]   = [];
                $resp_counts[ $rid ] = [];
            }
            if (!isset($resp_sums[ $rid ][ $pn ])) {
                $resp_sums[ $rid ][ $pn ]   = 0;
                $resp_counts[ $rid ][ $pn ] = 0;
            }
            $resp_sums[ $rid ][ $pn ]   += (int) $row->answer_value;
            $resp_counts[ $rid ][ $pn ]++;
        }

        foreach ($resp_sums as $rid => $by_page) {
            foreach ($by_page as $pn => $total) {
                $c = $resp_counts[ $rid ][ $pn ] ?? 0;
                if ($c > 0) {
                    $formatted_individual_results[ $pn ][ $rid ] = (float) $total / $c;
                }
            }
        }
    }

    // Build email message from the admin-defined message
    $message = wpautop(esc_html($email_message));

    $subject = 'Your Survey Submission: ' . $survey_title;

    $headers = ['Content-Type: text/html; charset=UTF-8'];

    $attachments = [];

    if ($include_pdf) {
        $message .= '<p>Attached is a PDF report summarizing your responses and how they compare to others.</p>';
        $pdf_logo_id = isset($survey->pdf_report_logo_attachment_id)
            ? (int) $survey->pdf_report_logo_attachment_id
            : 0;
        $pdf_path = sp_generate_survey_pdf(
            $survey_title,
            $response_id,
            $results,
            $sample_means,
            $formatted_individual_results,
            $pdf_logo_id > 0 ? $pdf_logo_id : null
        );
        if (!is_wp_error($pdf_path)) {
            $attachments[] = $pdf_path;
        } else {
            error_log('SP: PDF generation failed: ' . $pdf_path->get_error_message());
            if ($pdf_path->get_error_code() === 'sp_no_dompdf') {
                error_log('SP: Dompdf library is missing. Please run composer install to include dependencies.');
            }
        }
    }

    error_log('SP: sending to email=' . $user_email);
    error_log('SP: about to call wp_mail');
    $sent = wp_mail($user_email, $subject, $message, $headers, $attachments);
    error_log('SP: wp_mail sent: ' . ($sent ? 'true' : 'false'));

    // Clean up temporarily generated PDF files after sending
    if (!empty($attachments)) {
        foreach ($attachments as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}

// Save in-progress response to user's session storage
function sp_save_answer_ajax() {
    if (is_user_logged_in()) {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sp_submit_survey')) {
            error_log('SP AJAX: Nonce verification failed for logged-in user');
            wp_send_json_error('Security check failed');
        }
    }

    $survey_id = isset($_POST['survey_id']) ? absint($_POST['survey_id']) : 0;
    $question_id = isset($_POST['question_id']) ? absint($_POST['question_id']) : 0;
    $answer_value = isset($_POST['answer_value']) ? absint($_POST['answer_value']) : 0;

    error_log('SP AJAX: survey_id=' . $survey_id . ', question_id=' . $question_id . ', answer_value=' . $answer_value);

    if ($survey_id <= 0 || $question_id <= 0 || $answer_value < 0) {
        wp_send_json_error('Invalid parameters');
    }

    $session_key = 'sp_survey_answers_' . $survey_id;
    if (!isset($_SESSION[$session_key])) {
        $_SESSION[$session_key] = [];
    }

    $_SESSION[$session_key][$question_id] = $answer_value;

    error_log('SP AJAX: Answer saved. Session data: ' . print_r($_SESSION[$session_key], true));

    wp_send_json_success('Answer saved');
}

add_action('wp_ajax_sp_save_answer', 'sp_save_answer_ajax');
add_action('wp_ajax_nopriv_sp_save_answer', 'sp_save_answer_ajax');
