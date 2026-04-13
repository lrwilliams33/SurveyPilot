<?php
// dashboard.php template

global $wpdb;
$surveys = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}survey_info ORDER BY updated_at DESC", ARRAY_A);

// Build a map of survey_id => response count in one query.
$response_counts = [];
$raw_counts = $wpdb->get_results(
    "SELECT survey_id, COUNT(*) AS cnt FROM {$wpdb->prefix}survey_response_info GROUP BY survey_id",
    ARRAY_A
);
foreach ($raw_counts as $rc) {
    $response_counts[ (int) $rc['survey_id'] ] = (int) $rc['cnt'];
}
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
                        <label for="sp-sort-select" class="sp-sort-label">Sort By:</label>
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
                    <a href="<?php echo admin_url('admin.php?page=survey-pilot-create-survey'); ?>" class="button button-primary sp-btn-large">+ Create Survey</a>
                </div>
            </div>
            <?php if (!empty($surveys)) : ?>
                <div class="sp-survey-list" style="visibility:hidden">
                    <?php foreach ($surveys as $survey) : ?>
                        <?php
                        $edit_url      = admin_url('admin.php?page=survey-pilot-create-survey&action=edit&id=' . intval($survey['id']));
                        $duplicate_url = wp_nonce_url(
                            admin_url('admin-post.php?action=sp_duplicate_survey&survey_id=' . intval($survey['id'])),
                            'sp_duplicate_survey_' . intval($survey['id'])
                        );
                        $delete_url = wp_nonce_url(
                            admin_url('admin.php?page=survey-pilot-dashboard&action=delete&id=' . intval($survey['id'])),
                            'sp_delete_survey_' . intval($survey['id'])
                        );
                        $response_count = (int) ($response_counts[ (int) $survey['id'] ] ?? 0);
                        $response_count_label = sprintf(
                            _n('%s response', '%s responses', $response_count, 'survey-pilot'),
                            number_format_i18n($response_count)
                        );
                        $edit_status_class = $response_count === 0 ? 'sp-survey-edit-status--full' : 'sp-survey-edit-status--partial';
                        $edit_status_text    = $response_count === 0
                            ? __('Fully Editable', 'survey-pilot')
                            : __('Partially Editable', 'survey-pilot');
                        ?>
                        <div class="sp-survey-card"
                             data-edit-url="<?php echo esc_url($edit_url); ?>"
                             data-survey-id="<?php echo (int) $survey['id']; ?>"
                             data-title="<?php echo esc_attr($survey['title']); ?>"
                             data-created="<?php echo esc_attr(str_replace(' ', 'T', $survey['created_at'])); ?>"
                             data-updated="<?php echo esc_attr(str_replace(' ', 'T', $survey['updated_at'])); ?>"
                             data-sort-order="<?php echo (int) ($survey['sort_order'] ?? 0); ?>"
                             data-response-count="<?php echo (int) $response_count; ?>">
                            <div class="sp-survey-info">
                                <span class="sp-survey-title"><?php echo esc_html($survey['title']); ?></span>
                                <?php if (!empty($survey['survey_description'])) : ?>
                                    <div class="sp-survey-desc"><?php echo esc_html($survey['survey_description']); ?></div>
                                <?php endif; ?>
                                <span class="sp-survey-shortcode-display">
                                    <span class="sp-survey-shortcode-box">
                                        <span class="sp-survey-shortcode-inline">
                                            <code>[survey_pilot name="<?php echo esc_attr($survey['title']); ?>"]</code><button type="button" class="sp-copy-btn" data-shortcode="<?php echo esc_attr('[survey_pilot name="' . $survey['title'] . '"]'); ?>" title="Copy Shortcode"><img src="<?php echo esc_url(SP_URL . 'assets/images/copy.svg'); ?>" alt="Copy" class="sp-copy-icon" width="16" height="16"></button>
                                        </span>
                                    </span>
                                    <span class="sp-survey-response-count">
                                        <?php echo esc_html($response_count_label); ?>
                                        <span class="sp-survey-response-count-sep"> &rarr; </span>
                                        <span class="sp-survey-edit-status <?php echo esc_attr($edit_status_class); ?>"><?php echo esc_html($edit_status_text); ?></span>
                                    </span>
                                </span>
                            </div>
                            <div class="sp-survey-card-toolbar">
                                <span class="sp-survey-reorder-actions" role="group" aria-label="<?php esc_attr_e('Reorder survey in list', 'survey-pilot'); ?>">
                                    <button type="button" class="button-link sp-survey-move-btn sp-survey-move-up" aria-label="Move Element Up" title="Move Element Up">
                                        <img src="<?php echo esc_url(SP_URL . 'assets/images/up-arrow.png'); ?>" alt="" width="20" height="20" class="sp-survey-move-icon">
                                    </button>
                                    <button type="button" class="button-link sp-survey-move-btn sp-survey-move-down" aria-label="Move Element Down" title="Move Element Down">
                                        <img src="<?php echo esc_url(SP_URL . 'assets/images/down-arrow.png'); ?>" alt="" width="20" height="20" class="sp-survey-move-icon">
                                    </button>
                                </span>
                            <div class="sp-survey-actions">
                                <button type="button" class="button-link sp-survey-menu-toggle" aria-haspopup="true" aria-expanded="false" aria-label="<?php esc_attr_e('Survey actions', 'survey-pilot'); ?>" title="<?php esc_attr_e('Options', 'survey-pilot'); ?>">
                                    <span class="sp-survey-menu-icon-wrapper">
                                        <img src="<?php echo esc_url(SP_URL . 'assets/images/three-dots-vertical.svg'); ?>" alt="" class="sp-survey-menu-icon" width="30" height="30">
                                    </span>
                                </button>
                                <div class="sp-survey-menu" role="menu">
                                    <a href="<?php echo esc_url($edit_url); ?>" class="sp-survey-menu-item" role="menuitem">Edit</a>
                                    <a href="<?php echo esc_url($duplicate_url); ?>" class="sp-survey-menu-item sp-duplicate-btn" role="menuitem" data-survey-title="<?php echo esc_attr($survey['title']); ?>">Duplicate</a>
                                    <button type="button" class="sp-survey-menu-item sp-survey-menu-item-export" role="menuitem" data-sp-export-survey-id="<?php echo (int) $survey['id']; ?>" data-sp-survey-title="<?php echo esc_attr($survey['title']); ?>" data-sp-response-count="<?php echo (int) $response_count; ?>">Export Responses</button>
                                    <a href="<?php echo esc_url($delete_url); ?>" data-sp-delete-url="<?php echo esc_url($delete_url); ?>" data-sp-survey-title="<?php echo esc_attr($survey['title']); ?>" class="sp-survey-menu-item sp-survey-menu-item-delete" role="menuitem">Delete</a>
                                </div>
                            </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="sp-no-surveys">No surveys created yet.</p>
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

