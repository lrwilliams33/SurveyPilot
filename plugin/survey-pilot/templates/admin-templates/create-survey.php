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
        <a href="<?php echo esc_url(admin_url('admin.php?page=survey-pilot-dashboard')); ?>" class="button-link sp-back-link">
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

    <!-- Survey Builder -->
    <div class="sp-dashboard-content">
        <div class="sp-dashboard-left">
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
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
                        placeholder="e.g. Customer Feedback Survey"
                        maxlength="255"
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
                        placeholder="Explain the purpose of this survey..."
                        maxlength="1000"
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
                        placeholder="Tell respondents how to complete this survey..."
                        maxlength="2000"
                    ><?php echo $is_edit && !empty($survey['instructions']) ? esc_textarea($survey['instructions']) : ''; ?></textarea>
                </td>
            </tr>

        </table>

        <hr class="sp-section-divider">

        <h2 class="sp-questions-heading">Email Messaging</h2>
        <p class="description">After someone submits a response to this survey, you can send them an email with a custom message. You can also attach a PDF report with their results and comparisons to others.</p>

        <?php
        $sp_email_on = $is_edit && !empty($survey['send_email_message']);
        $sp_pdf_logo_show = $is_edit && $sp_email_on && !empty($survey['send_pdf_report']);
        $sp_pdf_logo_id  = ($is_edit && !empty($survey['pdf_report_logo_attachment_id']))
            ? (int) $survey['pdf_report_logo_attachment_id']
            : 0;
        $sp_pdf_logo_url = ($sp_pdf_logo_id > 0) ? wp_get_attachment_image_url($sp_pdf_logo_id, 'medium') : '';
        ?>

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
        </div>

        <table class="form-table" id="sp-email-message-row" <?php echo $sp_email_on ? '' : 'style="display:none;"'; ?>>
            <tr>
                <th><label for="sp_email_message">Message<span class="sp-required" aria-hidden="true">*</span></label></th>
                <td>
                    <textarea
                        name="sp_email_message"
                        id="sp_email_message"
                        class="regular-text sp-fixed-textarea"
                        placeholder="The message participants will receive..."
                        maxlength="2000"
                    ><?php echo $is_edit && !empty($survey['email_message']) ? esc_textarea($survey['email_message']) : ''; ?></textarea>
                    <p id="sp-email-message-error" class="sp-field-error" style="display:none;">Message is required if "Send Email Message" is checked.</p>
                </td>
            </tr>
        </table>

        <div id="sp-email-pdf-column" <?php echo $sp_email_on ? '' : 'style="display:none;"'; ?>>
            <div class="sp-email-options-row sp-email-options-row--pdf" id="sp-send-pdf-row">
                <div class="sp-email-option-item">
                    <label class="sp-email-option-label" for="sp_send_pdf_report"><?php esc_html_e('Send PDF Results Report', 'survey-pilot'); ?></label>
                    <input
                        type="checkbox"
                        name="sp_send_pdf_report"
                        id="sp_send_pdf_report"
                        value="1"
                        <?php if ($is_edit && !empty($survey['send_pdf_report'])) echo 'checked'; ?>
                    >
                </div>
            </div>
            <div class="sp-pdf-logo-row" id="sp-pdf-logo-row" <?php echo $sp_pdf_logo_show ? '' : 'style="display:none;"'; ?>>
                <div class="sp-email-option-label sp-pdf-logo-label"><?php esc_html_e('PDF Report Logo', 'survey-pilot'); ?></div>
                <p class="description sp-pdf-logo-hint"><?php esc_html_e('Optional. Accepted image types: .jpg, .jpeg, or .png (max 2 MB). If omitted, no logo will appear on the PDF.', 'survey-pilot'); ?></p>
                <input type="hidden" name="sp_remove_pdf_report_logo" id="sp_remove_pdf_report_logo" value="">
                <div class="sp-pdf-logo-file-row">
                    <button type="button" class="sp-pdf-logo-choose-btn sp-btn-filelike" id="sp-pdf-logo-choose-btn">
                        <?php esc_html_e('Choose File', 'survey-pilot'); ?>
                    </button>
                    <span
                        class="sp-pdf-logo-filename"
                        id="sp-pdf-logo-filename"
                        data-empty-text="<?php echo esc_attr(__('No File Chosen', 'survey-pilot')); ?>"
                    ><?php echo esc_html(__('No File Chosen', 'survey-pilot')); ?></span>
                    <input
                        type="file"
                        name="sp_pdf_report_logo"
                        id="sp_pdf_report_logo"
                        class="sp-pdf-logo-file-input-hidden"
                        tabindex="-1"
                        accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                    >
                </div>
                <div class="sp-pdf-logo-preview sp-pdf-logo-preview-live" id="sp-pdf-logo-preview-live" style="display: none;" hidden>
                    <p class="description"><?php esc_html_e('Selected Logo:', 'survey-pilot'); ?></p>
                    <img src="" alt="" width="120" height="auto" class="sp-pdf-logo-preview-img" id="sp-pdf-logo-preview-live-img">
                    <button type="button" class="sp-pdf-logo-remove-btn sp-btn-filelike" id="sp-pdf-logo-remove-live-btn">
                        <?php esc_html_e('Remove Logo', 'survey-pilot'); ?>
                    </button>
                </div>
                <?php if ($is_edit && $sp_pdf_logo_url) : ?>
                    <div class="sp-pdf-logo-preview sp-pdf-logo-preview-saved" id="sp-pdf-logo-preview-saved">
                        <p class="description"><?php esc_html_e('Current Logo:', 'survey-pilot'); ?></p>
                        <img src="<?php echo esc_url($sp_pdf_logo_url); ?>" alt="" width="120" height="auto" class="sp-pdf-logo-preview-img">
                        <button type="button" class="sp-pdf-logo-remove-btn sp-btn-filelike" id="sp-pdf-logo-remove-saved-btn">
                            <?php esc_html_e('Remove Logo', 'survey-pilot'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <p class="description sp-email-delivery-note">Configure email delivery in the <a href="<?php echo esc_url(admin_url('admin.php?page=survey-pilot-email-settings')); ?>">Email Settings</a> page.</p>

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
                maxlength="120"
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
                        elseif ($block_type === 'page_break') :
                            $ph_value_pb = isset($block['header']) ? (string) $block['header'] : '';
                ?>
                        <div class="sp-page-break">
                            <div class="sp-page-break-bar">
                                <div class="sp-page-break-line"></div>
                                <span class="sp-page-break-label">Page Break</span>
                                <span class="sp-block-move-actions" role="group" aria-label="<?php esc_attr_e('Reorder', 'survey-pilot'); ?>">
                                    <button type="button" class="button-link sp-move-btn sp-move-up" aria-label="Move Element Up" title="Move Element Up"<?php echo $structure_locked ? ' disabled' : ''; ?>>
                                        <img src="<?php echo esc_url(SP_URL . 'assets/images/up-arrow.png'); ?>" alt="" class="sp-move-icon" width="24" height="24" />
                                    </button>
                                    <button type="button" class="button-link sp-move-btn sp-move-down" aria-label="Move Element Down" title="Move Element Down"<?php echo $structure_locked ? ' disabled' : ''; ?>>
                                        <img src="<?php echo esc_url(SP_URL . 'assets/images/down-arrow.png'); ?>" alt="" class="sp-move-icon" width="24" height="24" />
                                    </button>
                                </span>
                                <button type="button" class="button-link sp-page-break-remove" aria-label="Delete Page Break" title="Delete Page Break"<?php echo $structure_locked ? ' disabled' : ''; ?>>
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
                                    maxlength="120"
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
                                        <button type="button" class="button-link sp-move-btn sp-move-up" aria-label="Move Element Up" title="Move Element Up">
                                            <img src="<?php echo esc_url(SP_URL . 'assets/images/up-arrow.png'); ?>" alt="" class="sp-move-icon" width="24" height="24" />
                                        </button>
                                        <button type="button" class="button-link sp-move-btn sp-move-down" aria-label="Move Element Down" title="Move Element Down">
                                            <img src="<?php echo esc_url(SP_URL . 'assets/images/down-arrow.png'); ?>" alt="" class="sp-move-icon" width="24" height="24" />
                                        </button>
                                    </span>
                                    <button type="button" class="button-link sp-text-remove" aria-label="Delete Text Content" title="Delete Text Content">
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
                                        placeholder="Add explanatory text or section guidance..."
                                        maxlength="2000"
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
                                        <button type="button" class="button-link sp-move-btn sp-move-up" aria-label="Move Element Up" title="Move Element Up"<?php echo $structure_locked ? ' disabled' : ''; ?>>
                                            <img src="<?php echo esc_url(SP_URL . 'assets/images/up-arrow.png'); ?>" alt="" class="sp-move-icon" width="24" height="24" />
                                        </button>
                                        <button type="button" class="button-link sp-move-btn sp-move-down" aria-label="Move Element Down" title="Move Element Down"<?php echo $structure_locked ? ' disabled' : ''; ?>>
                                            <img src="<?php echo esc_url(SP_URL . 'assets/images/down-arrow.png'); ?>" alt="" class="sp-move-icon" width="24" height="24" />
                                        </button>
                                    </span>
                                    <button type="button" class="button-link sp-question-remove" aria-label="Delete Question" title="Delete Question"<?php echo $structure_locked ? ' disabled' : ''; ?>>
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
                                        placeholder="Enter your question here..."
                                        maxlength="500"
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
                                                <input type="text" class="regular-text" name="sp_questions[<?php echo esc_attr($question_index); ?>][scale][<?php echo esc_attr($scale_index); ?>][label]" value="<?php echo esc_attr($row['label']); ?>" placeholder="Label for <?php echo esc_attr($row['value']); ?>" maxlength="120">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="button button-secondary sp-add-scale sp-btn-filelike"<?php echo $structure_locked ? ' disabled' : ''; ?>>+ Add Option</button>
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
                                <button type="button" class="button-link sp-move-btn sp-move-up" aria-label="Move Element Up" title="Move Element Up" disabled>
                                    <img src="<?php echo esc_url(SP_URL . 'assets/images/up-arrow.png'); ?>" alt="" class="sp-move-icon" width="24" height="24" />
                                </button>
                                <button type="button" class="button-link sp-move-btn sp-move-down" aria-label="Move Element Down" title="Move Element Down" disabled>
                                    <img src="<?php echo esc_url(SP_URL . 'assets/images/down-arrow.png'); ?>" alt="" class="sp-move-icon" width="24" height="24" />
                                </button>
                            </span>
                            <button type="button" class="button-link sp-question-remove" aria-label="Delete Question" title="Delete Question" disabled>
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
                                placeholder="Enter your question here..."
                                maxlength="500"
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
                                        <input type="text" class="regular-text" name="sp_questions[__INDEX__][scale][<?php echo esc_attr($i); ?>][label]" placeholder="Label for <?php echo esc_attr($val); ?>" maxlength="120" disabled>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            <button type="button" class="button button-secondary sp-add-scale sp-btn-filelike" disabled>+ Add Option</button>
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
                                <button type="button" class="button-link sp-move-btn sp-move-up" aria-label="Move Element Up" title="Move Element Up" disabled>
                                    <img src="<?php echo esc_url(SP_URL . 'assets/images/up-arrow.png'); ?>" alt="" class="sp-move-icon" width="24" height="24" />
                                </button>
                                <button type="button" class="button-link sp-move-btn sp-move-down" aria-label="Move Element Down" title="Move Element Down" disabled>
                                    <img src="<?php echo esc_url(SP_URL . 'assets/images/down-arrow.png'); ?>" alt="" class="sp-move-icon" width="24" height="24" />
                                </button>
                            </span>
                            <button type="button" class="button-link sp-text-remove" aria-label="Delete Text Content" title="Delete Text Content" disabled>
                                <img src="<?php echo esc_url(SP_URL . 'assets/images/trash-can.svg'); ?>" alt="" class="sp-trash-icon" width="22" height="22">
                            </button>
                        </div>
                    </div>
                    <div class="sp-question-body">
                        <div class="sp-field">
                            <label>Text<span class="sp-required" aria-hidden="true">*</span></label>
                            <textarea class="regular-text sp-text-block-textarea sp-auto-expand" rows="3" placeholder="Add explanatory text or section guidance..." maxlength="2000" disabled></textarea>
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

        <!-- Survey Editability & Page-Based Aggregation Instructions -->
        <div class="sp-dashboard-right">
            <h2>Survey Editability</h2>
            <p>SurveyPilot uses two editability states once a survey is created:</p>
            <ul class="sp-editability-list">
                <li><strong>Fully Editable</strong> means the survey has no responses yet, so you can freely modify both its structure and content.</li>
                <li><strong>Partially Editable</strong> means the survey has responses. To preserve data integrity and aggregation, page structure is locked, but non-structural fields and text content can still be updated.</li>
            </ul>
                <p>To make a fully editable version of a partially editable survey, duplicate the survey and edit the copy. Note that responses will not carry over and will restart for the new survey.</p>
            <hr class="sp-sidebar-divider">
            <h2>Page-Based Aggregation</h2>
            <p>For the <strong>PDF Results Report</strong>, summary statistics are calculated separately for each page. Page structure is defined by where page breaks are placed in the survey builder.</p>
            <p>Each page is treated as its own reporting section, with page headers used as labels in aggregated results.</p>
            <p>Choose page break locations carefully to ensure the data is grouped and reported as intended.</p>
        </div>
    </div>
</div>
