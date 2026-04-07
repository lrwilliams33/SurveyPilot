<?php
/**
 * Survey linear layout (questions, text, page breaks, page headers) shared by admin and frontend.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keep only valid numeric indices from sp_questions (drops template keys like "__INDEX__").
 *
 * @param array|null $questions_post
 * @return array<int, mixed>
 */
function sp_normalize_questions_post_array($questions_post) {
    if (!is_array($questions_post)) {
        return [];
    }
    $out = [];
    foreach ($questions_post as $k => $q) {
        if (is_int($k) && $k >= 0) {
            $out[$k] = $q;
            continue;
        }
        if (is_string($k) && $k !== '' && ctype_digit($k)) {
            $out[(int) $k] = $q;
        }
    }
    ksort($out, SORT_NUMERIC);
    return $out;
}

/**
 * @param array<int, mixed> $legacy_ph page number => header text (may be empty)
 * @return array<int, array<string, mixed>>
 */
function sp_layout_prepend_page1_header_block_if_missing(array $blocks, array $legacy_ph) {
    if (empty($blocks)) {
        return $blocks;
    }
    $first = $blocks[0];
    if (($first['type'] ?? '') === 'page_header' && (int) ($first['page'] ?? 0) === 1) {
        return $blocks;
    }
    $h1 = isset($legacy_ph[1]) ? (string) $legacy_ph[1] : '';
    array_unshift(
        $blocks,
        [
            'type'   => 'page_header',
            'page'   => 1,
            'header' => $h1,
        ]
    );
    return $blocks;
}

/**
 * Fill missing page_break.header (and page_header.header) from legacy page_headers JSON.
 *
 * @param array<int, array<string, mixed>> $blocks
 * @param array<int, string>             $legacy_ph
 * @return array<int, array<string, mixed>>
 */
function sp_enrich_layout_page_break_headers_from_legacy(array $blocks, array $legacy_ph) {
    $page_num = 1;
    $out      = [];
    foreach ($blocks as $b) {
        $t = $b['type'] ?? '';
        if ($t === 'page_header' && (int) ($b['page'] ?? 0) === 1) {
            if (!array_key_exists('header', $b)) {
                $b['header'] = isset($legacy_ph[1]) ? (string) $legacy_ph[1] : '';
            }
            $out[] = $b;
            continue;
        }
        if ($t === 'page_break') {
            $page_num++;
            if (!array_key_exists('header', $b)) {
                $b['header'] = isset($legacy_ph[$page_num]) ? (string) $legacy_ph[$page_num] : '';
            }
            $out[] = $b;
            continue;
        }
        $out[] = $b;
    }
    return $out;
}

/**
 * Build page number => header map from layout blocks (empty strings allowed).
 *
 * @param array<int, array<string, mixed>> $blocks
 * @return array<int, string>
 */
function sp_page_headers_map_from_layout_blocks(array $blocks) {
    $headers = [1 => ''];
    $page    = 1;
    foreach ($blocks as $b) {
        $t = $b['type'] ?? '';
        if ($t === 'page_header' && (int) ($b['page'] ?? 0) === 1) {
            $headers[1] = (string) ($b['header'] ?? '');
            continue;
        }
        if ($t === 'page_break') {
            $page++;
            $headers[ $page ] = (string) ($b['header'] ?? '');
        }
    }
    return $headers;
}

/**
 * Highest page index implied by walking page_break markers (minimum 1).
 *
 * @param array<int, array<string, mixed>> $blocks
 */
function sp_layout_max_page_from_blocks(array $blocks) {
    $p = 1;
    foreach ($blocks as $b) {
        if (($b['type'] ?? '') === 'page_break') {
            $p++;
        }
    }
    return $p;
}

/**
 * Page number for each question block in layout order (same order as questions ordered by question_order, id).
 *
 * @param array<int, array<string, mixed>> $blocks
 * @return array<int, int> 0-based question index => page (1-based)
 */
function sp_question_pages_list_from_layout(array $blocks) {
    $list = [];
    $page = 1;
    foreach ($blocks as $b) {
        $t = $b['type'] ?? '';
        if ($t === 'page_break') {
            $page++;
        } elseif ($t === 'question') {
            $list[] = $page;
        }
    }
    return $list;
}

/**
 * Normalized layout blocks for the public survey (same rules as admin save).
 *
 * @param array<int, array<string, mixed>> $questions_ordered DB rows ORDER BY question_order, id
 * @param string|null                      $layout_json
 * @return array<int, array<string, mixed>>
 */
