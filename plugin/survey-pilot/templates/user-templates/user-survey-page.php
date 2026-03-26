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
        ORDER BY page_number ASC, question_order ASC",
        $sp_survey_id
    ),
    ARRAY_A
);

if (!$questions) {
    echo '<div class="sp-container"><p class="sp-notice">No questions found for this survey.</p></div>';
    return;
}

// Group questions by page number
$pages = [];
foreach ($questions as $q) {
    $page_num = (int) ($q['page_number'] ?? 1);
    if (!isset($pages[$page_num])) {
        $pages[$page_num] = [];
    }
    $pages[$page_num][] = $q;
}

ksort($pages);

// Get current page from query parameter or POST, default to first page
$current_page = 1;
if (isset($_GET['sp_page'])) {
    $current_page = (int) $_GET['sp_page'];
} elseif (isset($_POST['sp_current_page'])) {
    $current_page = (int) $_POST['sp_current_page'];
}

if (!isset($pages[$current_page])) {
    $current_page = min(array_keys($pages));
}

$total_pages = count($pages);
$current_questions = $pages[$current_page] ?? [];

// Group current page's questions that share the same scale into table groups
$groups = [];
$current_group = [];
$prev_key = null;

foreach ($current_questions as $q) {
    $key = $q['scale_min'] . '|' . $q['scale_max'] . '|' . ($q['scale_labels'] ?? '');
    if ($prev_key !== null && $key !== $prev_key) {
        $groups[] = ['key' => $prev_key, 'questions' => $current_group];
        $current_group = [];
    }
    $current_group[] = $q;
    $prev_key = $key;
}
if (!empty($current_group)) {
    $groups[] = ['key' => $prev_key, 'questions' => $current_group];
}
?>

<div class="sp-container">

    <h2>Survey Questions</h2>

    <div class="sp-page-indicator">
        <span class="sp-page-number">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
    </div>

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
    $question_number = 1;

    foreach (array_slice(array_keys($pages), 0, array_search($current_page, array_keys($pages))) as $prev_page) {
        $question_number += count($pages[$prev_page]);
    }

    foreach ($groups as $group) :
        $group_questions = $group['questions'];
        $sample = $group_questions[0];
        $scale_min   = (int) $sample['scale_min'];
        $scale_max   = (int) $sample['scale_max'];
        $scale_labels = $sample['scale_labels'] ?? '';
        $labels = [];
        if (!empty($scale_labels)) {
            $decoded = json_decode($scale_labels, true);
            if (is_array($decoded)) {
                $labels = $decoded;
            }
        }

        $group_first = $question_number;
        $group_last  = $question_number + count($group_questions) - 1;
    ?>

        <div class="sp-table-wrapper">
            <table class="sp-question-table">
                <thead>
                    <tr>
                        <th class="sp-q-col">
                            <?php if ($group_first === $group_last) : ?>
                                Question <?php echo $group_first; ?>
                            <?php else : ?>
                                Questions <?php echo $group_first; ?> through <?php echo $group_last; ?>
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
                ?>
                    <tr>
                        <td class="sp-q-col"><?php echo $question_number . '. ' . esc_html($q['question_text']); ?></td>
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
                <?php
                    $question_number++;
                endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php
    endforeach;
    ?>

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