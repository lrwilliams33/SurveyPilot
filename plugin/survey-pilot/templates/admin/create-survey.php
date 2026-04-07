<div class="wrap sp-admin-page">
    <?php
    $is_edit = isset($is_edit) && $is_edit && !empty($survey);
    $page_title = $is_edit ? 'Edit Survey' : 'Create Survey';
    $action_value = $is_edit ? 'sp_edit_survey' : 'sp_create_survey';
    $nonce_action = $is_edit ? 'sp_edit_survey_nonce' : 'sp_create_survey_nonce';
    $submit_label = $is_edit ? 'Update Survey' : 'Create Survey';
    $questions = isset($questions) && is_array($questions) ? $questions : [];
    $sp_survey_response_count = isset($sp_survey_response_count) ? (int) $sp_survey_response_count : 0;
    $structure_locked         = $is_edit && !empty($survey) && $sp_survey_response_count > 0;
    $response_count_label = sprintf(
        _n('%s response', '%s responses', $sp_survey_response_count, 'survey-pilot'),
        number_format_i18n($sp_survey_response_count)
    );
    $edit_status_class = $sp_survey_response_count === 0 ? 'sp-survey-edit-status--full' : 'sp-survey-edit-status--partial';
    $edit_status_text  = $sp_survey_response_count === 0
        ? __('Fully Editable', 'survey-pilot')
        : __('Partially Editable', 'survey-pilot');
    ?>

    <div class="sp-admin-header">
        <a href="<?php echo esc_url(admin_url('admin.php?page=survey-pilot')); ?>" class="button-link sp-back-link">
            <img src="<?php echo esc_url(SP_URL . 'assets/images/back-arrow.svg'); ?>" alt="<?php esc_attr_e('Back to Dashboard', 'survey-pilot'); ?>" class="sp-back-arrow" width="28" height="28">
        </a>
        <h1><?php echo esc_html($page_title); ?></h1>
        <?php if ($is_edit) : ?>
            <span class="sp-survey-response-count sp-admin-header-status">
                <?php echo esc_html($response_count_label); ?>
                <span class="sp-survey-response-count-sep"> &rarr; </span>
                <span class="sp-survey-edit-status <?php echo esc_attr($edit_status_class); ?>"><?php echo esc_html($edit_status_text); ?></span>
            </span>
        <?php endif; ?>
    </div>

    <hr class="sp-section-divider">

    <div class="sp-dashboard-content">
        <div class="sp-dashboard-left">
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field($nonce_action); ?>
        <input type="hidden" name="action" value="<?php echo esc_attr($action_value); ?>">
        <?php if ($is_edit) : ?>
            <input type="hidden" name="sp_survey_id" value="<?php echo intval($survey['id']); ?>">
        <?php endif; ?>
        <input type="hidden" id="sp_survey_exclude_id" value="<?php echo $is_edit ? intval($survey['id']) : 0; ?>">
        <input type="hidden" id="sp_survey_original_title" value="<?php echo $is_edit ? esc_attr($survey['title']) : ''; ?>">

        <table class="form-table">
            <tr>
                <th class="sp-th-middle"><label for="sp_survey_title">Survey Title<span class="sp-required" aria-hidden="true">*</span></label></th>
                <td>
                    <input
                        type="text"
                        name="sp_survey_title"
                        id="sp_survey_title"
                        class="regular-text"
                        value="<?php echo $is_edit ? esc_attr($survey['title']) : ''; ?>"
                    >
                    <p id="sp-title-error" class="sp-field-error" style="display:none;">Survey Title is required.</p>
                </td>
            </tr>

            <tr>
                <th><label for="sp_survey_description">Description</label></th>
                <td>
                    <textarea
                        name="sp_survey_description"
                        id="sp_survey_description"
                        class="regular-text sp-fixed-textarea"
                    ><?php echo $is_edit && !empty($survey['survey_description']) ? esc_textarea($survey['survey_description']) : ''; ?></textarea>
                </td>
            </tr>

            <tr>
                <th><label for="sp_survey_instructions">Instructions</label></th>
                <td>
                    <textarea
                        name="sp_survey_instructions"
                        id="sp_survey_instructions"
                        class="regular-text sp-fixed-textarea"
                    ><?php echo $is_edit && !empty($survey['instructions']) ? esc_textarea($survey['instructions']) : ''; ?></textarea>
                </td>
            </tr>

        </table>

        <hr class="sp-section-divider">

        <h2 class="sp-questions-heading">Email Messaging</h2>
        <p class="description">After someone submits a response to this survey, you can send them an email with a custom message. You can also attach a PDF report with their results and comparisons to others.</p>

        <div class="sp-email-options-row">
            <div class="sp-email-option-item">
                <label class="sp-email-option-label" for="sp_email_messaging">Send Email Message</label>
                <input
                    type="checkbox"
                    name="sp_email_messaging"
                    id="sp_email_messaging"
                    value="1"
                    <?php if ($is_edit && !empty($survey['send_email_message'])) echo 'checked'; ?>
                >
            </div>
            <div class="sp-email-option-item" id="sp-send-pdf-row" <?php if (!$is_edit || empty($survey['send_email_message'])) echo 'style="display:none;"'; ?>>
                <label class="sp-email-option-label" for="sp_send_pdf_report">Send PDF Results Report</label>
                <input
                    type="checkbox"
                    name="sp_send_pdf_report"
                    id="sp_send_pdf_report"
                    value="1"
                    <?php if ($is_edit && !empty($survey['send_pdf_report'])) echo 'checked'; ?>
                >
            </div>
        </div>

        <table class="form-table" id="sp-email-message-row" <?php if (!$is_edit || empty($survey['send_email_message'])) echo 'style="display:none;"'; ?>>
            <tr>
                <th><label for="sp_email_message">Message<span class="sp-required" aria-hidden="true">*</span></label></th>
                <td>
                    <textarea
                        name="sp_email_message"
                        id="sp_email_message"
                        class="regular-text sp-fixed-textarea"
                    ><?php echo $is_edit && !empty($survey['email_message']) ? esc_textarea($survey['email_message']) : ''; ?></textarea>
                    <p id="sp-email-message-error" class="sp-field-error" style="display:none;">Message is required when "Send Email Message" is checked.</p>
                </td>
            </tr>
        </table>

        <p class="description sp-email-delivery-note">Configure email delivery in the <a href="<?php echo esc_url(admin_url('admin.php?page=sp-email-settings')); ?>">Email Settings</a> page.</p>

        <hr class="sp-section-divider">

        <h2 class="sp-questions-heading">Questions</h2>
        <p class="description">Add Likert scale questions for this survey.</p>
        <span id="sp-trash-icon-url" data-src="<?php echo esc_url(SP_URL . 'assets/images/trash-can.svg'); ?>" aria-hidden="true" style="display:none;"></span>
        <span id="sp-arrow-icon-urls" data-up="<?php echo esc_url(SP_URL . 'assets/images/up-arrow.png'); ?>" data-down="<?php echo esc_url(SP_URL . 'assets/images/down-arrow.png'); ?>" aria-hidden="true" style="display:none;"></span>

        <?php
        $saved_survey_layout = ($is_edit && isset($survey['survey_layout']) && $survey['survey_layout'] !== '') ? $survey['survey_layout'] : null;
        $layout_blocks       = sp_admin_survey_layout_blocks_for_display($questions, $saved_survey_layout);
        $page1_header_value  = '';
        foreach ($layout_blocks as $_pb) {
            if (($_pb['type'] ?? '') === 'page_header' && (int) ($_pb['page'] ?? 0) === 1) {
                $page1_header_value = (string) ($_pb['header'] ?? '');
                break;
            }
        }
        ?>

        <div class="sp-page-header-field" id="sp-page-1-header">
            <label class="sp-page-header-label">Page <span class="sp-page-number-display">1</span> Header</label>
            <input
                type="text"
                class="regular-text sp-page-header-input"
                name="sp_page_headers[1]"
                value="<?php echo esc_attr($page1_header_value); ?>"
                placeholder="Optional page header…"
            >
        </div>

        <div id="sp-question-builder" data-next-index="<?php echo esc_attr(count($questions)); ?>" data-sp-structure-locked="<?php echo $structure_locked ? '1' : '0'; ?>">
            <input type="hidden" name="sp_survey_layout" id="sp_survey_layout" value="">
            <div id="sp-questions-list">
                <?php if (!empty($layout_blocks)) :
                    $break_target_page = 2;
                    $question_render_i = 0;
                    foreach ($layout_blocks as $block) :
                        $block_type = isset($block['type']) ? $block['type'] : '';
                        if ($block_type === 'page_header') :
                            // Page 1 header is edited in #sp-page-1-header above this list.
                        elseif ($block_type === 'page_break') :
                            $ph_value_pb = isset($block['header']) ? (string) $block['header'] : '';
                ?>
                        <div class="sp-page-break">
                            <div class="sp-page-break-bar">
                                <div class="sp-page-break-line"></div>
                                <span class="sp-page-break-label">Page Break</span>
                                <span class="sp-block-move-actions" role="group" aria-label="<?php esc_attr_e('Reorder', 'survey-pilot'); ?>">
                                    <button type="button" class="button-link sp-move-btn sp-move-up" aria-label="<?php esc_attr_e('Move up', 'survey-pilot'); ?>"<?php echo $structure_locked ? ' disabled' : ''; ?>>
                                        <img src="<?php echo esc_url(SP_URL . 'assets/images/up-arrow.png'); ?>" alt="" class="sp-move-icon" width="24" height="24" />
                                    </button>
                                    <button type="button" class="button-link sp-move-btn sp-move-down" aria-label="<?php esc_attr_e('Move down', 'survey-pilot'); ?>"<?php echo $structure_locked ? ' disabled' : ''; ?>>
                                        <img src="<?php echo esc_url(SP_URL . 'assets/images/down-arrow.png'); ?>" alt="" class="sp-move-icon" width="24" height="24" />
                                    </button>
                                </span>
                                <button type="button" class="button-link sp-page-break-remove" aria-label="Remove page break"<?php echo $structure_locked ? ' disabled' : ''; ?>>
                                    <img src="<?php echo esc_url(SP_URL . 'assets/images/trash-can.svg'); ?>" alt="" class="sp-trash-icon" width="22" height="22">
                                </button>
                                <div class="sp-page-break-line"></div>
                            </div>
                            <div class="sp-page-header-field">
                                <label class="sp-page-header-label">Page <span class="sp-page-number-display"><?php echo esc_html($break_target_page); ?></span> Header</label>
                                <input
                                    type="text"
                                    class="regular-text sp-page-header-input"
                                    name="sp_page_headers[<?php echo esc_attr($break_target_page); ?>]"
                                    value="<?php echo esc_attr($ph_value_pb); ?>"
                                    placeholder="Optional page header…"
                                >
                            </div>
                        </div>
                <?php
                            $break_target_page++;
                        elseif ($block_type === 'text') :
                            $text_body = isset($block['content']) ? (string) $block['content'] : '';
                ?>
                        <div class="sp-text-card">
                            <div class="sp-question-header">
                                <span class="sp-question-label">Text Content</span>
                                <div class="sp-block-header-actions">
                                    <span class="sp-block-move-actions" role="group" aria-label="<?php esc_attr_e('Reorder', 'survey-pilot'); ?>">
                                        <button type="button" class="button-link sp-move-btn sp-move-up" aria-label="<?php esc_attr_e('Move up', 'survey-pilot'); ?>">
                                            <img src="<?php echo esc_url(SP_URL . 'assets/images/up-arrow.png'); ?>" alt="" class="sp-move-icon" width="24" height="24" />
                                        </button>
                                        <button type="button" class="button-link sp-move-btn sp-move-down" aria-label="<?php esc_attr_e('Move down', 'survey-pilot'); ?>">
                                            <img src="<?php echo esc_url(SP_URL . 'assets/images/down-arrow.png'); ?>" alt="" class="sp-move-icon" width="24" height="24" />
                                        </button>
                                    </span>
                                    <button type="button" class="button-link sp-text-remove" aria-label="Delete text block">
                                        <img src="<?php echo esc_url(SP_URL . 'assets/images/trash-can.svg'); ?>" alt="" class="sp-trash-icon" width="22" height="22">
                                    </button>
                                </div>
                            </div>
                            <div class="sp-question-body">
                                <div class="sp-field">
                                    <label>Text<span class="sp-required" aria-hidden="true">*</span></label>
                                    <textarea
                                        class="regular-text sp-text-block-textarea sp-auto-expand"
                                        rows="3"
                                    ><?php echo esc_textarea($text_body); ?></textarea>
                                    <p class="sp-field-error sp-text-block-error" style="display:none;">Text is required.</p>
                                </div>
                            </div>
                        </div>
                <?php
                        elseif ($block_type === 'question' && isset($questions[$question_render_i])) :
                            $question        = $questions[$question_render_i];
                            $question_index  = $question_render_i;
                            $scale_rows      = [];
                            $min             = max(1, (int) ($question['scale_min'] ?? 1));
                            $max             = max($min, (int) ($question['scale_max'] ?? 5));
                            $decoded         = [];
                            if (!empty($question['scale_labels'])) {
                                $decoded_try = json_decode($question['scale_labels'], true);
                                if (is_array($decoded_try)) {
                                    $decoded = $decoded_try;
                                }
                            }
                            for ($v = $min; $v <= $max; $v++) {
                                $scale_rows[] = [
                                    'value' => $v,
                                    'label' => isset($decoded[$v]) ? (string) $decoded[$v] : '',
                                ];
                            }
                            $question_render_i++;
                ?>
                        <div class="sp-question-card" data-question-index="<?php echo esc_attr($question_index); ?>">
                            <input type="hidden" name="sp_questions[<?php echo esc_attr($question_index); ?>][id]" value="<?php echo esc_attr($question['id'] ?? ''); ?>">
                            <div class="sp-question-header">
                                <span class="sp-question-label">Question <span class="sp-question-number"></span></span>
                                <div class="sp-block-header-actions">
                                    <span class="sp-block-move-actions" role="group" aria-label="<?php esc_attr_e('Reorder', 'survey-pilot'); ?>">
                                        <button type="button" class="button-link sp-move-btn sp-move-up" aria-label="<?php esc_attr_e('Move up', 'survey-pilot'); ?>"<?php echo $structure_locked ? ' disabled' : ''; ?>>
                                            <img src="<?php echo esc_url(SP_URL . 'assets/images/up-arrow.png'); ?>" alt="" class="sp-move-icon" width="24" height="24" />
                                        </button>
                                        <button type="button" class="button-link sp-move-btn sp-move-down" aria-label="<?php esc_attr_e('Move down', 'survey-pilot'); ?>"<?php echo $structure_locked ? ' disabled' : ''; ?>>
                                            <img src="<?php echo esc_url(SP_URL . 'assets/images/down-arrow.png'); ?>" alt="" class="sp-move-icon" width="24" height="24" />
                                        </button>
                                    </span>
                                    <button type="button" class="button-link sp-question-remove" aria-label="Delete question"<?php echo $structure_locked ? ' disabled' : ''; ?>>
                                        <img src="<?php echo esc_url(SP_URL . 'assets/images/trash-can.svg'); ?>" alt="" class="sp-trash-icon" width="22" height="22">
                                    </button>
                                </div>
                            </div>
                            <div class="sp-question-body">
                                <div class="sp-field">
                                    <label>Question Text<span class="sp-required" aria-hidden="true">*</span></label>
                                    <textarea
                                        class="regular-text sp-question-textarea sp-auto-expand"
                                        name="sp_questions[<?php echo esc_attr($question_index); ?>][text]"
                                        rows="3"
                                    ><?php echo esc_textarea($question['question_text']); ?></textarea>
                                    <p class="sp-field-error sp-qtext-error" style="display:none;">Question Text is required.</p>
                                </div>
                                <div class="sp-field">
                                    <label>Scale Options</label>
                                    <div class="sp-scale-rows">
                                        <?php foreach ($scale_rows as $scale_index => $row) : ?>
                                            <div class="sp-scale-row" data-scale-value="<?php echo esc_attr($row['value']); ?>">
                                                <input type="hidden" name="sp_questions[<?php echo esc_attr($question_index); ?>][scale][<?php echo esc_attr($scale_index); ?>][value]" value="<?php echo esc_attr($row['value']); ?>">
                                                <input type="number" class="small-text" value="<?php echo esc_attr($row['value']); ?>" readonly>
                                                <input type="text" class="regular-text" name="sp_questions[<?php echo esc_attr($question_index); ?>][scale][<?php echo esc_attr($scale_index); ?>][label]" value="<?php echo esc_attr($row['label']); ?>" placeholder="Label for <?php echo esc_attr($row['value']); ?>">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="button-secondary sp-add-scale"<?php echo $structure_locked ? ' disabled' : ''; ?>>+ Add Option</button>
                                </div>
                            </div>
                        </div>
                <?php
                        endif;
                    endforeach;
                endif; ?>
            </div>

            <div class="sp-question-builder-actions">
                <button type="button" class="button sp-btn-large" id="sp-add-question"<?php echo $structure_locked ? ' disabled' : ''; ?>>+ Add Question</button>
                <button type="button" class="button sp-btn-large" id="sp-add-text">+ Add Text</button>
                <button type="button" class="button sp-btn-large" id="sp-add-page-break" disabled>+ Add Page Break</button>
            </div>

            <div id="sp-question-template" style="display:none;">
                <div class="sp-question-card" data-question-index="__INDEX__">
                    <input type="hidden" name="sp_questions[__INDEX__][id]" value="" disabled>
                    <div class="sp-question-header">
                        <span class="sp-question-label">Question <span class="sp-question-number"></span></span>
                        <div class="sp-block-header-actions">
                            <span class="sp-block-move-actions" role="group" aria-label="Reorder">
                                <button type="button" class="button-link sp-move-btn sp-move-up" aria-label="Move up" disabled>
                                    <img src="<?php echo esc_url(SP_URL . 'assets/images/up-arrow.png'); ?>" alt="" class="sp-move-icon" width="24" height="24" />
                                </button>
                                <button type="button" class="button-link sp-move-btn sp-move-down" aria-label="Move down" disabled>
                                    <img src="<?php echo esc_url(SP_URL . 'assets/images/down-arrow.png'); ?>" alt="" class="sp-move-icon" width="24" height="24" />
                                </button>
                            </span>
                            <button type="button" class="button-link sp-question-remove" aria-label="Delete question" disabled>
                                <img src="<?php echo esc_url(SP_URL . 'assets/images/trash-can.svg'); ?>" alt="" class="sp-trash-icon" width="22" height="22">
                            </button>
                        </div>
                    </div>
                    <div class="sp-question-body">
                        <div class="sp-field">
                            <label>Question Text<span class="sp-required" aria-hidden="true">*</span></label>
                            <textarea
                                class="regular-text sp-question-textarea sp-auto-expand"
                                name="sp_questions[__INDEX__][text]"
                                rows="3"
                                disabled
                            ></textarea>
                            <p class="sp-field-error sp-qtext-error" style="display:none;">Question Text is required.</p>
                        </div>
                        <div class="sp-field">
                            <label>Scale Options</label>
                            <div class="sp-scale-rows">
                                <?php for ($i = 0; $i < 5; $i++) : $val = $i + 1; ?>
                                    <div class="sp-scale-row" data-scale-value="<?php echo esc_attr($val); ?>">
                                        <input type="hidden" name="sp_questions[__INDEX__][scale][<?php echo esc_attr($i); ?>][value]" value="<?php echo esc_attr($val); ?>" disabled>
                                        <input type="number" class="small-text" value="<?php echo esc_attr($val); ?>" readonly disabled>
                                        <input type="text" class="regular-text" name="sp_questions[__INDEX__][scale][<?php echo esc_attr($i); ?>][label]" placeholder="Label for <?php echo esc_attr($val); ?>" disabled>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            <button type="button" class="button-secondary sp-add-scale" disabled>+ Add Option</button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="sp-text-card-template" style="display:none;">
                <div class="sp-text-card">
                    <div class="sp-question-header">
                        <span class="sp-question-label">Text Content</span>
                        <div class="sp-block-header-actions">
                            <span class="sp-block-move-actions" role="group" aria-label="Reorder">
                                <button type="button" class="button-link sp-move-btn sp-move-up" aria-label="Move up" disabled>
                                    <img src="<?php echo esc_url(SP_URL . 'assets/images/up-arrow.png'); ?>" alt="" class="sp-move-icon" width="24" height="24" />
                                </button>
                                <button type="button" class="button-link sp-move-btn sp-move-down" aria-label="Move down" disabled>
                                    <img src="<?php echo esc_url(SP_URL . 'assets/images/down-arrow.png'); ?>" alt="" class="sp-move-icon" width="24" height="24" />
                                </button>
                            </span>
                            <button type="button" class="button-link sp-text-remove" aria-label="Delete text block" disabled>
                                <img src="<?php echo esc_url(SP_URL . 'assets/images/trash-can.svg'); ?>" alt="" class="sp-trash-icon" width="22" height="22">
                            </button>
                        </div>
                    </div>
                    <div class="sp-question-body">
                        <div class="sp-field">
                            <label>Text<span class="sp-required" aria-hidden="true">*</span></label>
                            <textarea class="regular-text sp-text-block-textarea sp-auto-expand" rows="3" disabled></textarea>
                            <p class="sp-field-error sp-text-block-error" style="display:none;">Text is required.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <p id="sp-questions-error" class="sp-questions-error" style="display:none;">You must add at least one question before saving the survey.</p>

        <?php submit_button($submit_label, 'primary sp-btn-large'); ?>
    </form>
        </div>

        <div class="sp-dashboard-right">
            <h2>Survey Editability</h2>
            <p>SurveyPilot uses two editability states once a survey is created:</p>
            <p><strong>Fully Editable</strong> means the survey has no responses yet, so you can freely modify both its structure and content.</p>
            <p><strong>Partially Editable</strong> means the survey has responses. To preserve data integrity and aggregation, page structure is locked, but non-structural fields and text content can still be updated.</p>
            <hr class="sp-sidebar-divider">
            <h2>Page-Based Aggregation</h2>
            <p>For the <strong>PDF Results Report</strong>, summary statistics are calculated separately for each page. Page structure is defined by where page breaks are placed in the survey builder.</p>
            <p>Each page is treated as its own reporting section, with page headers used as labels in aggregated results.</p>
            <p>Choose page break locations carefully to ensure the data is grouped and reported as intended.</p>
        </div>
    </div>
</div>