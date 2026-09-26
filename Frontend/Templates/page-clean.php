<?php
/**
 * Clean full-width page template for plugin-owned pages.
 *
 * The plugin's app pages (directory, portal, dashboard, submit) are backed by
 * regular pages whose content is a single shortcode that renders its own
 * header and chrome. The theme's default page.php instead wraps every page in
 * the blog layout — sidebar widgets (Recent Posts, Recent Comments, ...) and a
 * "post your comment" section inherited from the blog index — which sits below
 * the plugin UI as stray blog content.
 *
 * This template renders only the page content (so the shortcode still runs),
 * with no sidebar, comments, or post navigation.
 *
 * @package Zeko_ZEKO_BUSINESS
 **/

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="site-main zbp-page-clean">
	<div class="container">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
			</article>
			<?php
		endwhile;
		?>
	</div>
</main><!-- #primary -->
<?php
get_footer();