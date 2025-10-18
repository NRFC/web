<?php
global $wpdb;
$player_id = get_query_var('player_id');
$player = get_post($player_id);

if (!$player || $player->post_type !== 'rugby_player') {
    status_header(404);
    get_template_part('404');
    exit;
}

$cards = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}rugby_player_cards
     WHERE player_id = %d ORDER BY card_date DESC",
        $player_id
    )
);

get_header();
?>

    <div class="rugby-player-container" style="max-width:1200px; margin:0 auto; padding:20px;">

        <?php echo esc_html($player->post_title); ?>

        <?php if (has_post_thumbnail($player_id)) : ?>
            <?php echo get_the_post_thumbnail($player_id, 'medium'); ?>
        <?php endif; ?>

        <?php if ($rfuid = get_post_meta($player_id, '_player_rfuid', true)) : ?>
            <?php echo esc_html($rfuid); ?>
        <?php endif; ?>

        <?php if ($rfuid = get_post_meta($player_id, '_player_dob', true)) : ?>
            <?php echo esc_html($rfuid); ?>
        <?php endif; ?>

        <?php if ($cards) : ?>
            <?php foreach ($cards as $index => $card) : ?>
                Current Evaluation (<?php echo date('M j, Y', strtotime($cards[0]->card_date)); ?>)
                Primary Positions: <?php echo esc_html($cards[0]->positions); ?>
                <?php echo wpautop(esc_html($cards[0]->strengths)); ?>
                <?php echo wpautop(esc_html($cards[0]->work_ons)); ?>
            <?php endforeach; ?>

        <?php else : ?>
            <div class="no-cards" style="text-align:center; padding:40px; background:#f5f5f5; border-radius:8px;">
                <p>No evaluation cards have been created for this player yet.</p>
            </div>
        <?php endif; ?>
    </div>

<?php get_footer(); ?>
