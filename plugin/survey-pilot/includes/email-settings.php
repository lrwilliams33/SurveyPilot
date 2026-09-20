<?php

if (!defined('ABSPATH')) {
    exit;
}

// Email settings fields saved in wp_options table
add_action('admin_init', function () {
    register_setting('sp_email_settings', 'sp_email_mode', [
        'sanitize_callback' => 'sp_sanitize_email_mode',
    ]);
    register_setting('sp_email_settings', 'sp_smtp_host', [
        'sanitize_callback' => 'sp_sanitize_smtp_host',
    ]);
    register_setting('sp_email_settings', 'sp_smtp_port', [
        'sanitize_callback' => 'sp_sanitize_smtp_port',
    ]);
    register_setting('sp_email_settings', 'sp_smtp_user', [
        'sanitize_callback' => 'sp_sanitize_smtp_user',
    ]);
    register_setting('sp_email_settings', 'sp_smtp_pass', [
        'sanitize_callback' => 'sp_sanitize_smtp_pass',
    ]);
});

// Only the two supported email modes are accepted
function sp_sanitize_email_mode($value) {
    $value = is_string($value) ? sanitize_key($value) : '';
    return in_array($value, ['default', 'smtp'], true) ? $value : 'default';
}

// SMTP host must be a hostname or IP address; anything else keeps the previously saved host
function sp_sanitize_smtp_host($value) {
    $value = is_string($value) ? trim(sanitize_text_field($value)) : '';
    $is_hostname = strlen($value) <= 255
        && preg_match('/^[A-Za-z0-9]([A-Za-z0-9.\-]*[A-Za-z0-9])?$/', $value);

    if ($value === '' || $is_hostname || filter_var(trim($value, '[]'), FILTER_VALIDATE_IP)) {
        return $value;
    }

    add_settings_error('sp_email_settings', 'sp_smtp_host_invalid', 'SMTP Host is not valid, so the previous host was kept. Use a host name such as smtp.example.com.');
    return get_option('sp_smtp_host', '');
}

// SMTP port must be a whole number from 1 to 65535; anything else keeps the previously saved port
function sp_sanitize_smtp_port($value) {
    $port = is_scalar($value) ? absint($value) : 0;

    if ($port >= 1 && $port <= 65535) {
        return $port;
    }

    if ($value !== '' && $value !== null) {
        add_settings_error('sp_email_settings', 'sp_smtp_port_invalid', 'SMTP Port must be a number from 1 to 65535, so the previous port was kept.');
    }
    return (int) get_option('sp_smtp_port', 587);
}

function sp_sanitize_smtp_user($value) {
    return is_string($value) ? trim(sanitize_text_field($value)) : '';
}

// Passwords are stored as typed. sanitize_text_field() would silently strip characters such as "<" or
// "%ab" from a valid password, so only characters that can never be part of a password are removed.
function sp_sanitize_smtp_pass($value) {
    return is_string($value) ? str_replace(["\r", "\n", "\0"], '', $value) : '';
}

// Encrypt SMTP password before it gets stored in the database
add_filter('pre_update_option_sp_smtp_pass', function ($new_value, $old_value) {
    if (empty($new_value)) {
        return $old_value;
    }
    return sp_encrypt_smtp_password($new_value);
}, 10, 2);

// Encryption keys are derived from the site's own WordPress salts (wp-config.php or the database)
function sp_smtp_crypto_keys() {
    $salt = wp_salt('auth');
    return [
        'enc' => hash_hmac('sha256', 'sp-smtp-enc', $salt, true),
        'mac' => hash_hmac('sha256', 'sp-smtp-mac', $salt, true),
    ];
}

// Encrypt with a random IV per value and an HMAC so tampering is detected: "sp2:" + base64(iv . mac . ciphertext)
function sp_encrypt_smtp_password($plain) {
    $keys = sp_smtp_crypto_keys();
    $iv = random_bytes(16);
    $cipher = openssl_encrypt((string) $plain, 'AES-256-CBC', $keys['enc'], OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        return '';
    }
    $mac = hash_hmac('sha256', $iv . $cipher, $keys['mac'], true);
    return 'sp2:' . base64_encode($iv . $mac . $cipher);
}

