<?php
if (!defined('ABSPATH')) exit;
$start_url = esc_url(add_query_arg(['sp_step' => 'start', 'sp_survey_id' => (int) $sp_survey_id], get_permalink()));
?>
<link rel="stylesheet" href="<?php echo esc_url(SP_URL . 'templates/user-templates/styles.css'); ?>">

<div class="sp-container">

    <div class="sp-success-icon">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M20 6L9 17L4 12" stroke="#155724" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>

    <div class="sp-confirmation-text">
        <h2>Thank You!</h2>
        <p>Your response has been successfully submitted.</p>
    </div>

    <div class="sp-confirmation-actions">
        <a href="<?php echo $start_url; ?>" class="sp-button">Retake Survey</a>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const surveyId = <?php echo (int) $sp_survey_id; ?>;
    localStorage.removeItem('sp_survey_answers_' + surveyId);
    localStorage.removeItem('sp_survey_answers_expiry_' + surveyId);
});
</script>