<?php
// includes/frontend.php

/**
 * Shortcode: [survey_pilot id="1"] to display the survey with ID 1.
 * The id attribute is required so the correct survey is shown on the page.
 */
function sp_render_survey($atts) {
    $atts = shortcode_atts(['id' => ''], $atts, 'survey_pilot');
    $step = isset($_GET['sp_step']) ? sanitize_text_field($_GET['sp_step']) : 'start';
    $survey_id_from_get = isset($_GET['sp_survey_id']) ? absint($_GET['sp_survey_id']) : 0;
    $survey_id_from_shortcode = !empty($atts['id']) ? absint($atts['id']) : 0;

    // Use shortcode id first, then GET (e.g. when navigating between steps).
    $sp_survey_id = $survey_id_from_shortcode ? $survey_id_from_shortcode : $survey_id_from_get;

    ob_start();

    if ($sp_survey_id <= 0) {
        echo '<div class="sp-container"><p class="sp-notice">';
        echo esc_html__('Please specify which survey to display. Use the shortcode with a survey ID, for example: [survey_pilot id="1"]', 'survey-pilot');
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