function sp_user_normalized_survey_layout_blocks(array $questions_ordered, $layout_json) {
    $blocks = [];
    if ($layout_json !== null && $layout_json !== '') {
        $decoded = json_decode($layout_json, true);
        if (is_array($decoded) && !empty($decoded)) {
            $blocks = $decoded;
        }
    }
    if (empty($blocks)) {
        $blocks = [
            [
                'type'   => 'page_header',
                'page'   => 1,
                'header' => '',
            ],
        ];
        foreach ($questions_ordered as $_unused) {
            $blocks[] = ['type' => 'question'];
        }
    }

    $blocks = sp_layout_prepend_page1_header_block_if_missing($blocks, []);
    $blocks = sp_enrich_layout_page_break_headers_from_legacy($blocks, []);

    return $blocks;
}

/**
 * Scale signature for grouping questions into one matrix table (must match between rows).
 *
 * @param array<string, mixed> $q
 */
function sp_user_question_scale_key(array $q) {
    return $q['scale_min'] . '|' . $q['scale_max'] . '|' . ($q['scale_labels'] ?? '');
}

/**
 * Ordered segments for one survey page: standalone text blocks and question tables.
 * A text block always breaks the table; adjacent questions with different scales use separate tables.
 *
 * @param int                              $page_num          1-based
 * @param array<int, array<string, mixed>> $questions_ordered DB rows ORDER BY question_order, id
 * @param string|null                      $layout_json
 * @return array<int, array<string, mixed>> Each element is either question_table (questions[]) or text (content)
 */
function sp_user_page_render_segments($page_num, array $questions_ordered, $layout_json) {
    $page_num = max(1, (int) $page_num);
    $blocks   = sp_user_normalized_survey_layout_blocks($questions_ordered, $layout_json);

    $segments = [];
    $page     = 1;
    $qi       = 0;
    $pending  = [];

    $flush = static function () use (&$segments, &$pending) {
        if ($pending !== []) {
            $segments[] = [
                'type'      => 'question_table',
                'questions' => $pending,
            ];
            $pending     = [];
        }
    };

    foreach ($blocks as $b) {
        $t = $b['type'] ?? '';
        if ($t === 'page_header') {
            continue;
        }
        if ($t === 'page_break') {
            if ($page === $page_num) {
                $flush();
            }
            $page++;
            continue;
        }
        if ($t === 'text') {
            if ($page === $page_num) {
                $flush();
                $content = isset($b['content']) ? (string) $b['content'] : '';
                if ($content !== '') {
                    $segments[] = [
                        'type'    => 'text',
                        'content' => $content,
                    ];
                }
            }
            continue;
        }
        if ($t === 'question') {
            if (! array_key_exists($qi, $questions_ordered)) {
                $qi++;
                continue;
            }
            $q                = $questions_ordered[ $qi ];
            $qi++;
            $q                = is_array($q) ? $q : (array) $q;
            $q['page_number'] = $page;

            if ($page !== $page_num) {
                continue;
            }

            if ($pending === []) {
                $pending[] = $q;
                continue;
            }

            $prev_key = sp_user_question_scale_key($pending[ count($pending) - 1 ]);
            $new_key  = sp_user_question_scale_key($q);
            if ($new_key !== $prev_key) {
                $flush();
            }
            $pending[] = $q;
        }
    }

    $flush();

    return $segments;
}

/**
 * Resolve grouped questions, per-page header text, and page indices for the public survey UI.
 * Question rows need not include page_number; it is derived from survey_layout.
 *
 * @param array<int, array<string, mixed>> $questions_ordered DB rows ORDER BY question_order, id
 * @param string|null                      $layout_json       survey_layout column
 * @return array{pages: array<int, array>, page_headers: array<int, string>, all_page_numbers: int[], max_page: int}
 */
function sp_user_resolve_survey_pages_and_headers(array $questions_ordered, $layout_json) {
    $blocks       = sp_user_normalized_survey_layout_blocks($questions_ordered, $layout_json);
    $page_headers = sp_page_headers_map_from_layout_blocks($blocks);
    $q_pages      = sp_question_pages_list_from_layout($blocks);

    $questions_with_page = [];
    foreach ($questions_ordered as $i => $q) {
        $q                   = is_array($q) ? $q : (array) $q;
        $q['page_number']    = $q_pages[ $i ] ?? 1;
        $questions_with_page[] = $q;
    }

    $pages = [];
    foreach ($questions_with_page as $q) {
        $pn = (int) $q['page_number'];
        if (!isset($pages[ $pn ])) {
            $pages[ $pn ] = [];
        }
        $pages[ $pn ][] = $q;
    }
    if (!empty($pages)) {
        ksort($pages);
    }

    $max_layout = sp_layout_max_page_from_blocks($blocks);
    $max_q      = !empty($pages) ? max(array_keys($pages)) : 1;
    $max_page   = max($max_layout, $max_q, 1);
    for ($pn = 1; $pn <= $max_page; $pn++) {
        if (!array_key_exists($pn, $page_headers)) {
            $page_headers[ $pn ] = '';
        }
    }
    ksort($page_headers);
    $all_page_numbers = range(1, $max_page);

    return [
        'pages'            => $pages,
        'page_headers'     => $page_headers,
        'all_page_numbers' => $all_page_numbers,
        'max_page'         => $max_page,
    ];
}

