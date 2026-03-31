/**
 * DH Google Reviews - Block Registration
 *
 * Registers the dh/google-reviews block type with WordPress using
 * the metadata from block.json. The save function returns null because
 * rendering is handled entirely server-side via the PHP render_callback.
 *
 * @package DH_Reviews
 * @version 1.0.0
 * @see     SPEC.md Section 6.2
 */

import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';

registerBlockType( metadata, {
	edit: Edit,

	/**
	 * The save function returns null because this block uses a PHP
	 * render_callback. WordPress stores a dynamic block comment placeholder
	 * and calls render_callback on every page load.
	 *
	 * @return {null} No static output.
	 */
	save: () => null,
} );
