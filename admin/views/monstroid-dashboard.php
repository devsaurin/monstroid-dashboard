<?php
/**
 * Monstroid dashboard main page template
 *
 * @package   monstroid_dashboard
 * @author    Cherry Team
 * @license   GPL-2.0+
 * @version   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
?>
<div class="md-content">
	<div class="md-description">
		<h2 class="md-description_title"><?php _e( 'Welcome to Monstroid Dashboard', 'monstroid-dashboard' ); ?></h2>
		<div class="md-description_content">
			<img class="md-description_image" src="<?php echo monstroid_dashboard()->plugin_url( 'assets/images/main-screen.png' ); ?>" alt="">
			<div class="md-description_text">
				<div class="md-description_ver">
					<?php
						printf(
							__( 'Monstroid Version: %1$s %2$s', 'monstroid-dashboard' ),
							monstroid_dashboard_updater()->get_current_version(),
							monstroid_dashboard_updater()->check_update_messages( '(<b></b>', ')' )
						);
					?>
				</div>
				<div class="md-description_author">
					<?php
						printf(
							__( 'By %s', 'monstroid-dashboard' ),
							'<a href="http://www.templatemonster.com/" target="_blank">TemplateMonster</a>'
						);
					?>
				</div>
				<div class="md-description_about">
					<?php
						_e( 'Monstroid is powered by the latest Cherry Framework 4 that ensures easy and worry-free installation and customization. With over 30 inbuilt premium extensions, you can get any kind of functionality required for a specific project. Thanks to the brand new backup options, you can restore the theme any time you need.', 'monstroid-dashboard' );
					?>
				</div>
			</div>
		</div>
	</div>
	<div class="md-tips">
		<?php $monstroid_dashboard_tips->get_links(); ?>
	</div>
</div>