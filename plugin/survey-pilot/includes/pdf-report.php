<?php

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Scale a JPEG/PNG on disk to fit inside a bounding box (aspect ratio preserved, no upscaling).
 * Returns base64 body and mime for embedding in the PDF, or null on failure.
 *
 * @return array{data: string, mime: string, width: int, height: int}|null
 */
function sp_pdf_logo_to_bounded_base64($file_path, $max_width = 120, $max_height = 80) {
    $max_width  = max(1, (int) $max_width);
    $max_height = max(1, (int) $max_height);

    $detected = wp_get_image_mime($file_path);
    if ($detected !== 'image/jpeg' && $detected !== 'image/png') {
        return null;
    }

    $dims = @getimagesize($file_path);
    if ($dims === false || empty($dims[0]) || empty($dims[1])) {
        return null;
    }

    $orig_w = (int) $dims[0];
    $orig_h = (int) $dims[1];

    if ($orig_w <= $max_width && $orig_h <= $max_height) {
        $raw = file_get_contents($file_path);
        if ($raw === false) {
            return null;
        }

        return [
            'data'   => base64_encode($raw),
            'mime'   => $detected,
            'width'  => $orig_w,
            'height' => $orig_h,
        ];
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';

    $editor = wp_get_image_editor($file_path);
    if (is_wp_error($editor)) {
        return null;
    }

    $resized = $editor->resize($max_width, $max_height, false);
    if (is_wp_error($resized)) {
        return null;
    }

    $temp = wp_tempnam('sp-pdf-logo-');
    if (!$temp) {
        return null;
    }

    $saved = $editor->save($temp, $detected);

    if (is_wp_error($saved) || empty($saved['path']) || !is_readable($saved['path'])) {
        if (is_string($temp) && is_file($temp)) {
            @unlink($temp);
        }

        return null;
    }

    $raw = file_get_contents($saved['path']);
    if (is_file($saved['path'])) {
        @unlink($saved['path']);
    }

    if ($raw === false || $raw === '') {
        return null;
    }

    $out_w = isset($saved['width']) ? (int) $saved['width'] : $max_width;
    $out_h = isset($saved['height']) ? (int) $saved['height'] : $max_height;

    return [
        'data'   => base64_encode($raw),
        'mime'   => $detected,
        'width'  => $out_w,
        'height' => $out_h,
    ];
}

//generate PDF Report and perform score aggregation and percentile calculations for the report
function sp_generate_survey_pdf($survey_title, $response_id, $results, $sample_means, $individual_results, $pdf_logo_attachment_id = null) {

    if (!class_exists('Dompdf\Dompdf')) {
        return new WP_Error('sp_no_dompdf', 'Dompdf is not available.');
    }

    $options = new Options();
    $options->set('isRemoteEnabled', false);

    $dompdf = new Dompdf($options);

    $current_user = wp_get_current_user();
    $name = $current_user->display_name ? $current_user->display_name : 'Anonymous';

    //group by category/page number
    $categories = [];

    foreach ($results as $row) {
        $page = $row->page_number;
        $value = (int) $row->answer_value;
        $header = $row->page_header;
        $question_max = 0;
        
        $labels = json_decode($row->scale_labels, true);
        

        if (is_array($labels)) {
            $question_max = max(array_keys($labels));
        }

        if (!isset($categories[$page])) {
            $categories[$page] = [
                'questions' => [],
                'total' => 0,
                'count' => 0,
                'header' => $header,
                'max' => 0
            ];
        }

        $categories[$page]['questions'][] = $row;
        $categories[$page]['total'] += $value;
        $categories[$page]['count']++;
        $categories[$page]['max'] += $question_max ?? 0;
    }

    foreach ($categories as $page => $data) {
        $categories[$page]['composite'] =
            $data['count'] > 0 ? ($data['total'] / $data['count']) : 0;
        $categories[$page]['max'] = $data['max'] > 0 ? ($data['max'] / $data['count']) : 0;
    }

    $category_stats = [];

    foreach ($categories as $page => $data) {

        $page_results = $individual_results[$page] ?? [];

        $values = array_values($page_results);
        $mean = $sample_means[$page] ?? 0;
        $variance = 0;
        $count = count($values);

        if ($count > 1){
            foreach ($values as $val) {
                $variance += pow($val - $mean, 2);
            }
            $variance /= ($count - 1);
            $stddev = sqrt($variance);
        }
        else{
            $stddev = null;
        }

        $below = 0;

        foreach ($page_results as $any_response_id => $user_composite) {

            if ($user_composite < $data['composite']) {
                $below++;
            }
        }

        $total = max(count($page_results), 0);

        // Percentile defined as (# values below x / total values) * 100
        // Matches standard textbook definition
        $percentile = $total > 0
            ? ($below / $total) * 100
            : null;

        $category_stats[$page] = [
            'header' => $data['header'],
            'composite' => $data['composite'],
            'mean' => $sample_means[$page] ?? 0,
            'stddev' => $stddev,
            'percentile' => $percentile
        ];
    }

    $has_logo    = false;
    $logo_data   = '';
    $logo_mime   = '';
    $logo_width  = 120;
    $logo_height = 80;
    $attachment_id = ($pdf_logo_attachment_id !== null && (int) $pdf_logo_attachment_id > 0)
        ? (int) $pdf_logo_attachment_id
        : 0;

    if ($attachment_id > 0) {
        $custom_path = get_attached_file($attachment_id);
        if ($custom_path && is_readable($custom_path) && is_file($custom_path)) {
            $bounded = sp_pdf_logo_to_bounded_base64($custom_path, 120, 80);
            if ($bounded !== null) {
                $has_logo    = true;
                $logo_data   = $bounded['data'];
                $logo_mime   = $bounded['mime'];
                $logo_width  = $bounded['width'];
                $logo_height = $bounded['height'];
            }
        }
    }

    $header_class = $has_logo ? 'header header--with-logo' : 'header';

    //create html for the pdf 
    $html = '
    <html>
    <head>
        <style>
            body {
                font-family: Helvetica, Arial, sans-serif;
                font-size: 12px;
                color: #222;
                background-color: #fff;
            }

            h1 {
                font-size: 22px;
                margin-bottom: 10px;
            }

            h2 {
                font-size: 16px;
                font-weight: bold;
                margin-top: 20px;
                margin-bottom: 10px;
                border-bottom: 2px solid #e5e5e5;
                padding-bottom: 5px;
            }

            h3 {
                font-size: 14px;
                margin-top: 15px;
            }

            .header {
                position: relative;
                margin-bottom: 25px;
                background-color: #fff;
                padding: 20px;
            }

            .logo {
                position: absolute;
                top: -10px;
                right: 0;
                max-width: 120px;
                max-height: 80px;
                width: auto;
                height: auto;
                object-fit: contain;
            }

            .survey-title {
                text-align: center;
                font-weight: bold;
                padding-right: 0;
                font-size: 24px;
                color: #222;
            }

            .header--with-logo .survey-title {
                padding-right: 140px;
            }

            .response-id {
                text-align: center;
                font-size: 12px;
                margin-top: -5px;
                padding-right: 0;
                color: #777;
            }

            .header--with-logo .response-id {
                padding-right: 140px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 10px;
            }

            th, td {
                padding: 6px;
                text-align: left;
            }

            .divider {
                height: 1px;
                background: #ddd;
                margin: 20px 0;
            }

            .stats-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
            }

            .stats-table th {
                background-color: #1f1f1f;
                color: #ffffff;
                padding: 8px;
                text-align: left;
            }

            .stats-table td {
                padding: 8px;
                border-bottom: 1px solid #e5e5e5;
            }

            .qa-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
            }

            .qa-table th {
                background-color: #f4f4f4;
                padding: 8px;
                text-align: left;
            }

            .qa-table td {
                padding: 8px;
                border-bottom: 1px solid #e5e5e5;
            }

            .stats-table tbody tr:nth-child(even),
            .qa-table tbody tr:nth-child(even) {
                background-color: #fafafa;
            }

            .overview-box {
                border: 1px solid #e5e5e5;
                padding: 12px;
                margin-bottom: 10px;
                border-radius: 4px;
                background-color: #fafafa;
            }

            .overview-box:last-child {
                margin-bottom: 15px;
            }

            .overview-title {
                font-weight: bold;
                margin-bottom: 4px;
            }

            .bar-container {
                margin-bottom: 12px;
            }

            .bar-label {
                font-size: 12px;
                margin-bottom: 3px;
            }

            .bar-wrapper {
                width: 100%;
            }

            .bar-track {
                width: 100%;
                background-color: #eee;
                height: 14px;
                border-radius: 4px;
                margin-bottom: 6px;
            }

            .bar-user {
                height: 100%;
                background-color: #4CAF50;
            }

            .bar-mean {
                height: 100%;
                background-color: #888;
            }
        </style>
    </head>
    <body>
    ';

    // PDF header (logo only if a custom image is configured and readable)
    $html .= '<div class="' . esc_attr($header_class) . '">';
    if ($has_logo) {
        $lw = (int) $logo_width;
        $lh = (int) $logo_height;
        $html .= '<img class="logo" src="data:' . $logo_mime . ';base64,' . $logo_data . '" width="' . $lw . '" height="' . $lh . '">';
    }
    $html .= '<h1 class="survey-title">' . esc_html($survey_title) . '</h1>';
    $html .= '</div>';

    $html .= '<h2>Overview</h2>';

    $html .= '<p>
    This report summarizes your performance on <strong>' . esc_html($survey_title) . '</strong>, taken by ' .  esc_html($name) . '.
    </p>';

    $html .= '<div class="overview-box">
    <div class="overview-title">Composite Score</div>
    <div>Your average score within each category.</div>
    </div>';

    $html .= '<div class="overview-box">
    <div class="overview-title">Sample Mean</div>
    <div>The average score across all respondents.</div>
    </div>';

    $html .= '<div class="overview-box">
    <div class="overview-title">Sample Standard Deviation</div>
    <div>Indicates how spread out responses are.</div>
    </div>';

    $html .= '<div class="overview-box">
    <div class="overview-title">Percentile</div>
    <div>Shows how your score compares to others.</div>
    </div>';


    $html .= '<h2>Summary Statistics</h2>';
    $html .= '<table class="stats-table">';
    $html .= '<thead><tr>
                <th>Category</th>
                <th>Composite Score</th>
                <th>Sample Mean</th>
                <th>Sample Standard Deviation</th>
                <th>Percentile</th>
              </tr></thead><tbody>';

    foreach ($category_stats as $page => $stat) {

        $html .= '<tr>';
        $html .= '<td>' . esc_html($categories[$page]['header'] ?? '') . '</td>';
        $html .= '<td>' . number_format($stat['composite'], 2) . '</td>';
        $html .= '<td>' . number_format($stat['mean'], 2) . '</td>';
        $html .= '<td>' . ($stat['stddev'] !== null 
                ? number_format($stat['stddev'], 2) 
                : 'N/A') . '</td>';
        $html .= '<td>' . ($stat['percentile'] !== null 
            ? number_format($stat['percentile'], 1) . '%' 
            : 'N/A') . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    $html .= '<h2>Category Performance</h2>';
    foreach ($category_stats as $page => $stat) {
        $user = $stat['composite'];
        $mean = $stat['mean'];


        $max = $categories[$page]['max'] ?? 5;

        $user_width = ($user / $max) * 100;
        $mean_width = ($mean / $max) * 100;

        $html .= '<div class="bar-container">';

        $html .= '<div class="bar-label">'
            . esc_html($stat['header'])
            . ' (You: ' . number_format($user, 2)
            . ' | Avg: ' . number_format($mean, 2)
            .' | Max Possible Score: ' .number_format($max, 2) . ')'
            . '</div>';

        $html .= '<div class="bar-wrapper">';

        //user bar
        $html .= '<div style="font-size:10px; margin-bottom:2px;">You</div>';
        $html .= '<div class="bar-track">';
        $html .= '<div class="bar-user" style="
            width:' . $user_width . '%;
            color:white;
            font-size:10px;
            text-align:right;
            padding-right:4px;
        ">';
        $html .= number_format($user, 1);
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div style="height:10px;"></div>';

        //average bar
        $html .= '<div style="font-size:10px; margin-bottom:2px;">Average</div>';
        $html .= '<div class="bar-track">';
         $html .= '<div class="bar-mean" style="
            width:' . $mean_width . '%;
            color:white;
            font-size:10px;
            text-align:right;
            padding-right:4px;
        ">';
        $html .= number_format($mean, 1);
        $html .= '</div>';


        $html .= '</div>';
        $html .= '</div>';
    }

    //iterate through categories and display question/answer pairs 
    $html .= '<h2>Detailed Responses</h2>';

    foreach ($categories as $page => $data) {

        $html .= '<h3>' . esc_html($data['header'] ?? '') . '</h3>';
        $html .= '<table class="qa-table">';
        $html .= '<thead><tr>
                    <th>Question</th>
                    <th>Answer</th>
                  </tr></thead><tbody>';

        foreach ($data['questions'] as $row) {

            $labels = json_decode($row->scale_labels, true);
            $answer_text = $row->answer_value;

            if (is_array($labels) && isset($labels[$row->answer_value])) {
                $answer_text = $labels[$row->answer_value];
            }

            $html .= '<tr>';
            $html .= '<td>' . esc_html($row->question_text) . '</td>';
            $html .= '<td>' . esc_html($answer_text) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        $html .= '<div class="divider"></div>';
    }

    $html .= '</body></html>';

    //generate pdf using dompdf
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    //send the generated PDF to the uploads directory and return the file path for email attachment
    $upload_dir = wp_upload_dir();

    if (!empty($upload_dir['error'])) {
        return new WP_Error('sp_upload_error', $upload_dir['error']);
    }

    $pdf_dir = trailingslashit($upload_dir['basedir']) . 'survey-pilot-pdfs';

    if (!file_exists($pdf_dir)) {
        wp_mkdir_p($pdf_dir);
    }

    $file_path = trailingslashit($pdf_dir) . 'Survey-Results-Report.pdf';

    $written = file_put_contents($file_path, $dompdf->output());

    if ($written === false) {
        return new WP_Error('sp_pdf_write_failed', 'Failed to create PDF file.');
    }

    return $file_path;
}