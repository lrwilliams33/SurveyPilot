<div class="sp-container">
    <h2>Survey Questions</h2>

    <?php
    $confirmation_url = esc_url(add_query_arg('sp_step', 'confirmation', get_permalink()));
    ?>

    <form method="post" action="<?php echo $confirmation_url; ?>">
        
        <div class="sp-question">
            <p>1. The website was easy to navigate.</p>
            <?php for ($i = 1; $i <= 5; $i++) : ?>
                <label>
                    <input type="radio" name="q1" value="<?php echo $i; ?>" required>
                    <?php echo $i; ?>
                </label>
            <?php endfor; ?>
            <div class="sp-scale-labels">
                <span>Strongly Disagree</span>
                <span>Strongly Agree</span>
            </div>
        </div>

        <div class="sp-question">
            <p>2. The content was helpful and clear.</p>
            <?php for ($i = 1; $i <= 5; $i++) : ?>
                <label>
                    <input type="radio" name="q2" value="<?php echo $i; ?>" required>
                    <?php echo $i; ?>
                </label>
            <?php endfor; ?>
        </div>

        <button type="submit" class="sp-button">Submit Survey</button>
    </form>
</div>
