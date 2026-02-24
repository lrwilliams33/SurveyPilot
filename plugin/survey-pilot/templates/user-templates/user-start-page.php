<div class="sp-container">
    <h1>Customer Feedback Survey</h1>
    
    <p class="sp-description">
        We appreciate your time in completing this survey. Your responses help us improve our services.
    </p>

    <div class="sp-instructions">
        <h3>Instructions</h3>
        <ul>
            <li>This survey will take about 3-5 minutes</li>
            <li>Answer honestly using the Likert scale</li>
            <li>You can retake the survey after submission</li>
        </ul>
    </div>

    <?php
    $info_url = esc_url(add_query_arg(['sp_step' => 'info', 'sp_survey_id' => (int) $sp_survey_id], get_permalink()));
    ?>
    <a href="<?php echo $info_url; ?>" class="sp-button">Begin Survey</a>
</div>
