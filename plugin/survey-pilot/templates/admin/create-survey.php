<div class="wrap">
    <h1>Create Survey</h1>
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('sp_create_survey_nonce'); ?>
        <input type="hidden" name="action" value="sp_create_survey">

        <table class="form-table">
            <tr>
                <th><label for="sp_survey_title">Survey Title</label></th>
                <td><input type="text" name="sp_survey_title" id="sp_survey_title" required class="regular-text"></td>
            </tr>

            <tr>
                <th><label for="sp_survey_description">Description (optional)</label></th>
                <td><textarea name="sp_survey_description" id="sp_survey_description" class="regular-text"></textarea></td>
            </tr>

            <tr>
                <th><label for="sp_survey_instructions">Instructions (optional)</label></th>
                <td><textarea name="sp_survey_instructions" id="sp_survey_instructions" class="regular-text"></textarea></td>
            </tr>
        </table>

        <?php submit_button('Create Survey'); ?>
    </form>
</div>