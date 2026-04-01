<?php

use Dompdf\Dompdf;
use Dompdf\Options;

//generate PDF Report and perform score aggregation and percentile calculations for the report
function sp_generate_survey_pdf($survey_title, $response_id, $results, $population_means, $individual_results) {

    if (!class_exists('Dompdf\Dompdf')) {
        return new WP_Error('sp_no_dompdf', 'Dompdf is not available.');
    }

    $options = new Options();
    $options->set('isRemoteEnabled', false);

    $dompdf = new Dompdf($options);

    //group by category/page number
    $categories = [];

    foreach ($results as $row) {
        $page = $row->page_number;
        $value = (int) $row->answer_value;

        if (!isset($categories[$page])) {
            $categories[$page] = [
                'questions' => [],
                'total' => 0,
                'count' => 0
            ];
        }

        $categories[$page]['questions'][] = $row;
        $categories[$page]['total'] += $value;
        $categories[$page]['count']++;
    }

    foreach ($categories as $page => $data) {
        $categories[$page]['composite'] =
            $data['count'] > 0 ? ($data['total'] / $data['count']) : 0;
    }

    $category_stats = [];

    foreach ($categories as $page => $data) {

        $page_results = $individual_results[$page] ?? [];

        $below = 0;

        foreach ($page_results as $any_response_id => $user_composite) {
            if ($any_response_id == $response_id) continue;

            if ($user_composite < $data['composite']) {
                $below++;
            } elseif (abs($user_composite - $data['composite']) < 0.0001) {
                $below += 0.5;
            }
        }

        $total = max(count($page_results) - 1, 0);

        $percentile = $total > 0
            ? ($below / $total) * 100
            : null;

        $category_stats[$page] = [
            'composite' => $data['composite'],
            'mean' => $population_means[$page] ?? 0,
            'percentile' => $percentile
        ];
    }

    //create html for the pdf 
    $html = '
    <html>
    <head>
        <style>
            body {
                font-family: Helvetica, Arial, sans-serif;
                font-size: 12px;
                color: #333;
            }

            h1 {
                font-size: 22px;
                margin-bottom: 10px;
            }

            h2 {
                font-size: 16px;
                margin-top: 25px;
                margin-bottom: 10px;
                border-bottom: 2px solid #eee;
                padding-bottom: 5px;
            }

            h3 {
                font-size: 14px;
                margin-top: 15px;
            }

            .meta {
                margin-bottom: 15px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 10px;
            }

            th {
                background: #f5f5f5;
                font-weight: bold;
            }

            th, td {
                border: 1px solid #ddd;
                padding: 6px;
                text-align: left;
            }

            .divider {
                height: 1px;
                background: #ddd;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
    ';

    //PDF header
    $html .= '<h1>Survey Report</h1>';
    $html .= '<div class="meta">';
    $html .= '<strong>Survey:</strong> ' . esc_html($survey_title) . '<br>';
    $html .= '<strong>Response ID:</strong> ' . intval($response_id);
    $html .= '</div>';

    $html .= '<h2>Summary Statistics</h2>';
    $html .= '<table>';
    $html .= '<thead><tr>
                <th>Category</th>
                <th>Composite Score</th>
                <th>Population Mean</th>
                <th>Percentile</th>
              </tr></thead><tbody>';

    foreach ($category_stats as $page => $stat) {

        $html .= '<tr>';
        $html .= '<td>Category ' . intval($page) . '</td>';
        $html .= '<td>' . number_format($stat['composite'], 2) . '</td>';
        $html .= '<td>' . number_format($stat['mean'], 2) . '</td>';
        $html .= '<td>' . ($stat['percentile'] !== null 
            ? number_format($stat['percentile'], 2) . '%' 
            : 'N/A') . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    //iterate through categories and display question/answer pairs 
    $html .= '<h2>Detailed Responses</h2>';

    foreach ($categories as $page => $data) {

        $html .= '<h3>Category ' . intval($page) . '</h3>';
        $html .= '<table>';
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

    $file_path = trailingslashit($pdf_dir) . 'survey-report-' . intval($response_id) . '.pdf';

    $written = file_put_contents($file_path, $dompdf->output());

    if ($written === false) {
        return new WP_Error('sp_pdf_write_failed', 'Failed to create PDF file.');
    }

    return $file_path;
}