<?php
$sponsors = new WP_Query(
    [
    'post_type' => 'sponsor',
    'posts_per_page' => -1,
    'orderby' => 'title',
    'order' => 'ASC',
    ]
);

if ($sponsors->have_posts()) : ?>
    <div class="header-sponsors">
    <?php while ($sponsors->have_posts()) :
        $sponsors->the_post(); ?>
        <?php $sponsor_link = get_post_meta(get_the_ID(), '_sponsor_link', true); ?>
        <div class="sponsor-logo">
            <?php if (!empty($sponsor_link)) : ?>
                <a href="<?php echo esc_url($sponsor_link); ?>" target="_blank">
                    <?php the_post_thumbnail('medium'); ?>
                </a>
            <?php else : ?>
                <?php the_post_thumbnail('medium'); ?>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>
<?php endif; ?>