<div class="wrap sp-admin-page">
    <?php
    $is_edit = isset($is_edit) && $is_edit && !empty($survey);
    $page_title = $is_edit ? 'Edit Survey' : 'Create Survey';
    $action_value = $is_edit ? 'sp_edit_survey' : 'sp_create_survey';
    $nonce_action = $is_edit ? 'sp_edit_survey_nonce' : 'sp_create_survey_nonce';
    $submit_label = $is_edit ? 'Update Survey' : 'Create Survey';
    $questions = isset($questions) && is_array($questions) ? $questions : [];
    ?>

    <div class="sp-admin-header">
        <a href="<?php echo esc_url(admin_url('admin.php?page=survey-pilot')); ?>" class="button-link sp-back-link">
            <img src="<?php echo esc_url(SP_URL . 'assets/images/back-arrow.svg'); ?>" alt="<?php esc_attr_e('Back to Dashboard', 'survey-pilot'); ?>" class="sp-back-arrow" width="28" height="28">
        </a>
        <h1><?php echo esc_html($page_title); ?></h1>
    </div>

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
                <th><label for="sp_survey_title">Survey Title<span class="sp-required" aria-hidden="true">*</span></label></th>
                <td>
                    <input
                        type="text"
                        name="sp_survey_title"
                        id="sp_survey_title"
                        class="regular-text"
                        value="<?php echo $is_edit ? esc_attr($survey['title']) : ''; ?>"
                    >
                    <p id="sp-title-error" class="sp-field-error" style="display:none;">Please enter a survey title.</p>
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

        <h2 class="sp-questions-heading">Questions</h2>
        <p class="description">Add Likert scale questions for this survey.</p>
        <span id="sp-trash-icon-url" data-src="<?php echo esc_url(SP_URL . 'assets/images/trash-can.svg'); ?>" aria-hidden="true" style="display:none;"></span>

        <?php
        $page_headers_decoded = [];
        if ($is_edit && !empty($survey['page_headers'])) {
            $decoded_ph = json_decode($survey['page_headers'], true);
            if (is_array($decoded_ph)) {
                $page_headers_decoded = $decoded_ph;
            }
        }
        $page1_header_value = isset($page_headers_decoded[1]) ? $page_headers_decoded[1] : '';
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

        <div id="sp-question-builder" data-next-index="<?php echo esc_attr(count($questions)); ?>">
            <div id="sp-questions-list">
                <?php if (!empty($questions)) :
                    $prev_page = 1;
                    foreach ($questions as $index => $question) :
                        $question_index = (int) $index;
                        $current_page = isset($question['page_number']) ? max(1, (int) $question['page_number']) : 1;

                        if ($current_page > $prev_page) :
                            $prev_page = $current_page;
                            $ph_value = isset($page_headers_decoded[$current_page]) ? $page_headers_decoded[$current_page] : '';
                ?>
                        <div class="sp-page-break">
                            <div class="sp-page-break-bar">
                                <div class="sp-page-break-line"></div>
                                <span class="sp-page-break-label">Page Break</span>
                                <button type="button" class="button-link sp-page-break-remove" aria-label="Remove page break">
                                    <img src="<?php echo esc_url(SP_URL . 'assets/images/trash-can.svg'); ?>" alt="" class="sp-trash-icon" width="18" height="18">
                                </button>
                                <div class="sp-page-break-line"></div>
                            </div>
                            <div class="sp-page-header-field">
                                <label class="sp-page-header-label">Page <span class="sp-page-number-display"><?php echo esc_html($current_page); ?></span> Header</label>
                                <input
                                    type="text"
                                    class="regular-text sp-page-header-input"
                                    name="sp_page_headers[<?php echo esc_attr($current_page); ?>]"
                                    value="<?php echo esc_attr($ph_value); ?>"
                                    placeholder="Optional page header…"
                                >
                            </div>
                        </div>
                <?php   endif; ?>
                        <?php
                        $scale_rows = [];
                        $min = max(1, (int) ($question['scale_min'] ?? 1));
                        $max = max($min, (int) ($question['scale_max'] ?? 5));
                        $decoded = [];
                        if (!empty($question['scale_labels'])) {
                            $decoded = json_decode($question['scale_labels'], true);
                            if (!is_array($decoded)) {
                                $decoded = [];
                            }
                        }
                        for ($v = $min; $v <= $max; $v++) {
                            $scale_rows[] = [
                                'value' => $v,
                                'label' => isset($decoded[$v]) ? (string) $decoded[$v] : '',
                            ];
                        }
                        ?>
                        <div class="sp-question-card" data-question-index="<?php echo esc_attr($question_index); ?>">
                            <input type="hidden" class="sp-page-input" name="sp_questions[<?php echo esc_attr($question_index); ?>][page]" value="<?php echo esc_attr($current_page); ?>">
                            <div class="sp-question-header">
                                <span class="sp-question-label">Question <span class="sp-question-number"></span></span>
                                <button type="button" class="button-link sp-question-remove" aria-label="Delete question">
                                    <img src="<?php echo esc_url(SP_URL . 'assets/images/trash-can.svg'); ?>" alt="" class="sp-trash-icon" width="22" height="22">
                                </button>
                            </div>
                            <div class="sp-question-body">
                                <div class="sp-field">
                                    <label>Question Text<span class="sp-required" aria-hidden="true">*</span></label>
                                    <textarea
                                        class="regular-text sp-question-textarea"
                                        name="sp_questions[<?php echo esc_attr($question_index); ?>][text]"
                                    ><?php echo esc_textarea($question['question_text']); ?></textarea>
                                    <p class="sp-field-error sp-qtext-error" style="display:none;">Question text is required.</p>
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
                                    <button type="button" class="button-secondary sp-add-scale">+ Add Option</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php
                    // Render trailing page breaks: page breaks placed after the last question.
                    // Identified by page_headers keys greater than the highest question page number.
                    if (!empty($page_headers_decoded)) :
                        $max_header_page = max(array_map('intval', array_keys($page_headers_decoded)));
                        for ($tp = $prev_page + 1; $tp <= $max_header_page; $tp++) :
                            $ph_value = isset($page_headers_decoded[$tp]) ? $page_headers_decoded[$tp] : '';
                    ?>
                        <div class="sp-page-break">
                            <div class="sp-page-break-bar">
                                <div class="sp-page-break-line"></div>
                                <span class="sp-page-break-label">Page Break</span>
                                <button type="button" class="button-link sp-page-break-remove" aria-label="Remove page break">
                                    <img src="<?php echo esc_url(SP_URL . 'assets/images/trash-can.svg'); ?>" alt="" class="sp-trash-icon" width="18" height="18">
                                </button>
                                <div class="sp-page-break-line"></div>
                            </div>
                            <div class="sp-page-header-field">
                                <label class="sp-page-header-label">Page <span class="sp-page-number-display"><?php echo esc_html($tp); ?></span> Header</label>
                                <input
                                    type="text"
                                    class="regular-text sp-page-header-input"
                                    name="sp_page_headers[<?php echo esc_attr($tp); ?>]"
                                    value="<?php echo esc_attr($ph_value); ?>"
                                    placeholder="Optional page header…"
                                >
                            </div>
                        </div>
                    <?php   endfor; endif; ?>

                <?php endif; ?>
            </div>

            <div class="sp-question-builder-actions">
                <button type="button" class="button sp-btn-large" id="sp-add-question">+ Add Question</button>
                <button type="button" class="button sp-btn-large" id="sp-add-page-break" disabled>+ Add Page Break</button>
            </div>

            <div id="sp-question-template" style="display:none;">
                <div class="sp-question-card" data-question-index="__INDEX__">
                    <input type="hidden" class="sp-page-input" name="sp_questions[__INDEX__][page]" value="1">
                    <div class="sp-question-header">
                        <span class="sp-question-label">Question <span class="sp-question-number"></span></span>
                        <button type="button" class="button-link sp-question-remove" aria-label="Delete question">
                            <img src="<?php echo esc_url(SP_URL . 'assets/images/trash-can.svg'); ?>" alt="" class="sp-trash-icon" width="22" height="22">
                        </button>
                    </div>
                    <div class="sp-question-body">
                        <div class="sp-field">
                            <label>Question Text<span class="sp-required" aria-hidden="true">*</span></label>
                            <textarea
                                class="regular-text sp-question-textarea"
                                name="sp_questions[__INDEX__][text]"
                            ></textarea>
                            <p class="sp-field-error sp-qtext-error" style="display:none;">Question text is required.</p>
                        </div>
                        <div class="sp-field">
                            <label>Scale Options</label>
                            <div class="sp-scale-rows">
                                <?php for ($i = 0; $i < 5; $i++) : $val = $i + 1; ?>
                                    <div class="sp-scale-row" data-scale-value="<?php echo esc_attr($val); ?>">
                                        <input type="hidden" name="sp_questions[__INDEX__][scale][<?php echo esc_attr($i); ?>][value]" value="<?php echo esc_attr($val); ?>">
                                        <input type="number" class="small-text" value="<?php echo esc_attr($val); ?>" readonly>
                                        <input type="text" class="regular-text" name="sp_questions[__INDEX__][scale][<?php echo esc_attr($i); ?>][label]" placeholder="Label for <?php echo esc_attr($val); ?>">
                                    </div>
                                <?php endfor; ?>
                            </div>
                            <button type="button" class="button-secondary sp-add-scale">+ Add Option</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <p id="sp-questions-error" class="sp-questions-error" style="display:none;">You must add at least one question before saving the survey.</p>

        <?php submit_button($submit_label, 'primary sp-btn-large'); ?>
    </form>
</div>