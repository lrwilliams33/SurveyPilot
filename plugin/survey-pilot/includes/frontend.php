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
