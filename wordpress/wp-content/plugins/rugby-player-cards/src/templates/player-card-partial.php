<div class="player-card" style="border:1px solid #ccc; padding:1rem; width: 300px;">
    <h3><?php the_title(); ?></h3>
    <?php if (has_post_thumbnail()) : ?>
        <div style="width:150px; height:150px; overflow:hidden; margin-bottom:10px;">
            <?php the_post_thumbnail('medium'); ?>
        </div>
    <?php endif; ?>

    <?php if ($dob = get_post_meta(get_the_ID(), '_player_dob', true)) : ?>
        <div><strong>DOB:</strong> <?php echo esc_html(date('j M Y', strtotime($dob))); ?></div>
    <?php endif; ?>

    <?php if ($rfuid = get_post_meta(get_the_ID(), '_player_rfuid', true)) : ?>
        <div><strong>RFID:</strong> <?php echo esc_html($rfuid); ?></div>
    <?php endif; ?>

    <a href="<?php the_permalink(); ?>" style="display:block; margin-top:10px;">View Full Profile</a>
</div>
