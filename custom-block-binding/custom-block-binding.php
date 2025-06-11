<?php

/**
 * Plugin Name: Custom Block Binding
 * Plugin URI: https://example.com/plugins/custom-block-binding
 * Description: Register block binding
 * Author: Chris Zarate
 * Author URI: https://example.com
 * Version: 1.0.0
 */

class CustomBlockBinding {
	protected $block_binding_source = 'custom/my-block-binding';
	protected $context_name = 'custom/customAttribute';

	public function __construct() {
		add_action( 'init', [ $this, 'register_block_binding_source' ] );
		add_filter( 'register_block_type_args', [ $this, 'register_block_type_args' ], 10, 2 );
	}

	public function register_block_binding_source() {
		register_block_bindings_source( $this->block_binding_source, [
			'label' => 'My Block Binding',
			'get_value_callback' => [ $this, 'block_binding_callback' ],
			'uses_context' => [ $this->context_name ],
		] );
	}

	public function block_binding_callback( $source_args, $block ) {
		return $block->context[ $this->context_name ] ?? 'Fallback value';
	}

	public function register_block_type_args( $args, $block_type ) {
		if ( 'core/group' !== $block_type ) {
			return $args;
		}

		$args['attributes']['myAttribute'] = [
			'type' => 'string',
			'default' => 'Value from context',
		];
		$args['provides_context'] = [ $this->context_name => 'myAttribute' ];

		return $args;
	}
}

new CustomBlockBinding();
