<?php

use Dompdf\Dompdf;
use Dompdf\Options;

function sp_generate_submission_pdf($survey_title, $submission_id, $submission_data) {
    if (!class_exists('Dompdf\Dompdf')) {
        return new WP_Error('sp_no_dompdf', 'Dompdf is not available.');
    }

    $options = new Options();
    $options->set('isRemoteEnabled', false);

    $dompdf = new Dompdf($options);

    $html  = '<html><body>';
    $html .= '<h1>Survey Submission Report</h1>';
    $html .= '<p><strong>Survey:</strong> ' . esc_html($survey_title) . '</p>';
    $html .= '<p><strong>Submission ID:</strong> ' . intval($submission_id) . '</p>';
    $html .= '<table width="100%" border="1" cellspacing="0" cellpadding="6">';
    $html .= '<thead><tr><th align="left">Question</th><th align="left">Answer</th></tr></thead><tbody>';

    foreach ($submission_data as $row) {
        $question = isset($row['question']) ? $row['question'] : '';
        $answer   = isset($row['answer']) ? $row['answer'] : '';

        $html .= '<tr>';
        $html .= '<td>' . esc_html($question) . '</td>';
        $html .= '<td>' . nl2br(esc_html($answer)) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    $html .= '</body></html>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $upload_dir = wp_upload_dir();
    if (!empty($upload_dir['error'])) {
        return new WP_Error('sp_upload_dir_error', $upload_dir['error']);
    }

    $pdf_dir = trailingslashit($upload_dir['basedir']) . 'survey-pilot-pdfs';
    if (!file_exists($pdf_dir)) {
        wp_mkdir_p($pdf_dir);
    }

    $filename = 'survey-submission-' . intval($submission_id) . '-' . time() . '.pdf';
    $file_path = trailingslashit($pdf_dir) . $filename;

    $bytes_written = file_put_contents($file_path, $dompdf->output());

    if ($bytes_written === false) {
        return new WP_Error('sp_pdf_write_failed', 'Failed to write PDF file.');
    }

    return $file_path;
}