// Decrypt a stored SMTP password. Also reads the older format (fixed IV, no HMAC) so existing saved passwords keep working.
function sp_decrypt_smtp_password($stored) {
    if (!is_string($stored) || $stored === '') {
        return '';
    }

    if (strpos($stored, 'sp2:') === 0) {
        $raw = base64_decode(substr($stored, 4), true);
        if ($raw === false || strlen($raw) <= 48) {
            return '';
        }
        $iv     = substr($raw, 0, 16);
        $mac    = substr($raw, 16, 32);
        $cipher = substr($raw, 48);
        $keys   = sp_smtp_crypto_keys();
        if (!hash_equals(hash_hmac('sha256', $iv . $cipher, $keys['mac'], true), $mac)) {
            return '';
        }
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $keys['enc'], OPENSSL_RAW_DATA, $iv);
        return $plain === false ? '' : $plain;
    }

    if (defined('AUTH_KEY') && defined('AUTH_SALT')) {
        $plain = openssl_decrypt($stored, 'AES-256-CBC', AUTH_KEY, 0, substr(hash('sha256', AUTH_SALT), 0, 16));
        return $plain === false ? '' : $plain;
    }

    return '';
}

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

                <?php
                $is_smtp = get_option('sp_email_mode') === 'smtp';
                $sp_settings_errors = get_settings_errors('sp_email_settings');
                ?>
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
                                <?php $has_saved_pass = get_option('sp_smtp_pass', '') !== ''; ?>
                                <?php // The saved password is never printed into the page; leaving this blank keeps it ?>
                                <input type="password" name="sp_smtp_pass" id="sp_smtp_pass" class="regular-text" maxlength="255" data-sp-maxlength="255" value="" autocomplete="new-password"<?php if ($has_saved_pass) echo ' data-sp-has-saved="1" placeholder="Saved. Leave blank to keep current password."'; ?>>
                                <p id="sp-smtp-pass-error" class="sp-field-error" style="display:none;">Password is required.</p>
                            </td>
                        </tr>

                    </table>

                    <?php if (!empty($_GET['settings-updated'])) : ?>
                        <?php if (empty($sp_settings_errors)) : ?>
                            <p class="sp-test-result-box sp-test-result-box-success">Settings saved successfully.</p>
                        <?php else : ?>
                            <?php foreach ($sp_settings_errors as $sp_settings_error) : ?>
                                <p class="sp-test-result-box sp-test-result-box-error"><?php echo esc_html($sp_settings_error['message']); ?></p>
                            <?php endforeach; ?>
                        <?php endif; ?>
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

// True only while SurveyPilot itself is sending an email. The mail hooks below check this so that
// SurveyPilot's SMTP settings and From address never affect emails sent by WordPress or other plugins.
function sp_mail_scope($active = null) {
    static $is_active = false;
    if ($active !== null) {
        $is_active = (bool) $active;
    }
    return $is_active;
}

// Send an email through wp_mail() with SurveyPilot's email settings applied to this message only
function sp_send_mail($to, $subject, $message, $headers = '', $attachments = []) {
    sp_mail_scope(true);
    try {
        return wp_mail($to, $subject, $message, $headers, $attachments);
    } finally {
        sp_mail_scope(false);

        // WordPress reuses a single PHPMailer object for every wp_mail() call in a request, so drop it
        // after an SMTP send. Otherwise later emails from other plugins would inherit our SMTP server.
        if (get_option('sp_email_mode') === 'smtp') {
            global $phpmailer;
            $phpmailer = null;
        }
    }
}

// Log failures of SurveyPilot's own emails only (not other plugins' emails)
add_action('wp_mail_failed', function ($wp_error) {
    if (!sp_mail_scope()) {
        return;
    }
    error_log('SP: wp_mail_failed fired');
    error_log('SP: error message=' . $wp_error->get_error_message());
});

// Configure PHPMailer for when SMTP mode is enabled
add_action('phpmailer_init', function ($phpmailer) {
    // Leave PHPMailer untouched for emails that SurveyPilot did not send
    if (!sp_mail_scope()) {
        return;
    }

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
    $phpmailer->Password = sp_decrypt_smtp_password(get_option('sp_smtp_pass'));
    // Port 465 uses implicit SSL; other ports (such as 587) use STARTTLS
    $phpmailer->SMTPSecure = ((int) $phpmailer->Port === 465) ? 'ssl' : 'tls';

    $phpmailer->From     = get_option('admin_email');
    $phpmailer->FromName = 'IBSTPI';
});

add_filter('wp_mail_from', function ($from) {
    return sp_mail_scope() ? get_option('admin_email') : $from;
});

add_filter('wp_mail_from_name', function ($from_name) {
    return sp_mail_scope() ? 'IBSTPI' : $from_name;
});

// Send a test email from the Email Settings screen
add_action('wp_ajax_sp_send_test_email', function () {
    check_ajax_referer('sp_send_test_email', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'You do not have permission to send test emails.'], 403);
    }

    $to = sanitize_email($_POST['email'] ?? '');

    if (!is_email($to)) {
        wp_send_json_error(['message' => 'Invalid email address.']);
    }

    $sent = sp_send_mail(
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
