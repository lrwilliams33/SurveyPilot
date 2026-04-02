<?php
if (!defined('ABSPATH')) exit;

//This file ensures that if user selects SMTP, the wp_mail function will create a PHPMailer instance with SMTP settings instead of PHP mail()

//basic flow is wp_mail called, filters are applied, then phpmailer_init is triggered, then email is sent

// Register settings
add_action('admin_init', function () {
  //Upon activation, create a settings group called sp_email_settings
  // register the following settings: sp_email_mode, sp_smtp_host, sp_smtp_port, sp_smtp_user, sp_smtp_pass
  //These values are stored in the wp_options table and can be retrieved using get_option('option_name')
    register_setting('sp_email_settings', 'sp_email_mode');
    register_setting('sp_email_settings', 'sp_smtp_host');
    register_setting('sp_email_settings', 'sp_smtp_port');
    register_setting('sp_email_settings', 'sp_smtp_user');
    register_setting('sp_email_settings', 'sp_smtp_pass', [
    'sanitize_callback' => function ($value) {
        if (empty($value)) {
            return get_option('sp_smtp_pass'); 
        }
        $key = AUTH_KEY; 
        $iv = substr(hash('sha256', AUTH_SALT), 0, 16);

        return openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
    }
]);
});

//This creates a menu item dropdown under Settings called Email Settings
add_action('admin_menu', function () {
    add_submenu_page(
        'survey-pilot',
        'Email Settings',
        'Email Settings',
        'manage_options',
        'sp-email-settings',
        //This is the callback function that will render the email settings page when the menu item is clicked
        'sp_render_email_settings'
    );
});


//This function will render the email settings page
//Called by the add_options_page function above
//options.php will save settings to wp_options table upon form submission
function sp_render_email_settings() {
    ?>
    <div class="wrap">
        <h1>SurveyPilot Email Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('sp_email_settings'); ?>

            <table class="form-table">
                <tr>
                    <th>Email Mode</th>
                    <td>
                        <select name="sp_email_mode">
                            <option value="default" <?php selected(get_option('sp_email_mode'), 'default'); ?>>
                                Default (wp_mail)
                            </option>
                            <option value="smtp" <?php selected(get_option('sp_email_mode'), 'smtp'); ?>>
                                SMTP
                            </option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>SMTP Host</th>
                    <td><input type="text" name="sp_smtp_host" value="<?php echo esc_attr(get_option('sp_smtp_host')); ?>"></td>
                </tr>

                <tr>
                    <th>SMTP Port</th>
                    <td><input type="number" name="sp_smtp_port" value="<?php echo esc_attr(get_option('sp_smtp_port', 587)); ?>"></td>
                </tr>

                <tr>
                    <th>Username</th>
                    <td><input type="text" name="sp_smtp_user" value="<?php echo esc_attr(get_option('sp_smtp_user')); ?>"></td>
                </tr>

                <tr>
                    <th>Password</th>
                    <td><input type="password" name="sp_smtp_pass" value=""></td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <hr>

        <h2>Send Test Email</h2>
        <form method="post">
            <?php wp_nonce_field('sp_test_email'); ?>
            <input type="email" name="sp_test_email_to" placeholder="Enter email" required>
            <?php submit_button('Send Test Email', 'secondary', 'sp_send_test_email'); ?>
        </form>
    </div>
    <?php
}

//handles sending emails by configuring PHPMailer to use SMTP settings if the email mode is set to SMTP in the options page
add_action('phpmailer_init', function ($phpmailer) {
    // Only configure PHPMailer for SMTP mode, if not in SMTP mode, wp_mail will use the default PHP mail() function by returning early from this function and not modifying the PHPMailer instance
    if (get_option('sp_email_mode') !== 'smtp') {
        return;
    }
    //everytime wp_mail is called either by test email or other emails and the mode is SMTP, this function will run and set PHPMailer to use SMTP with the settings from the options page
    $phpmailer->isSMTP();
    //get settings options from the database and set PHPMailer properties accordingly
    $phpmailer->Host = get_option('sp_smtp_host');
    $phpmailer->Port = get_option('sp_smtp_port', 587);
    $phpmailer->SMTPAuth = true;
    $phpmailer->Username = get_option('sp_smtp_user');
    $encrypted = get_option('sp_smtp_pass');
    $key = AUTH_KEY;
    $iv = substr(hash('sha256', AUTH_SALT), 0, 16);
    $phpmailer->Password = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    $phpmailer->SMTPSecure = 'tls';

    $phpmailer->From = get_option('admin_email');
    $phpmailer->FromName = get_bloginfo('name');
});


//These filters set the default From email and name for all emails sent by wp_mail, but can be overridden by PHPMailer settings if SMTP mode is enabled. This ensures that even in default mode, emails are sent with a consistent From address and name.
add_filter('wp_mail_from', function () {
    return get_option('admin_email');
});

add_filter('wp_mail_from_name', function () {
    return get_bloginfo('name');
});


//This function handles test email sending 
add_action('admin_init', function () {

    if (!isset($_POST['sp_send_test_email'])) return;

    if (!wp_verify_nonce($_POST['_wpnonce'], 'sp_test_email')) return;

    $to = sanitize_email($_POST['sp_test_email_to']);

    if (!is_email($to)) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>Invalid email</p></div>';
        });
        return;
    }

    $sent = wp_mail(
        $to,
        'SurveyPilot Test Email',
        '<p>This is a test email from SurveyPilot.</p>',
        ['Content-Type: text/html']
    );

    add_action('admin_notices', function () use ($sent) {
        echo $sent
            ? '<div class="notice notice-success"><p>Email sent!</p></div>'
            : '<div class="notice notice-error"><p>Email failed.</p></div>';
    });
});