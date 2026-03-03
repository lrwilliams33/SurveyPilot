<?php
// dashboard.php template

global $wpdb;
$surveys = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}survey_info ORDER BY updated_at DESC", ARRAY_A);
?>

<div class="wrap sp-dashboard">
    <div class="sp-dashboard-header">
        <h1>SurveyPilot Dashboard</h1>
    </div>

    <hr>

    <div class="sp-dashboard-content">
        <!-- Left Column: Surveys -->
        <div class="sp-dashboard-left">
            <div class="sp-survey-list-header">
                <span class="sp-survey-list-label">Your Surveys</span>
                <div class="sp-list-header-right">
                    <div class="sp-sort-wrapper">
                        <label for="sp-sort-select" class="sp-sort-label">Sort by:</label>
                        <select id="sp-sort-select" class="sp-sort-select">
                            <option value="updated_desc">Last Updated (Newest First)</option>
                            <option value="updated_asc">Last Updated (Oldest First)</option>
                            <option value="created_desc">Date Created (Newest First)</option>
                            <option value="created_asc">Date Created (Oldest First)</option>
                            <option value="alpha_asc">A &#8594; Z</option>
                            <option value="alpha_desc">Z &#8594; A</option>
                            <option value="custom">Custom Order</option>
                        </select>
                        <script>
                        (function(){var s=localStorage.getItem('sp_sort_order');if(s){var el=document.getElementById('sp-sort-select');if(el)el.value=s;}})();
                        </script>
                    </div>
                    <a href="<?php echo admin_url('admin.php?page=sp-create-survey'); ?>" class="button button-primary sp-btn-large">+ Create Survey</a>
                </div>
            </div>
            <?php if (!empty($surveys)) : ?>
                <div class="sp-survey-list" style="visibility:hidden">
                    <?php foreach ($surveys as $survey) : ?>
                        <?php
                        $edit_url      = admin_url('admin.php?page=sp-create-survey&action=edit&id=' . intval($survey['id']));
                        $duplicate_url = wp_nonce_url(
                            admin_url('admin-post.php?action=sp_duplicate_survey&survey_id=' . intval($survey['id'])),
                            'sp_duplicate_survey_' . intval($survey['id'])
                        );
                        $delete_url = wp_nonce_url(
                            admin_url('admin.php?page=survey-pilot&action=delete&id=' . intval($survey['id'])),
                            'sp_delete_survey_' . intval($survey['id'])
                        );
                        ?>
                        <div class="sp-survey-card"
                             data-edit-url="<?php echo esc_url($edit_url); ?>"
                             data-survey-id="<?php echo (int) $survey['id']; ?>"
                             data-title="<?php echo esc_attr($survey['title']); ?>"
                             data-created="<?php echo esc_attr(str_replace(' ', 'T', $survey['created_at'])); ?>"
                             data-updated="<?php echo esc_attr(str_replace(' ', 'T', $survey['updated_at'])); ?>"
                             data-sort-order="<?php echo (int) ($survey['sort_order'] ?? 0); ?>">
                            <div class="sp-survey-info">
                                <span class="sp-survey-title"><?php echo esc_html($survey['title']); ?></span>
                                <?php if (!empty($survey['survey_description'])) : ?>
                                    <span class="sp-survey-desc"><?php echo esc_html($survey['survey_description']); ?></span>
                                <?php endif; ?>
                                <span class="sp-survey-shortcode-display">
                                    <code>[survey_pilot name="<?php echo esc_attr($survey['title']); ?>"]</code><button type="button" class="sp-copy-btn" data-shortcode="<?php echo esc_attr('[survey_pilot name="' . $survey['title'] . '"]'); ?>" title="Copy shortcode"><img src="<?php echo esc_url(SP_URL . 'assets/images/copy.svg'); ?>" alt="Copy" class="sp-copy-icon" width="16" height="16"></button>
                                </span>
                            </div>
                            <div class="sp-survey-actions">
                                <button type="button" class="button-link sp-survey-menu-toggle" aria-haspopup="true" aria-expanded="false" aria-label="<?php esc_attr_e('Survey actions', 'survey-pilot'); ?>" title="<?php esc_attr_e('Options', 'survey-pilot'); ?>">
                                    <span class="sp-survey-menu-icon-wrapper">
                                        <img src="<?php echo esc_url(SP_URL . 'assets/images/three-dots-vertical.svg'); ?>" alt="" class="sp-survey-menu-icon" width="30" height="30">
                                    </span>
                                </button>
                                <div class="sp-survey-menu" role="menu">
                                    <a href="<?php echo esc_url($edit_url); ?>" class="sp-survey-menu-item" role="menuitem">Edit</a>
                                    <a href="<?php echo esc_url($duplicate_url); ?>" class="sp-survey-menu-item" role="menuitem">Duplicate</a>
                                    <a href="<?php echo esc_url($delete_url); ?>" data-sp-delete-url="<?php echo esc_url($delete_url); ?>" data-sp-survey-title="<?php echo esc_attr($survey['title']); ?>" class="sp-survey-menu-item sp-survey-menu-item-delete" role="menuitem">Delete</a>
                                </div>
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
            <p>To display a survey on a page or post, copy its shortcode from the survey card and paste it into the editor:</p>
            <p class="sp-shortcode-block"><code>[survey_pilot name="My Survey"]</code></p>
            <p>Replace <code>My Survey</code> with the exact name of your survey. Each card shows the correct shortcode to copy.</p>
            <p class="sp-eg-line">e.g. a survey named <strong>Customer Feedback</strong> uses:<br><code>[survey_pilot name="Customer Feedback"]</code></p>
        </div>
    </div>
</div>

<div id="sp-delete-modal" class="sp-modal" aria-hidden="true">
    <div class="sp-modal-overlay" tabindex="-1"></div>
    <div class="sp-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="sp-delete-modal-title" tabindex="-1">
        <h2 id="sp-delete-modal-title">Delete survey?</h2>
        <p>This will permanently delete <strong id="sp-delete-survey-name"></strong> and any associated questions/responses. This cannot be undone.</p>
        <div class="sp-modal-actions">
            <a href="#" class="button sp-btn-large" data-sp-cancel>Cancel</a>
            <a href="#" class="button sp-btn-large sp-btn-danger-outline" data-sp-confirm>Delete</a>
        </div>
    </div>
</div>