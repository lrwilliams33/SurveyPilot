<?php
/**
 * SurveyPilot uninstall cleanup.
 *
 * Runs when the plugin is deleted from the Plugins screen (not on deactivate).
 * Per site: removes PDF logo attachments referenced in survey data, drops custom
 * tables, deletes plugin options, and removes the generated-PDF directory under uploads.
 */

 
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Remove a directory tree (used only for plugin-owned uploads/survey-pilot-pdfs).
 */
function sp_uninstall_remove_directory($dir_path) {
    if (!is_dir($dir_path)) {
        return;
    }

    $items = @scandir($dir_path);
    if (!is_array($items)) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir_path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            sp_uninstall_remove_directory($path);
            continue;
        }

        @unlink($path);
    }

    @rmdir($dir_path);
}

/**
 * True when $file_path is inside $directory_path (after normalization).
 */
function sp_uninstall_is_path_in_directory($file_path, $directory_path) {
    if (!is_string($file_path) || !is_string($directory_path) || $file_path === '' || $directory_path === '') {
        return false;
    }

    $normalized_dir = wp_normalize_path(untrailingslashit($directory_path));
    $real_dir       = realpath($normalized_dir);
    if ($real_dir !== false) {
        $normalized_dir = wp_normalize_path($real_dir);
    }

    $normalized_file = wp_normalize_path($file_path);
    $real_file       = realpath($normalized_file);
    if ($real_file !== false) {
        $normalized_file = wp_normalize_path($real_file);
    }

    if ($normalized_dir === '' || $normalized_file === '') {
        return false;
    }

    if (DIRECTORY_SEPARATOR === '\\') {
        $normalized_dir  = strtolower($normalized_dir);
        $normalized_file = strtolower($normalized_file);
    }

    return $normalized_file === $normalized_dir || strpos($normalized_file, $normalized_dir . '/') === 0;
}

/**
 * SurveyPilot data cleanup for the current blog.
 */
function sp_uninstall_cleanup_site_data() {
    global $wpdb;

    $survey_info_table             = $wpdb->prefix . 'survey_info';
    $survey_questions_table        = $wpdb->prefix . 'survey_questions';
    $survey_response_info_table    = $wpdb->prefix . 'survey_response_info';
    $survey_response_answers_table = $wpdb->prefix . 'survey_response_answers';

    $upload_dir = wp_upload_dir(null, false);
    $basedir    = '';
    if (empty($upload_dir['error']) && !empty($upload_dir['basedir'])) {
        $basedir = wp_normalize_path(untrailingslashit($upload_dir['basedir']));
    }

    $pdf_dir = '';
    if ($basedir !== '') {
        $pdf_dir = trailingslashit($upload_dir['basedir']) . 'survey-pilot-pdfs';
    }

    // PDF header logos: attachment IDs stored on survey_info (Media Library; usually under uploads/YYYY/MM).
    $has_survey_info = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $survey_info_table));
    if ($has_survey_info === $survey_info_table) {
        $attachment_ids = $wpdb->get_col(
            "SELECT DISTINCT pdf_report_logo_attachment_id
             FROM {$survey_info_table}
             WHERE pdf_report_logo_attachment_id IS NOT NULL
             AND pdf_report_logo_attachment_id > 0"
        );

        if (is_array($attachment_ids)) {
            foreach ($attachment_ids as $attachment_id) {
                $attachment_id = (int) $attachment_id;
                if ($attachment_id <= 0) {
                    continue;
                }

                $attached_file = get_attached_file($attachment_id);
                if (is_string($attached_file) && $attached_file !== '' && $basedir !== '') {
                    if (!sp_uninstall_is_path_in_directory($attached_file, $basedir)) {
                        continue;
                    }
                }

                wp_delete_attachment($attachment_id, true);
            }
        }
    }

    $wpdb->query("DROP TABLE IF EXISTS {$survey_response_answers_table}");
    $wpdb->query("DROP TABLE IF EXISTS {$survey_response_info_table}");
    $wpdb->query("DROP TABLE IF EXISTS {$survey_questions_table}");
    $wpdb->query("DROP TABLE IF EXISTS {$survey_info_table}");

    delete_option('sp_db_version');
    delete_option('sp_email_mode');
    delete_option('sp_smtp_host');
    delete_option('sp_smtp_port');
    delete_option('sp_smtp_user');
    delete_option('sp_smtp_pass');

    // Temp / generated PDFs (see includes/pdf-report.php).
    if ($pdf_dir !== '' && $basedir !== '' && sp_uninstall_is_path_in_directory($pdf_dir, $basedir)) {
        sp_uninstall_remove_directory($pdf_dir);
    }
}

if (function_exists('is_multisite') && is_multisite()) {
    $site_ids = get_sites([
        'fields' => 'ids',
        'number'   => 0,
    ]);

    if (is_array($site_ids)) {
        foreach ($site_ids as $site_id) {
            switch_to_blog((int) $site_id);
            sp_uninstall_cleanup_site_data();
            restore_current_blog();
        }
    }
} else {
    sp_uninstall_cleanup_site_data();
}
