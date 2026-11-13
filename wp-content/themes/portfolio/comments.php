<?php
if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="pf-comments">
	<?php if ( have_comments() ) : ?>
		<h3>
			<?php
			printf(
				_n( '%s comentario', '%s comentarios', get_comments_number(), 'portfolio' ),
				number_format_i18n( get_comments_number() )
			);
			?>
		</h3>
		<ol class="comment-list">
			<?php wp_list_comments( array( 'style' => 'ol', 'short_ping' => true ) ); ?>
		</ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>
	<?php comment_form(); ?>
</section>
