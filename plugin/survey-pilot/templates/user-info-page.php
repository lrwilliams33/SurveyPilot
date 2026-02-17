<?php
$current_user = wp_get_current_user();
$name = $current_user->display_name;
$email = $current_user->user_email;
?>

<div class="sp-container">
    <h2>General Information</h2>

    <?php
    $survey_url = esc_url(add_query_arg('sp_step', 'survey', get_permalink()));
    ?>
    <form method="post" action="<?php echo $survey_url; ?>">
        <label>Name:</label>
        <input type="text" name="sp_name" value="<?php echo esc_attr($name); ?>" required />

        <label>Email:</label>
        <input type="email" name="sp_email" value="<?php echo esc_attr($email); ?>" required />

        <button type="submit" class="sp-button">Next</button>
    </form>
</div>
