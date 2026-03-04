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
        ORDER BY question_order ASC",
        $sp_survey_id
    ),
    ARRAY_A
);

if (!$questions) {
    echo '<div class="sp-container"><p class="sp-notice">No questions found for this survey.</p></div>';
    return;
}

// Group consecutive questions that share the same scale into table groups
$groups = [];
$current_group = [];
$prev_key = null;

foreach ($questions as $q) {
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

    <?php
    $confirmation_url = esc_url(add_query_arg(
        ['sp_step' => 'confirmation', 'sp_survey_id' => (int) $sp_survey_id],
        get_permalink()
    ));
    ?>

    <form method="post" action="<?php echo $confirmation_url; ?>">

    <?php
    $question_number = 1;

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
                                    name="question_<?php echo $question_id; ?>"
                                    value="<?php echo $i; ?>"
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
        <button type="submit" class="sp-button">Submit Survey</button>
    </form>
</div>