/**
 * Map question id => survey page (1-based) from survey_layout.
 *
 * @return array<int, int>
 */
function sp_get_question_id_to_page_map($survey_id) {
    global $wpdb;

    $survey_id = (int) $survey_id;
    if ($survey_id <= 0) {
        return [];
    }

    $layout_json = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT survey_layout FROM {$wpdb->prefix}survey_info WHERE id = %d",
            $survey_id
        )
    );

    $questions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}survey_questions WHERE survey_id = %d ORDER BY question_order ASC, id ASC",
            $survey_id
        ),
        ARRAY_A
    );

    $blocks  = sp_user_normalized_survey_layout_blocks($questions, $layout_json);
    $q_pages = sp_question_pages_list_from_layout($blocks);
    $map     = [];
    foreach ($questions as $i => $row) {
        $map[ (int) $row['id'] ] = $q_pages[ $i ] ?? 1;
    }

    return $map;
}

/**
 * One-time migration: build survey_layout from legacy page_headers + question page_number.
 *
 * @param array<string, mixed> $survey_row survey_info row (may include page_headers)
 * @param array<int, array>    $questions  ordered rows with page_number
 */
function sp_build_survey_layout_from_legacy_survey_row(array $survey_row, array $questions) {
    $legacy = [];
    if (!empty($survey_row['page_headers'])) {
        $decoded = json_decode($survey_row['page_headers'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $k => $v) {
                $legacy[ (int) $k ] = (string) $v;
            }
        }
    }

    $blocks   = [];
    $blocks[] = [
        'type'   => 'page_header',
        'page'   => 1,
        'header' => $legacy[1] ?? '',
    ];

    if (empty($questions)) {
        return wp_json_encode(array_values($blocks));
    }

    $prev_page   = 1;
    $last_q_page = 1;
    foreach ($questions as $q) {
        $current_page = isset($q['page_number']) ? max(1, (int) $q['page_number']) : 1;
        $last_q_page  = $current_page;
        while ($current_page > $prev_page) {
            $next_page = $prev_page + 1;
            $blocks[]  = [
                'type'   => 'page_break',
                'header' => $legacy[ $next_page ] ?? '',
            ];
            $prev_page = $next_page;
        }
        $blocks[] = ['type' => 'question'];
    }

    $max_header_page = empty($legacy) ? 0 : max(array_keys($legacy));
    for ($p = $last_q_page + 1; $p <= $max_header_page; $p++) {
        $blocks[] = [
            'type'   => 'page_break',
            'header' => $legacy[ $p ] ?? '',
        ];
    }

    return wp_json_encode(array_values($blocks));
}

/**
 * Read sp_page_headers[N] helper for infer (supports numeric string keys from POST).
 *
 * @param array<string|int, mixed> $hdr_post
 */
function sp_infer_header_value_for_page(array $hdr_post, $page_num) {
    $page_num = (int) $page_num;
    if (isset($hdr_post[ $page_num ])) {
        return sanitize_text_field(wp_unslash($hdr_post[ $page_num ]));
    }
    $key = (string) $page_num;
    if (isset($hdr_post[ $key ])) {
        return sanitize_text_field(wp_unslash($hdr_post[ $key ]));
    }
    return '';
}

/**
 * Build layout from POST when sp_survey_layout is missing (no JS).
 *
 * @param array<int, array<string, mixed>>|array<string, mixed> $questions_post
 * @param array<string|int, mixed>|null                        $page_headers_post
 * @return array<int, array<string, mixed>>
 */
