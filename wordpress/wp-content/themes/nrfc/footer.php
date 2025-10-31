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

				if (empty($logo)) {
					echo '<span class="sponsor-name">' . esc_html($sponsor->post_title) . '</span>';
                    } else {
					$logo_id = attachment_url_to_postid($logo);

					if ($logo_id) {
						$sponsor_logo = wp_get_attachment_image_src($logo_id, 'sponsor-logo');
						$logo_url = $sponsor_logo ? $sponsor_logo[0] : $logo;
					} else {
						$logo_url = $logo;
					}

					echo '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($sponsor->post_title) . '" class="sponsor-logo">';
				}


				if (!empty($url)) {
					echo '</a>';
				}
				echo '</div>';
			}
		} else {
			echo '<p>No gold sponsors found.</p>';
		}
		?>
		<p>&copy; <?php echo date('Y'); ?> Norwich Rugby Club</p>

	</div>
</div>