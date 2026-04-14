<?php

if (!defined('ABSPATH')) {
    exit;
}

// Email settings fields saved in wp_options table
add_action('admin_init', function () {
    register_setting('sp_email_settings', 'sp_email_mode');
    register_setting('sp_email_settings', 'sp_smtp_host');
    register_setting('sp_email_settings', 'sp_smtp_port');
    register_setting('sp_email_settings', 'sp_smtp_user');
    register_setting('sp_email_settings', 'sp_smtp_pass', [
        'sanitize_callback' => 'sanitize_text_field',
    ]);
});

// Encrypt SMTP password before it gets stored in the database
add_filter('pre_update_option_sp_smtp_pass', function ($new_value, $old_value) {
    if (empty($new_value)) {
        return $old_value;
    }
    $key = AUTH_KEY;
    $iv  = substr(hash('sha256', AUTH_SALT), 0, 16);
    return openssl_encrypt($new_value, 'AES-256-CBC', $key, 0, $iv);
}, 10, 2);

// Add Email Settings submenu under SurveyPilot
add_action('admin_menu', function () {
    add_submenu_page(
        'survey-pilot-dashboard',
        'Email Settings',
        'Email Settings',
        'manage_options',
        'survey-pilot-email-settings',
        'sp_render_email_settings'
    );
});

// Render Email Settings admin page
function sp_render_email_settings() {
    ?>
    <div class="wrap sp-dashboard sp-admin-page sp-email-page">
        <div class="sp-dashboard-header">
            <h1>SurveyPilot Email Settings</h1>
        </div>

        <hr>

        <div class="sp-dashboard-content">
            <!-- Settings Forms -->
            <div class="sp-dashboard-left">
                <h2>Email Configuration</h2>

                <?php $is_smtp = get_option('sp_email_mode') === 'smtp'; ?>
                <form method="post" action="options.php" id="sp-email-config-form">
                    <?php settings_fields('sp_email_settings'); ?>

                    <table class="form-table">
                        <tr>
                            <th><label for="sp_email_mode">Email Mode</label></th>
                            <td>
                                <select name="sp_email_mode" id="sp_email_mode">
                                    <option value="default" <?php selected(get_option('sp_email_mode'), 'default'); ?>>Default (wp_mail)</option>
                                    <option value="smtp" <?php selected(get_option('sp_email_mode'), 'smtp'); ?>>SMTP</option>
                                </select>
                            </td>
                        </tr>

                        <tr class="sp-smtp-row"<?php if (!$is_smtp) echo ' style="display:none;"'; ?>>
                            <th><label for="sp_smtp_host">SMTP Host<span class="sp-required" aria-hidden="true">*</span></label></th>
                            <td>
                                <input type="text" name="sp_smtp_host" id="sp_smtp_host" class="regular-text" maxlength="255" data-sp-maxlength="255" value="<?php echo esc_attr(get_option('sp_smtp_host', 'smtp.gmail.com')); ?>">
                                <p id="sp-smtp-host-error" class="sp-field-error" style="display:none;">SMTP Host is required.</p>
                            </td>
                        </tr>

                        <tr class="sp-smtp-row"<?php if (!$is_smtp) echo ' style="display:none;"'; ?>>
                            <th><label for="sp_smtp_port">SMTP Port<span class="sp-required" aria-hidden="true">*</span></label></th>
                            <td>
                                <input type="number" name="sp_smtp_port" id="sp_smtp_port" class="small-text" data-sp-maxlength="5" value="<?php echo esc_attr(get_option('sp_smtp_port', 587)); ?>">
                                <p id="sp-smtp-port-error" class="sp-field-error" style="display:none;">SMTP Port is required.</p>
                            </td>
                        </tr>

                        <tr class="sp-smtp-row"<?php if (!$is_smtp) echo ' style="display:none;"'; ?>>
                            <th><label for="sp_smtp_user">Username<span class="sp-required" aria-hidden="true">*</span></label></th>
                            <td>
                                <input type="text" name="sp_smtp_user" id="sp_smtp_user" class="regular-text" maxlength="254" data-sp-maxlength="254" value="<?php echo esc_attr(get_option('sp_smtp_user')); ?>">
                                <p id="sp-smtp-user-error" class="sp-field-error" style="display:none;">Username is required.</p>
                            </td>
                        </tr>

                        <tr class="sp-smtp-row"<?php if (!$is_smtp) echo ' style="display:none;"'; ?>>
                            <th><label for="sp_smtp_pass">Password<span class="sp-required" aria-hidden="true">*</span></label></th>
                            <td>
                                <?php
                                $stored_pass    = get_option('sp_smtp_pass', '');
                                $decrypted_pass = '';
                                if ($stored_pass) {
                                    $key = AUTH_KEY;
                                    $iv  = substr(hash('sha256', AUTH_SALT), 0, 16);
                                    $decrypted_pass = openssl_decrypt($stored_pass, 'AES-256-CBC', $key, 0, $iv);
                                }
                                ?>
                                <input type="password" name="sp_smtp_pass" id="sp_smtp_pass" class="regular-text" maxlength="255" data-sp-maxlength="255" value="<?php echo esc_attr($decrypted_pass); ?>">
                                <p id="sp-smtp-pass-error" class="sp-field-error" style="display:none;">Password is required.</p>
                            </td>
                        </tr>

                    </table>

                    <?php if (!empty($_GET['settings-updated'])) : ?>
                        <p class="sp-test-result-box sp-test-result-box-success">Settings saved successfully.</p>
                    <?php endif; ?>

                    <?php submit_button('Save Settings', 'primary sp-btn-large', 'submit', false, ['id' => 'sp-save-settings-btn']); ?>
                </form>

                <hr>

                <h2>Send Test Email</h2>
                <div id="sp-test-email-form">
                    <table class="form-table">
                        <tr>
                            <th class="sp-th-middle"><label for="sp_test_email_to">Recipient<span class="sp-required" aria-hidden="true">*</span></label></th>
                            <td>
                                <input type="text" name="sp_test_email_to" id="sp_test_email_to" class="regular-text" maxlength="254" data-sp-maxlength="254" placeholder="Enter email address...">
                                <p id="sp-test-email-error" class="sp-field-error" style="display:none;">Please enter a valid email address.</p>
                            </td>
                        </tr>
                    </table>
                    <p id="sp-test-result-box" class="sp-test-result-box" style="display:none;"></p>
                    <button type="button" class="button button-secondary sp-btn-large" id="sp-send-test-email-btn">Send Test Email</button>
                </div>
            </div>

            <!-- Email Settings Guide -->
            <div class="sp-dashboard-right">
                <h2>Email Settings Guide</h2>

                <p>SurveyPilot can send an email to a respondent after they submit a survey. Email delivery is handled in one of two ways:</p>

                <ol class="sp-email-guide-list">
                    <li><strong>Default (wp_mail)</strong> uses WordPress's built-in mail function. This method relies on your server's configuration and will only work if your hosting environment supports PHP's <code>mail()</code> function.</li>
                    <li><strong>SMTP</strong> uses an external mail server to send emails. This method requires additional setup but is generally more reliable than the default option.</li>
                </ol>

                <hr>

                <p>To use SMTP with <strong>Google (Gmail)</strong>, you'll need to generate an App Password:</p>

                <ol class="sp-email-guide-list">
                    <li>Ensure you have a <a href="https://mail.google.com/mail" target="_blank" rel="noopener">Gmail</a> account.</li>
                    <li>Enable <a href="https://myaccount.google.com/security" target="_blank" rel="noopener">Two-Step Verification</a> on your Google Account.</li>
                    <li>Generate a 16-character <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">App Password</a> (App Name can be anything). When copying the password, remove any spaces.</li>
                    <li>Enter the following values into SurveyPilot:
                        <ul class="sp-email-guide-values">
                            <li><strong>SMTP Host:</strong> <code>smtp.gmail.com</code></li>
                            <li><strong>SMTP Port:</strong> <code>587</code></li>
                            <li><strong>Username:</strong> <code>Your Gmail Address</code></li>
                            <li><strong>Password:</strong> <code>Your Generated Google App Password</code></li>
                        </ul>
                    </li>
                </ol>

                <hr>

                <p>Save your settings and use the <strong>Send Test Email</strong> feature to confirm everything is working correctly. Once configured, each survey participant will receive an email shortly after submitting their response (if email messaging is enabled for that survey).</p>
            </div>
        </div>
    </div>
    <?php
}