function sp_infer_survey_layout_array_from_post($questions_post, $page_headers_post) {
    $blocks    = [];
    $hdr_post  = is_array($page_headers_post) ? $page_headers_post : [];
    $blocks[]  = [
        'type'   => 'page_header',
        'page'   => 1,
        'header' => sp_infer_header_value_for_page($hdr_post, 1),
    ];

    $posted = is_array($questions_post) ? $questions_post : [];
    ksort($posted, SORT_NUMERIC);

    if (empty($posted)) {
        return $blocks;
    }

    $prev_page   = 1;
    $last_q_page = 1;
    foreach ($posted as $q) {
        $current_page = isset($q['page']) ? max(1, (int) $q['page']) : 1;
        $last_q_page  = $current_page;
        while ($current_page > $prev_page) {
            $next_page = $prev_page + 1;
            $blocks[]  = [
                'type'   => 'page_break',
                'header' => sp_infer_header_value_for_page($hdr_post, $next_page),
            ];
            $prev_page = $next_page;
        }
        $blocks[] = ['type' => 'question'];
    }

    $max_header_page = 0;
    foreach ($hdr_post as $page_num => $_unused) {
        $max_header_page = max($max_header_page, absint($page_num));
    }
    for ($p = $last_q_page + 1; $p <= $max_header_page; $p++) {
        $blocks[] = [
            'type'   => 'page_break',
            'header' => sp_infer_header_value_for_page($hdr_post, $p),
        ];
    }

    return $blocks;
}

/**
 * Ordered structural sequence: 'q' (question) and 'pb' (page_break) only; ignores text and headers.
 *
 * @param array<int, array<string, mixed>> $blocks
 * @return array<int, string>
 */
function sp_layout_structure_signature_from_blocks(array $blocks) {
    $sig = [];
    foreach ($blocks as $b) {
        $t = $b['type'] ?? '';
        if ($t === 'question') {
            $sig[] = 'q';
        } elseif ($t === 'page_break') {
            $sig[] = 'pb';
        }
    }
    return $sig;
}

/**
 * When a survey has responses, block structural edits (question ids/order, page breaks, scale size).
 *
 * @param int $survey_id
 * @return true|WP_Error
 */
function sp_validate_locked_survey_edit($survey_id) {
    global $wpdb;

    $survey_id = absint($survey_id);
    if ($survey_id <= 0) {
        return true;
    }

    $response_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}survey_response_info WHERE survey_id = %d",
            $survey_id
        )
    );
    if ($response_count < 1) {
        return true;
    }

    $survey_row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT survey_layout FROM {$wpdb->prefix}survey_info WHERE id = %d",
            $survey_id
        ),
        ARRAY_A
    );
    if (!$survey_row) {
        return new WP_Error('sp_survey_missing', 'Survey not found.');
    }

    $db_questions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, scale_min, scale_max FROM {$wpdb->prefix}survey_questions WHERE survey_id = %d ORDER BY question_order ASC, id ASC",
            $survey_id
        ),
        ARRAY_A
    );

    $posted = sp_normalize_questions_post_array(isset($_POST['sp_questions']) ? $_POST['sp_questions'] : null);

    if (count($posted) !== count($db_questions)) {
        return new WP_Error(
            'sp_locked_questions',
            'This survey has responses; you cannot add or remove questions.'
        );
    }

    $i = 0;
    foreach ($posted as $q) {
        $existing_id = isset($q['id']) ? absint($q['id']) : 0;
        if ((int) $db_questions[ $i ]['id'] !== $existing_id) {
            return new WP_Error(
                'sp_locked_question_ids',
                'This survey has responses; question structure cannot be changed.'
            );
        }

        $scale_rows = isset($q['scale']) && is_array($q['scale']) ? $q['scale'] : [];
        $values     = [];
        foreach ($scale_rows as $row) {
            $v = isset($row['value']) ? intval($row['value']) : 0;
            if ($v > 0) {
                $values[] = $v;
            }
        }
        sort($values);
        $values    = array_unique($values);
        $scale_min = !empty($values) ? (int) reset($values) : 1;
        $scale_max = !empty($values) ? (int) end($values) : 5;

        if ((int) $db_questions[ $i ]['scale_min'] !== $scale_min || (int) $db_questions[ $i ]['scale_max'] !== $scale_max) {
            return new WP_Error(
                'sp_locked_scale',
                'This survey has responses; scale options cannot be added or removed.'
            );
        }
        $i++;
    }

    $raw_layout = isset($_POST['sp_survey_layout']) ? wp_unslash($_POST['sp_survey_layout']) : '';
    $new_layout = json_decode($raw_layout, true);
    if (!is_array($new_layout) || $new_layout === []) {
        return new WP_Error(
            'sp_locked_layout',
            'This survey has responses; invalid layout submitted.'
        );
    }

    $new_sig = sp_layout_structure_signature_from_blocks($new_layout);

    $old_json   = isset($survey_row['survey_layout']) ? (string) $survey_row['survey_layout'] : '';
    $old_layout = json_decode($old_json, true);

    $n = count($db_questions);
    if (!is_array($old_layout) || $old_layout === []) {
        $old_sig = $n > 0 ? array_fill(0, $n, 'q') : [];
    } else {
        $old_sig = sp_layout_structure_signature_from_blocks($old_layout);
        if ($old_sig === [] && $n > 0) {
            $old_sig = array_fill(0, $n, 'q');
        }
    }

    if ($new_sig !== $old_sig) {
        return new WP_Error(
            'sp_locked_structure',
            'This survey has responses; page breaks and question order cannot be changed.'
        );
    }

    return true;
}

