<?php
if (!defined('ABSPATH')) exit;
?>
<link rel="stylesheet" href="<?php echo esc_url(SP_URL . 'templates/user-templates/styles.css'); ?>">
<?php

global $wpdb;

$questions = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT *
        FROM {$wpdb->prefix}survey_questions
        WHERE survey_id = %d
        ORDER BY question_order ASC, id ASC",
        $sp_survey_id
    ),
    ARRAY_A
);

if (!$questions) {
    echo '<div class="sp-container"><p class="sp-notice">No questions found for this survey.</p></div>';
    return;
}

$survey_info = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT survey_layout FROM {$wpdb->prefix}survey_info WHERE id = %d",
        $sp_survey_id
    ),
    ARRAY_A
);

$resolved = sp_user_resolve_survey_pages_and_headers(
    $questions,
    ($survey_info && !empty($survey_info['survey_layout'])) ? $survey_info['survey_layout'] : null
);

$pages              = $resolved['pages'];
$page_headers       = $resolved['page_headers'];
$all_page_numbers   = $resolved['all_page_numbers'];

if (empty($all_page_numbers)) {
    echo '<div class="sp-container"><p class="sp-notice">No pages found for this survey.</p></div>';
    return;
}

// Get current page from query parameter or POST, default to first page
$current_page = 1;
if (isset($_GET['sp_page'])) {
    $current_page = (int) $_GET['sp_page'];
} elseif (isset($_POST['sp_current_page'])) {
    $current_page = (int) $_POST['sp_current_page'];
}

// Fallback to the first valid page if the requested page is not available
if (!in_array($current_page, $all_page_numbers, true)) {
    $current_page = reset($all_page_numbers);
}

$total_pages = count($all_page_numbers);

$layout_json = ($survey_info && !empty($survey_info['survey_layout'])) ? $survey_info['survey_layout'] : null;
$segments = sp_user_page_render_segments($current_page, $questions, $layout_json);

$question_numbers_by_id = [];
foreach ($questions as $idx => $q_row) {
    $question_numbers_by_id[ (int) $q_row['id'] ] = $idx + 1;
}
?>

