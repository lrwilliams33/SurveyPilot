<?php
if (!defined('ABSPATH')) exit;
?>
<link rel="stylesheet" href="<?php echo esc_url(SP_URL . 'templates/user-templates/styles.css'); ?>">
<?php

global $wpdb;

$survey = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT id, title, survey_description 
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

$current_user = wp_get_current_user();
$name = $current_user->display_name;
$email = $current_user->user_email;

$title    = $survey['title'] ?? 'Survey';
$description = $survey['survey_description'] ?? '';
$icon_url = esc_url(SP_URL . 'assets/icons/info-circle.svg');
?>

<div class="sp-container">

    <div class="sp-icon-row">
        <h1><?php echo esc_html($title); ?></h1>
    </div>

    <div class="sp-icon-row">
        <h3>General Information</h3>
    </div>

    <?php
    $survey_url = esc_url(add_query_arg(
        ['sp_step' => 'survey', 'sp_survey_id' => (int) $sp_survey_id],
        get_permalink()
    ));
    ?>

    <form method="post" action="<?php echo $survey_url; ?>">

        <input type="hidden" name="sp_survey_id" value="<?php echo (int) $sp_survey_id; ?>" />

        <div class="sp-form-group">
            <label for="sp_name">Name</label>
            <input type="text" id="sp_name" name="sp_name" value="<?php echo esc_attr($name); ?>" readonly />
        </div>

        <div class="sp-form-group">
            <label for="sp_email">Email</label>
            <input type="email" id="sp_email" name="sp_email" value="<?php echo esc_attr($email); ?>" readonly />
        </div>

        <button type="submit" class="sp-button">Next &rarr;</button>

    </form>

</div>