<div id="sp-duplicate-blocked-modal" class="sp-modal" aria-hidden="true">
    <div class="sp-modal-overlay" tabindex="-1"></div>
    <div class="sp-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="sp-dup-blocked-title" tabindex="-1">
        <h2 id="sp-dup-blocked-title">Cannot Duplicate Survey</h2>
        <p>A survey named <strong id="sp-dup-blocked-name"></strong> already exists. Please rename or delete it before duplicating.</p>
        <div class="sp-modal-actions">
            <a href="#" class="button sp-btn-large" id="sp-dup-blocked-ok">OK</a>
        </div>
    </div>
</div>

<div id="sp-duplicate-title-too-long-modal" class="sp-modal" aria-hidden="true">
    <div class="sp-modal-overlay" tabindex="-1"></div>
    <div class="sp-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="sp-dup-too-long-title" tabindex="-1">
        <h2 id="sp-dup-too-long-title">Cannot Duplicate Survey</h2>
        <p>The duplicated survey's name would exceed the maximum survey title length. Please shorten the survey title before duplicating.</p>
        <div class="sp-modal-actions">
            <a href="#" class="button sp-btn-large" id="sp-dup-too-long-ok">OK</a>
        </div>
    </div>
</div>

<div id="sp-export-modal" class="sp-modal" aria-hidden="true">
    <div class="sp-modal-overlay" tabindex="-1"></div>
    <div class="sp-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="sp-export-modal-title" tabindex="-1">
        <h2 id="sp-export-modal-title">Export Responses</h2>
        <p>Download all responses for <strong id="sp-export-survey-name"></strong> as a CSV file.</p>
        <p class="sp-export-description">The file will contain one row per submission with a column for each question and its answer value.</p>
        <p id="sp-export-no-responses" class="sp-export-notice" style="display:none;">This survey has no responses yet.</p>
        <div class="sp-modal-actions">
            <a href="#" class="button sp-btn-large" data-sp-export-cancel>Cancel</a>
            <button type="button" class="button button-primary sp-btn-large" id="sp-export-download-btn"><img src="<?php echo esc_url(SP_URL . 'assets/images/download-button.svg'); ?>" alt="" width="14" height="14" class="sp-download-icon"> Download CSV</button>
        </div>
    </div>
</div>

<div id="sp-delete-modal" class="sp-modal" aria-hidden="true">
    <div class="sp-modal-overlay" tabindex="-1"></div>
    <div class="sp-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="sp-delete-modal-title" tabindex="-1">
        <h2 id="sp-delete-modal-title">Delete Survey?</h2>
        <p>This will permanently delete <strong id="sp-delete-survey-name"></strong> and any associated questions/responses. This cannot be undone.</p>
        <div class="sp-modal-actions">
            <a href="#" class="button sp-btn-large" data-sp-cancel>Cancel</a>
            <a href="#" class="button sp-btn-large sp-btn-danger-outline" data-sp-confirm>Delete</a>
        </div>
    </div>
</div>