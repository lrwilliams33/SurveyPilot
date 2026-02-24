<div class="sp-container">
    <h2>Thank You!</h2>
    
    <p>Your survey has been successfully submitted.</p>

    <?php
    $start_url = esc_url(add_query_arg(['sp_step' => 'start', 'sp_survey_id' => (int) $sp_survey_id], get_permalink()));
    ?>
    <a href="<?php echo $start_url; ?>" class="sp-button">Retake Survey</a>
</div>
