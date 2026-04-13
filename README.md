# SurveyPilot

SurveyPilot is a WordPress plugin for **building, publishing, and managing Likert scale surveys**. Optional **email notifications** and **PDF reports** provide clear summaries of participant responses and comparisons across respondents.

## How It Works

- **Survey Builder** — Create and edit surveys with a **title, description, instructions, and ordered questions**. Each question uses a **numeric scale** (configurable min/max) with optional **per-point labels**.
- **Layout & Paging** — Arrange content into pages with **optional page headers**. Mix **text blocks** and **questions** in a visual layout.
- **Publishing** — Embed a survey into any WordPress page or post with a **shortcode**; participants step through **start → survey pages → submission**.
- **Responses** — Submissions are tied to the **logged-in WordPress user**. Answers are stored for **reporting and exporting**.
- **Edit Protection** — After responses exist for a survey, its structure is **partially locked** to protect data integrity.
- **Optional Email & PDF** — Per survey, you can send a **custom email message** after submission and attach a **PDF report**. An optional **uploadable logo** can appear on the PDF.
- **CSV Export** — Download all responses for a survey in **CSV format** from the dashboard.
- **Email Delivery Settings** — Configure **WordPress Mail** or **SMTP** and send a **test email** from SurveyPilot’s email settings screen to ensure email delivery is set up correctly.

## Installation

1. Copy the `plugin/survey-pilot` folder into `wp-content/plugins/`.
2. In **Plugins → Installed Plugins**, activate **SurveyPilot**.
3. On activation, the plugin creates the required database tables (`dbDelta`).

**Uninstall:** Deleting the plugin via **Plugins → Delete** runs `uninstall.php`, which removes SurveyPilot’s database tables, plugin options, temporarily generated PDF files, and uploaded PDF logos.

## Local Development (Docker)

This repository includes a **`docker-compose.yml`** that:

- Runs **WordPress** (on port `8000`) with the plugin mounted from `./plugin/survey-pilot`.
- Runs **MySQL** with a named volume for data.
- Runs **phpMyAdmin** (on port `8080`).

A **`wp/`** directory is expected for the WordPress core files volume (see compose file). Adjust ports and credentials for your machine. Do not use default passwords in production.

## License

SurveyPilot is licensed under the **GNU General Public License v2.0 or later (GPLv2+)**. See the **`LICENSE`** file in the repository root for the full GPLv2 text and a short copyright notice.

## Authors

SurveyPilot: **Jack McKee**, **Landon Williams**, **Terry Lu**