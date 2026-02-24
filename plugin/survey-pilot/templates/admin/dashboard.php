<?php
// dashboard.php template

global $wpdb;
$surveys = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}survey_info ORDER BY created_at DESC", ARRAY_A);
?>

<div class="wrap sp-dashboard">
    <div class="sp-dashboard-header">
        <div class="sp-dashboard-header-left">
            <h1>SurveyPilot Dashboard</h1>
            <a href="<?php echo admin_url('admin.php?page=sp-create-survey'); ?>" class="button button-primary sp-btn-large">+ Create Survey</a>
        </div>
        <div class="sp-dashboard-header-right" aria-hidden="true"></div>
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
                                <span class="sp-survey-title"><?php echo esc_html($survey['title']); ?></span>
                                <?php if (!empty($survey['survey_description'])) : ?>
                                    <span class="sp-survey-desc"><?php echo esc_html($survey['survey_description']); ?></span>
                                <?php endif; ?>
                                <span class="sp-survey-id">ID: <?php echo (int) $survey['id']; ?></span>
                            </div>
                            <div class="sp-survey-actions">
                                <a href="<?php echo admin_url('admin.php?page=sp-create-survey&action=edit&id=' . intval($survey['id'])); ?>" class="button sp-btn-large">Edit</a>
                                <?php $delete_url = wp_nonce_url(admin_url('admin.php?page=survey-pilot&action=delete&id=' . intval($survey['id'])), 'sp_delete_survey_' . intval($survey['id'])); ?>
                                <a href="<?php echo esc_url($delete_url); ?>" data-sp-delete-url="<?php echo esc_url($delete_url); ?>" class="button sp-btn-large sp-btn-danger-outline">Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p>No surveys created yet.</p>
            <?php endif; ?>
        </div>

        <!-- Right Column: Shortcode Help -->
        <div class="sp-dashboard-right">
            <h2>How To Use SurveyPilot</h2>
            <p>Each survey has an <strong>ID</strong>. To display a specific survey on a page or post, use the shortcode with that survey's <strong>ID</strong>:</p>
            <p class="sp-shortcode-block"><code>[survey_pilot id="1"]</code></p>
            <p>Replace <code>1</code> with the survey <strong>ID</strong> shown on each survey card:</p>
            <p class="sp-eg-line"><code>[survey_pilot id="2"]</code> for survey <strong>ID: 2</strong></p>
            <p>This will render that survey's flow (start, info, survey questions, confirmation) where the shortcode is placed.</p>
        </div>
    </div>
</div>

<div id="sp-delete-modal" class="sp-modal" aria-hidden="true">
    <div class="sp-modal-overlay" tabindex="-1"></div>
    <div class="sp-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="sp-delete-modal-title" tabindex="-1">
        <h2 id="sp-delete-modal-title">Delete survey?</h2>
        <p>This will permanently delete the survey and any associated questions/responses. This cannot be undone.</p>
        <div class="sp-modal-actions">
            <a href="#" class="button sp-btn-large" data-sp-cancel>Cancel</a>
            <a href="#" class="button sp-btn-large sp-btn-danger-outline" data-sp-confirm>Delete</a>
        </div>
    </div>
</div>