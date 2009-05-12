<?php
gp_title( sprintf( __( 'Translations &lt; %s &lt; %s &lt; GlotPress' ), $translation_set->name, $project->name ) );
gp_breadcrumb( array(
	gp_link_home_get(),
	gp_link_project_get( $project, $project->name ),
	$locale->combined_name(),
	$translation_set->name,
) );
wp_enqueue_script( 'editor' );
$parity = gp_parity_factory();
gp_tmpl_header();
?>
<table id="translations" class="translations">
	<tr>
		<th class="original"><?php _e('Original string'); ?></th>
		<th class="translation"><?php _e('Translation'); ?></th>
		<th><?php _e('Actions'); ?></th>
	</tr>
<?php foreach( $translations->entries as $t ):
		$class = str_replace( array( '+', '-' ), '', $t->translation_status );
		if ( !$class )  $class = 'untranslated';
?>
	<tr class="preview <?php echo $parity().' status-'.$class ?>" id="preview-<?php echo $t->original_id ?>" original="<?php echo $t->original_id; ?>">
		<td class="original">			
			<?php echo gp_h( $t->singular ); ?>
			<?php if ( $t->context ): ?>
			<span class="context" title="<?php printf( __('Context: %s'), gp_h($t->context) ); ?>"><?php echo gp_h($t->context); ?></span>
			<?php endif; ?>

		</td>
		<td class="translation"><?php echo gp_h( $t->translations[0] ); ?></td>
		<td class="actions">
			<a href="#" original="<?php echo $t->original_id; ?>" class="edit"><?php _e('Edit'); ?></a>
		</td>
	</tr>
	<tr class="editor" id="editor-<?php echo $t->original_id; ?>" original="<?php echo $t->original_id; ?>">
		<td colspan="3">
			<?php if ( !$t->plural ): ?>
			<p class="original"><?php echo gp_h($t->singular); ?></p>
			<div class="textareas">
				<textarea name="translation[<?php echo $t->original_id; ?>][]" rows="8" cols="80"><?php echo $t->translations[0] ?></textarea>
				<p><a href="#" class="copy" tabindex="-1">Copy from original</a></p>
			</div>	
			<?php else: ?>
				<!--
					TODO: use the correct number of plurals
					TODO: dynamically set the number of rows
				-->				
				<p><?php printf(__('Singular: %s'), '<span class="original">'.gp_h($t->singular).'</span>'); ?></p>
				<div class="textareas">
					<textarea name="translation[<?php echo $t->original_id; ?>][]" rows="8" cols="80"><?php echo $t->translations[0] ?></textarea>
					<p><a href="#" class="copy" tabindex="-1">Copy from original</a></p>					
				</div>
				<p class="clear"><?php printf(__('Plural: %s'), '<span class="original">'.gp_h($t->plural).'</span>'); ?></p>
				<div class="textareas">
					<textarea name="translation[<?php echo $t->original_id; ?>][]" rows="8" cols="80"><?php echo $t->translations[0] ?></textarea>
					<p><a href="#" class="copy" tabindex="-1">Copy from original</a></p>					
				</div>
				
			
			<?php endif; ?>
			<div class="meta">
				<?php if ( $t->context ): ?>
				<p class="context"><?php printf( __('Context: %s'), '<span class="context">'.gp_h($t->context).'</span>' ); ?></p>
				<?php endif; ?>
				<?php if ( $t->extracted_comment ): ?>
				<p class="comment"><?php printf( __('Comment: %s'), make_clickable( gp_h($t->extracted_comment) ) ); ?></p>
				<?php endif; ?>
			</div>
			<div class="actions">
				<button class="ok">Add translation</button>
				<a href="#" class="close"><?php _e('Close'); ?></a>
			</div>
		</td>
	</tr>
<?php endforeach; ?>
</table>
<p><?php gp_link( gp_url_project( $project, gp_url_join( $locale->slug, $translation_set->slug, 'import-translations' ) ), __('Import translations') ); ?></p>
<?php gp_tmpl_footer(); ?>