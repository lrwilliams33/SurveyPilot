<?php
if (!defined('ABSPATH')) exit;
?>
<link rel="stylesheet" href="<?php echo esc_url(SP_URL . 'templates/user-templates/styles.css'); ?>">
<?php

global $wpdb;

$sp_survey_id = isset($sp_survey_id) ? absint($sp_survey_id) : 0;

if ($sp_survey_id <= 0) {
    echo '<div class="sp-container"><p class="sp-notice">Survey not specified.</p></div>';
    return;
}

$survey = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT id, title, survey_description, instructions
         FROM {$wpdb->prefix}survey_info
         WHERE id = %d
         LIMIT 1",
        $sp_survey_id
    ),
    ARRAY_A
);

if (!$survey) {
    echo '<div class="sp-container"><p class="sp-notice">Survey not found.</p></div>';
    return;
}

$title        = $survey['title'] ?? 'Survey';
$description  = $survey['survey_description'] ?? '';
$instructions = $survey['instructions'] ?? '';
$icon_url     = esc_url(SP_URL . 'assets/images/info-circle.svg');
$is_logged_in  = is_user_logged_in();

if ($is_logged_in) {
    sp_unlock_info_step($sp_survey_id);
}
?>

<div class="sp-container">

    <div class="sp-icon-row">
        <h1><?php echo esc_html($title); ?></h1>
    </div>

    <?php if (!empty($description)) : ?>
        <div class="sp-icon-row">
            <p class="sp-description"><?php echo esc_html($description); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($instructions)) : ?>
        <div class="sp-instructions">
            <div class="sp-icon-row">
                <img src="<?php echo $icon_url; ?>" class="sp-info-icon" alt="" aria-hidden="true">
                <h3>Instructions</h3>
            </div>
            <div class="sp-instructions-body">
                <?php echo wp_kses_post(nl2br(esc_html($instructions))); ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($is_logged_in) :
        $info_url = esc_url(add_query_arg(
            ['sp_step' => 'info', 'sp_survey_id' => (int) $sp_survey_id],
            get_permalink()
        ));
    ?>
        <a href="<?php echo $info_url; ?>" class="sp-button">Begin Survey</a>
    <?php else : ?>
        <div class="sp-notice">You must be logged in to begin this survey.</div>
    <?php endif; ?>
</div>