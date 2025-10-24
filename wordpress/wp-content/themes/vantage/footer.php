			<?php do_action( 'vantage_main_bottom' ); ?>
            <div class="gold-sponsors">
                <div class="sponsors-container">
                    <?php
                    $gold_sponsors = nrfc_get_gold_sponsors();
                    if (!empty($gold_sponsors)) {
                        foreach ($gold_sponsors as $sponsor) {
                            $url = get_post_meta($sponsor->ID, '_sponsor_url', true);
                            $logo = get_the_post_thumbnail_url($sponsor->ID, 'medium');

                            echo '<div class="sponsor">';
                            if (!empty($url)) {
                                echo '<a href="' . esc_url($url) . '" target="_blank">';
                            }

                            if (!empty($logo)) {
                                echo '<img src="' . esc_url($logo) . '" alt="' . esc_attr($sponsor->post_title) . '" class="sponsor-logo">';
                            }

                            echo '<span class="sponsor-name">' . esc_html($sponsor->post_title) . '</span>';

                            if (!empty($url)) {
                                echo '</a>';
                            }
                            echo '</div>';
                        }
                    } else {
                        echo '<p>No gold sponsors found.</p>';
                    }
                    ?>
                </div>
            </div>
            <p>&copy; <?php echo date('Y'); ?> Norwich Rugby Club</p>
		</div><!-- .full-container -->
	</div><!-- #main .site-main -->

	<?php do_action( 'vantage_after_main_container' ); ?>

	<?php do_action( 'vantage_before_footer' ); ?>

	<?php get_template_part( 'parts/footer', apply_filters( 'vantage_footer_type', siteorigin_setting( 'layout_footer' ) ) ); ?>

	<?php do_action( 'vantage_after_footer' ); ?>

</div><!-- #page-wrapper -->

<?php do_action( 'vantage_after_page_wrapper' ); ?>

<?php wp_footer(); ?>

</body>
</html>