/**
 * Validate and sanitize survey_layout JSON from the editor. Returns JSON string or WP_Error.
 *
 * @param mixed $raw_json
 * @param mixed $questions_post
 * @param mixed $page_headers_post
 * @return string|WP_Error
 */
function sp_process_survey_layout_from_post($raw_json, $questions_post, $page_headers_post) {
    $posted = sp_normalize_questions_post_array($questions_post);

    foreach ($posted as $q) {
        $t = isset($q['text']) ? trim(wp_unslash($q['text'])) : '';
        if ($t === '') {
            return new WP_Error('sp_empty_q', 'Question text cannot be empty.');
        }
    }

    $layout = null;
    if ($raw_json !== null && $raw_json !== '') {
        $layout = json_decode(wp_unslash($raw_json), true);
    }
    if (!is_array($layout) || empty($layout)) {
        $layout = sp_infer_survey_layout_array_from_post($posted, is_array($page_headers_post) ? $page_headers_post : null);
    }

    if (is_array($layout) && !empty($layout)) {
        if (($layout[0]['type'] ?? '') !== 'page_header' || (int) ($layout[0]['page'] ?? 0) !== 1) {
            $h1 = sp_infer_header_value_for_page(is_array($page_headers_post) ? $page_headers_post : [], 1);
            array_unshift(
                $layout,
                [
                    'type'   => 'page_header',
                    'page'   => 1,
                    'header' => $h1,
                ]
            );
        }
    }

    if (empty($layout)) {
        return new WP_Error('sp_empty_layout', 'Survey layout is empty.');
    }

    $q_expected = 0;
    foreach ($layout as $b) {
        if (($b['type'] ?? '') === 'question') {
            $q_expected++;
        }
    }
    if ($q_expected !== count($posted)) {
        return new WP_Error('sp_layout_mismatch', 'Survey layout does not match submitted questions.');
    }

    $sanitized = [];
    foreach ($layout as $block) {
        $type = $block['type'] ?? '';
        if (!in_array($type, ['question', 'text', 'page_break', 'page_header'], true)) {
            return new WP_Error('sp_bad_layout_type', 'Invalid block in survey layout.');
        }
        if ($type === 'text') {
            $content = isset($block['content']) ? trim((string) wp_unslash($block['content'])) : '';
            if ($content === '') {
                return new WP_Error('sp_empty_text', 'Text block content cannot be empty.');
            }
            $sanitized[] = [
                'type'    => 'text',
                'content' => sanitize_textarea_field($content),
            ];
        } elseif ($type === 'page_header') {
            if ((int) ($block['page'] ?? 0) !== 1) {
                return new WP_Error('sp_bad_page_header', 'Invalid page header block.');
            }
            $sanitized[] = [
                'type'   => 'page_header',
                'page'   => 1,
                'header' => sanitize_text_field($block['header'] ?? ''),
            ];
        } elseif ($type === 'page_break') {
            $sanitized[] = [
                'type'   => 'page_break',
                'header' => sanitize_text_field($block['header'] ?? ''),
            ];
        } else {
            $sanitized[] = ['type' => 'question'];
        }
    }

    return wp_json_encode(array_values($sanitized));
}

/**
 * Layout blocks for the create/edit survey template.
 *
 * @param array<int, array<string, mixed>> $questions
 * @param string|null                      $survey_layout_json
 * @return array<int, array<string, mixed>>
 */
function sp_admin_survey_layout_blocks_for_display($questions, $survey_layout_json) {
    $blocks = [];
    if (!empty($survey_layout_json)) {
        $decoded = json_decode($survey_layout_json, true);
        if (is_array($decoded) && !empty($decoded)) {
            $blocks = $decoded;
        }
    }
    if (empty($blocks)) {
        $blocks = [
            [
                'type'   => 'page_header',
                'page'   => 1,
                'header' => '',
            ],
        ];
        foreach ($questions as $_unused) {
            $blocks[] = ['type' => 'question'];
        }
    }
    $blocks = sp_layout_prepend_page1_header_block_if_missing($blocks, []);
    return sp_enrich_layout_page_break_headers_from_legacy($blocks, []);
}