<div class="sp-container">

    <?php if (!empty($page_headers[$current_page])) : ?>
        <h2><?php echo esc_html($page_headers[$current_page]); ?></h2>
    <?php endif; ?>

    <?php
    $confirmation_url = esc_url(admin_url('admin-post.php'));
    ?>

    <form method="post" action="<?php echo $confirmation_url; ?>" class="sp-survey-form">
        <input type="hidden" name="action" value="sp_submit_survey">
        <input type="hidden" name="sp_survey_id" value="<?php echo (int) $sp_survey_id; ?>">
        <input type="hidden" name="sp_current_page" value="<?php echo (int) $current_page; ?>" class="sp-current-page-input">
        <input type="hidden" name="is_final_submission" value="0" class="sp-is-final-submission">

    <?php
    wp_nonce_field('sp_submit_survey');

    foreach ($segments as $segment) :
        $seg_type = $segment['type'] ?? '';
        if ($seg_type === 'text') :
            $text_content = isset($segment['content']) ? (string) $segment['content'] : '';
            if ($text_content === '') {
                continue;
            }
            ?>
        <div class="sp-layout-text"><?php echo nl2br(esc_html($text_content)); ?></div>
            <?php
            continue;
        endif;

        if ($seg_type !== 'question_table' || empty($segment['questions'])) {
            continue;
        }

        $group_questions = $segment['questions'];
        $sample          = $group_questions[0];
        $scale_min       = (int) $sample['scale_min'];
        $scale_max       = (int) $sample['scale_max'];
        $scale_labels    = $sample['scale_labels'] ?? '';
        $labels          = [];
        if (!empty($scale_labels)) {
            $decoded = json_decode($scale_labels, true);
            if (is_array($decoded)) {
                $labels = $decoded;
            }
        }

        $nums = [];
        foreach ($group_questions as $gq) {
            $nums[] = $question_numbers_by_id[ (int) $gq['id'] ] ?? 0;
        }
        $nums = array_values(array_filter($nums));
        sort($nums, SORT_NUMERIC);
        $group_first = $nums ? (int) $nums[0] : 0;
        $group_last  = $nums ? (int) $nums[ count($nums) - 1 ] : 0;
        ?>

        <div class="sp-table-wrapper">
            <table class="sp-question-table">
                <thead>
                    <tr>
                        <th class="sp-q-col">
                            <?php if ($group_first === $group_last) : ?>
                                Question <?php echo (int) $group_first; ?>
                            <?php else : ?>
                                Questions <?php echo (int) $group_first; ?> through <?php echo (int) $group_last; ?>
                            <?php endif; ?>
                        </th>
                        <?php for ($i = $scale_min; $i <= $scale_max; $i++) :
                            $val_label = isset($labels[$i]) ? $labels[$i] : '';
                            ?>
                            <th>
                                <?php echo $i; ?>
                                <?php if ($val_label !== '') : ?>
                                    <span class="sp-th-label"><?php echo esc_html($val_label); ?></span>
                                <?php endif; ?>
                            </th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($group_questions as $q) :
                    $question_id = (int) $q['id'];
                    $disp_num    = $question_numbers_by_id[ $question_id ] ?? 0;
                    ?>
                    <tr>
                        <td class="sp-q-col"><?php echo (int) $disp_num; ?>. <?php echo esc_html($q['question_text']); ?></td>
                        <?php for ($i = $scale_min; $i <= $scale_max; $i++) : ?>
                            <td class="sp-radio-cell">
                                <input
                                    type="radio"
                                    name="sp_answers[<?php echo $question_id; ?>]"
                                    value="<?php echo $i; ?>"
                                    data-page-number="<?php echo (int) $q['page_number']; ?>"
                                    data-question-order="<?php echo (int) $q['question_order']; ?>"
                                    required
                                >
                            </td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
    endforeach;
    ?>

        <div class="sp-page-indicator sp-page-indicator--footer">
            <span class="sp-page-number">Page <?php echo (int) $current_page; ?> of <?php echo (int) $total_pages; ?></span>
        </div>

        <div class="sp-navigation">
            <?php if ($current_page > 1) : 
                $prev_url = esc_url(add_query_arg(
                    ['sp_step' => 'survey', 'sp_survey_id' => (int) $sp_survey_id, 'sp_page' => $current_page - 1],
                    get_permalink()
                ));
            ?>
                <button type="button" class="sp-button sp-button-secondary sp-prev-btn" data-href="<?php echo $prev_url; ?>">← Previous</button>
            <?php endif; ?>

            <?php if ($current_page < $total_pages) : 
                $next_url = esc_url(add_query_arg(
                    ['sp_step' => 'survey', 'sp_survey_id' => (int) $sp_survey_id, 'sp_page' => $current_page + 1],
                    get_permalink()
                ));
            ?>
                <button type="button" class="sp-button sp-next-btn" data-href="<?php echo $next_url; ?>">Next →</button>
            <?php else : ?>
                <button type="submit" class="sp-button">Submit Survey</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('.sp-survey-form');
        if (!form) return;

        const prevBtn = document.querySelector('.sp-prev-btn');
        const nextBtn = document.querySelector('.sp-next-btn');
        const submitBtn = form.querySelector('button[type="submit"]');
        const surveyId = <?php echo (int) $sp_survey_id; ?>;

        const STORAGE_KEY = 'sp_survey_answers_' + surveyId;
        const EXPIRY_KEY = 'sp_survey_answers_expiry_' + surveyId;
        const EXPIRY_MS = 24 * 60 * 60 * 1000; // 24 hours

        function now() {
            return Date.now();
        }

        function isExpired() {
            const expiry = parseInt(localStorage.getItem(EXPIRY_KEY), 10);
            return !expiry || now() > expiry;
        }

        function clearSavedAnswers() {
            localStorage.removeItem(STORAGE_KEY);
            localStorage.removeItem(EXPIRY_KEY);
        }

        function getSavedAnswers() {
            if (isExpired()) {
                clearSavedAnswers();
                return {};
            }

            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                return raw ? JSON.parse(raw) : {};
            } catch (e) {
                console.error('Could not parse saved survey answers:', e);
                clearSavedAnswers();
                return {};
            }
        }

        function saveAllAnswers(answers) {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(answers));
                localStorage.setItem(EXPIRY_KEY, String(now() + EXPIRY_MS));
            } catch (e) {
                console.error('Could not save survey answers:', e);
            }
        }

        function saveSingleAnswer(questionId, answerValue) {
            const answers = getSavedAnswers();
            answers[String(questionId)] = String(answerValue);
            saveAllAnswers(answers);
        }

        function restoreAnswersToPage() {
            const answers = getSavedAnswers();
            const radios = form.querySelectorAll('input[type="radio"][name*="sp_answers"]');

            radios.forEach(radio => {
                const match = radio.name.match(/\[(\d+)\]/);
                if (!match) return;

                const questionId = match[1];
                if (
                    Object.prototype.hasOwnProperty.call(answers, questionId) &&
                    String(answers[questionId]) === String(radio.value)
                ) {
                    radio.checked = true;
                }
            });
        }

        function getCurrentPageAnswers() {
            const answers = {};
            const checkedRadios = form.querySelectorAll('input[type="radio"][name*="sp_answers"]:checked');

            checkedRadios.forEach(radio => {
                const match = radio.name.match(/\[(\d+)\]/);
                if (!match) return;

                const questionId = match[1];
                answers[questionId] = radio.value;
            });

            return answers;
        }

        function syncCurrentPageAnswersToStorage() {
            const storedAnswers = getSavedAnswers();
            const currentPageAnswers = getCurrentPageAnswers();
            const mergedAnswers = { ...storedAnswers, ...currentPageAnswers };
            saveAllAnswers(mergedAnswers);
        }

        function allQuestionsOnPageAnswered() {
            const requiredFields = form.querySelectorAll('input[type="radio"][required]');
            const questionGroups = {};

            requiredFields.forEach(field => {
                if (!questionGroups[field.name]) {
                    questionGroups[field.name] = [];
                }
                questionGroups[field.name].push(field);
            });

            for (const questionName in questionGroups) {
                const isAnswered = questionGroups[questionName].some(field => field.checked);
                if (!isAnswered) {
                    return false;
                }
            }

            return true;
        }

        restoreAnswersToPage();

        const allRadios = form.querySelectorAll('input[type="radio"][name*="sp_answers"]');
        allRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                const match = this.name.match(/\[(\d+)\]/);
                if (!match) return;

                const questionId = match[1];
                saveSingleAnswer(questionId, this.value);
            });
        });

        if (prevBtn) {
            prevBtn.addEventListener('click', function(e) {
                e.preventDefault();
                syncCurrentPageAnswersToStorage();
                window.location.href = prevBtn.getAttribute('data-href');
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function(e) {
                e.preventDefault();

                if (!allQuestionsOnPageAnswered()) {
                    alert('Please answer all questions on this page before proceeding.');
                    return;
                }

                syncCurrentPageAnswersToStorage();
                window.location.href = nextBtn.getAttribute('data-href');
            });
        }

        if (submitBtn) {
            submitBtn.addEventListener('click', function(e) {
                e.preventDefault();

                if (!allQuestionsOnPageAnswered()) {
                    alert('Please answer all questions on this page before submitting.');
                    return;
                }

                syncCurrentPageAnswersToStorage();

                const savedAnswers = getSavedAnswers();

                form.querySelectorAll('input[type="hidden"][name*="sp_answers"]').forEach(h => h.remove());

                Object.entries(savedAnswers)
                    .sort((a, b) => Number(a[0]) - Number(b[0]))
                    .forEach(([questionId, answerValue]) => {
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'sp_answers[' + questionId + ']';
                        hiddenInput.value = answerValue;
                        form.appendChild(hiddenInput);
                    });

                form.submit();
            });
        }
    });
</script>