// Configure PHPMailer for when SMTP mode is enabled
add_action('phpmailer_init', function ($phpmailer) {
    // If using wp_mail (default mode), leave PHPMailer untouched
    if (get_option('sp_email_mode') !== 'smtp') {
        return;
    }

    // Apply SMTP credentials from email settings fields
    $phpmailer->isSMTP();
    $phpmailer->Host = get_option('sp_smtp_host');
    $phpmailer->Port = get_option('sp_smtp_port', 587);
    $phpmailer->SMTPAuth = true;
    $phpmailer->Username = get_option('sp_smtp_user');
    $encrypted = get_option('sp_smtp_pass');
    $key = AUTH_KEY;
    $iv  = substr(hash('sha256', AUTH_SALT), 0, 16);
    $phpmailer->Password = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    $phpmailer->SMTPSecure = 'tls';

    $phpmailer->From     = get_option('admin_email');
    $phpmailer->FromName = get_bloginfo('name');
});

add_filter('wp_mail_from', function () {
    return get_option('admin_email');
});

add_filter('wp_mail_from_name', function () {
    return get_bloginfo('name');
});

// Send a test email from the Email Settings screen
add_action('wp_ajax_sp_send_test_email', function () {
    check_ajax_referer('sp_send_test_email', 'nonce');

    $to = sanitize_email($_POST['email'] ?? '');

    if (!is_email($to)) {
        wp_send_json_error(['message' => 'Invalid email address.']);
    }

    $sent = wp_mail(
        $to,
        'SurveyPilot Test Email',
        '<p>This is a test email from SurveyPilot. Your email settings are configured correctly!</p>',
        ['Content-Type: text/html']
    );

    if ($sent) {
        wp_send_json_success(['message' => 'Email sent successfully.']);
    } else {
        wp_send_json_error(['message' => 'Email failed to send. Check your settings and try again.']);
    }
});
