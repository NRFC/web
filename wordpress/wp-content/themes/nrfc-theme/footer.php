<footer>
  <div class="gold-sponsors">
    <h3>Gold Sponsors</h3>
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
</footer>
<?php wp_footer(); ?>
</body>
</html>
