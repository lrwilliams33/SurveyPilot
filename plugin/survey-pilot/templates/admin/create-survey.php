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

        <table class="form-table">
            <tr>
                <th><label for="sp_survey_title">Survey Title</label></th>
                <td>
                    <input
                        type="text"
                        name="sp_survey_title"
                        id="sp_survey_title"
                        required
                        class="regular-text"
                        value="<?php echo $is_edit ? esc_attr($survey['title']) : ''; ?>"
                    >
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

        <div id="sp-question-builder" data-next-index="<?php echo esc_attr(count($questions)); ?>">
            <div id="sp-questions-list">
                <?php if (!empty($questions)) : ?>
                    <?php foreach ($questions as $index => $question) : ?>
                        <?php
                        $question_index = (int) $index;
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
                            <div class="sp-question-header">
                                <span class="sp-question-label">Question <span class="sp-question-number"></span></span>
                                <button type="button" class="button-link sp-question-remove" aria-label="Delete question">
                                    <img src="<?php echo esc_url(SP_URL . 'assets/images/trash-can.svg'); ?>" alt="" class="sp-trash-icon" width="22" height="22">
                                </button>
                            </div>
                            <div class="sp-question-body">
                                <div class="sp-field sp-field-block">
                                    <label>Question Title</label>
                                    <input
                                        type="text"
                                        class="regular-text"
                                        name="sp_questions[<?php echo esc_attr($question_index); ?>][title]"
                                        value="<?php echo esc_attr($question['question_title']); ?>"
                                    >
                                </div>
                                <div class="sp-field">
                                    <label>Question Text</label>
                                    <textarea
                                        class="regular-text sp-question-textarea"
                                        name="sp_questions[<?php echo esc_attr($question_index); ?>][text]"
                                    ><?php echo esc_textarea($question['question_text']); ?></textarea>
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
                <?php endif; ?>
            </div>

            <button type="button" class="button sp-btn-large" id="sp-add-question">+ Add Question</button>

            <div id="sp-question-template" style="display:none;">
                <div class="sp-question-card" data-question-index="__INDEX__">
                    <div class="sp-question-header">
                        <span class="sp-question-label">Question <span class="sp-question-number"></span></span>
                        <button type="button" class="button-link sp-question-remove" aria-label="Delete question">
                            <img src="<?php echo esc_url(SP_URL . 'assets/images/trash-can.svg'); ?>" alt="" class="sp-trash-icon" width="22" height="22">
                        </button>
                    </div>
                    <div class="sp-question-body">
                        <div class="sp-field sp-field-block">
                            <label>Question Title</label>
                            <input
                                type="text"
                                class="regular-text"
                                name="sp_questions[__INDEX__][title]"
                            >
                        </div>
                        <div class="sp-field">
                            <label>Question Text</label>
                            <textarea
                                class="regular-text sp-question-textarea"
                                name="sp_questions[__INDEX__][text]"
                            ></textarea>
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

        <?php submit_button($submit_label, 'primary sp-btn-large'); ?>
    </form>
</div>