# SurveyPilot
Custom Wordpress Plugin for the Creation and Management of Surveys. Automatic scoring and email of results.

Email Setup Steps:

SurveyPilot sends an email to the user after they submit a survey. Email delivery is handled through the WP Mail SMTP plugin using Gmail SMTP.

Each developer must configure their own Gmail account to send emails during local development.

-----Step 1-----

Install and activate the WP Mail SMTP plugin in WordPress, should be the first option when you search up SMTP when adding plugins. 

In the WordPress admin dashboard, navigate to Plugins.
Click Add New Plugin.
Search for WP Mail SMTP.
Install the plugin and click Activate.

-----Step 2-----

Generate a Google App Password for Gmail, make sure you have a Gmail account.

Visit the following link after ensuring that Two Step Verification is enabled on your Google account.

https://myaccount.google.com/apppasswords

Follow the instructions to generate a sixteen character app password.

When the password is generated, copy it and remove any spaces.

-----Step 3-----

Configure WP Mail SMTP in WordPress.

Open the WP Mail SMTP settings page in the WordPress admin dashboard.

Choose the Other SMTP mailer option.

Enter the following configuration values:

SMTP Host
smtp.gmail.com

Encryption
TLS

SMTP Port
587

Authentication
Enabled

SMTP Username
Your Gmail address

SMTP Password
Paste the Google App Password generated in Step 2

From Name
SurveyPilot

From Email
Your Gmail address

Enable Force From Email if available.

Save the settings.

-----Step 4-----

Restart the Docker containers.

docker compose down
docker compose up -d

Restarting ensures the latest configuration is loaded.

-----Step 5-----

Test email delivery.

Open a survey page on the site.
Complete and submit the survey while logged in as a WordPress user.

SurveyPilot will automatically send a survey report to the email address associated with the logged in WordPress user account.

If the configuration is correct, the email should appear in your Gmail inbox shortly after submitting the survey.