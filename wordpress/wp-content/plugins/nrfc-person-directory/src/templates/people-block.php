<?php
/**
 * Template for rendering people block
 *
 * @var array $data Template data containing 'entries', 'attributes', etc.
 */

use PersonDirectory\PersonDirectory;

// Extract variables from data array at the top of the template
$people = $data['entries'] ?? [];
$attributes = $data['attributes'] ?? [];

if (empty($people)) {
	return;
}
?>

<div class="person-directory-container">
    <div class="person-directory-card-container">
        <?php foreach ($people as $person) { ?>

            <div class="person-directory-image-text-card">
                <div class="person-directory-image-container">
                    <img
                            src="<?php echo $person['image_attrs']['src']; ?>"
                            alt="<?php echo $person['image_attrs']['alt']; ?>"
                            title="<?php echo $person['image_attrs']['title']; ?>"
                            width="<?php echo $person['image_attrs']['width']; ?>"
                            height="<?php echo $person['image_attrs']['height']; ?>"
                    />
                    <div class="person-directory-image-overlay">
                        <p class="person-directory-name"><?php echo $person['name']; ?></p>
                    </div>
                </div>
                <div class="person-directory-text-content {">
                    <?php if (!empty($person['label'])) { ?>
                        <div class="person-directory-text-element">
                            <p class="person-directory-role"><?php echo $person['label'] ?></p>
                        </div>
                    <?php } ?>
                    <?php if (!empty($person['email'])) { ?>
                        <div class="person-directory-text-element email-container">
                            📧 <a href="mailto:<?php echo $person['email']; ?>" class="email-link">Email</a>
                            <span class="email-popup">
                                <a href="mailto:<?php echo $person['email']; ?>"><?php echo $person['email']; ?></a>
                            </span>
                        </div>
                    <?php } ?>
                    <?php if (!empty($person['phone'])) { ?>
                        <div class="person-directory-text-element">
                           &phone;  <?php echo $person['phone']; ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
    </div>
</div>