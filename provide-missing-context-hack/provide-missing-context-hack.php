<?php

/**
 * Plugin Name: Provide Missing Context Hack
 * Plugin URI: https://example.com/plugins/provide-missing-context-hack
 * Description: Workaround for missing context inheritance in synced patterns.
 * Author: Chris Zarate
 * Author URI: https://example.com
 * Version: 1.0.0
 */

/**
 * WORKAROUND FOR WP CORE ISSUE: CONTEXT INHERITANCE FOR SYNCED PATTERNS
 * ===
 *
 * Synced patterns are implemented as a special block type (`core/block`) with
 * a `ref` attribute that points to the post ID of the synced pattern. It is a
 * dynamic block, so it has a render callback function that is responsible for
 * loading the pattern and rendering it.
 *
 * https://github.com/WordPress/wordpress/blob/6.6.1/wp-includes/class-wp-block.php#L519
 * https://github.com/WordPress/wordpress/blob/6.6.1/wp-includes/blocks/block.php#L109
 * https://github.com/WordPress/wordpress/blob/6.6.1/wp-includes/blocks/block.php#L19
 * https://github.com/WordPress/wordpress/blob/6.6.1/wp-includes/blocks/block.php#L90
 *
 * Unfortunately, the render callback function delegates to `do_blocks()`,
 * which does not allow passing context and therefore breaks the context
 * inheritance chain for its inner blocks. Many block bindings rely on this
 * context inheritance to work, including ours. :/
 *
 * Core faces this exact same issue for sync pattern overrides, which are
 * implemented as a block binding. Core added a narrowly targeted workaround
 * for their binding, which adds a temporary filter to supply context
 * to the inner blocks of synced patterns. However, their workaround is
 * hardcoded for synced patterns, so we cannot benefit from it:
 *
 * https://github.com/WordPress/wordpress/blob/6.6.1/wp-includes/blocks/block.php#L83-L87
 *
 * However, we can add our own similar workaround. It requires filtering the
 * block type args for the `core/block` block type to make two changes:
 *
 * 1. Add our context to the `uses_context` array so the the synced pattern
 *    block has access to it. We do this only to make the context available to
 *    our changes in step 2.
 *
 * 2. Wrap the block render callback function with a new function. This function
 *    adds a temporary filter to inject the context for inner blocks.
 */
function inject_context_for_synced_patterns( array $block_type_args, string $block_name ): array {
	if ( 'core/block' !== $block_name ) {
		return $block_type_args;
	}

	$context_name = 'custom/customAttribute';

	// Add our context to the `uses_context` array so the the synced pattern block
	// has access to it.
	$block_type_args['uses_context'] = array_merge(
		$block_type_args['uses_context'] ?? [],
		[ $context_name ]
	);

	// Wrap the existing block render callback.
	$block_type_args['render_callback'] = static function ( array $attributes, string $content, WP_Block $synced_pattern_block ) use ( $block_type_args ): string {

		// Add a temporary filter to inject the context for inner blocks.
		$filter_block_context = static function ( array $context ) use ( $synced_pattern_block ): array {
			if ( isset( $synced_pattern_block->context ) ) {
				return array_merge( $context, $synced_pattern_block->context );
			}

			return $context;
		};
		add_filter( 'render_block_context', $filter_block_context, 10, 1 );

		// Call the original render callback.
		$rendered_content = call_user_func( $block_type_args['render_callback'], $attributes, $content, $synced_pattern_block );

		// Remove the temporary filter.
		remove_filter( 'render_block_context', $filter_block_context, 10 );

		return $rendered_content;
	};

	return $block_type_args;
}
add_filter( 'register_block_type_args', 'inject_context_for_synced_patterns', 10, 2 );
