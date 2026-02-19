<?php
// dashboard.php template

global $wpdb;
$surveys = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}survey_pilot_master_table ORDER BY created_at DESC", ARRAY_A);
?>

<div class="wrap sp-dashboard">
    <div class="sp-dashboard-header">
        <h1>SurveyPilot</h1>
        <a href="<?php echo admin_url('admin.php?page=sp-create-survey'); ?>" class="button button-primary sp-btn-large">+ Create Survey</a>
    </div>

    <hr>

    <div class="sp-dashboard-content">
        <!-- Left Column: Surveys -->
        <div class="sp-dashboard-left">
            <?php if (!empty($surveys)) : ?>
                <div class="sp-survey-list">
                    <?php foreach ($surveys as $survey) : ?>
                        <div class="sp-survey-card">
                            <div class="sp-survey-info">
                                <h2><?php echo esc_html($survey['title']); ?></h2>
                            </div>
                            <div class="sp-survey-actions">
                                <a href="<?php echo admin_url('admin.php?page=sp-create-survey&action=edit&id=' . intval($survey['id'])); ?>" class="button sp-btn-large">Edit</a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=survey-pilot&action=delete&id=' . intval($survey['id'])), 'sp_delete_survey_' . intval($survey['id'])); ?>" class="button button-secondary sp-btn-large" onclick="return confirm('Are you sure?');">Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p>No surveys created yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>