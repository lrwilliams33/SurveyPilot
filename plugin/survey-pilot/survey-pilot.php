<?php
/*
Plugin Name: Survey Pilot
Description: Custom survey plugin
Version: 1.0
Author: Jack McKee, Landon Williams, Terry Lu
*/

add_action('admin_menu', function () {
    add_menu_page(
        'SurveyPilot',
        'SurveyPilot',
        'manage_options',
        'survey-pilot',
        function () {
            echo '<h1>Hello from SurveyPilot</h1>';
        }
    );
});