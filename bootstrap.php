<?php
/**
 * BlockForm Bootstrap.
 *
 * @package   blockform
 * @author    David Cramer
 * @license   GPL-2.0+
 * @copyright 2021/09/18 David Cramer
 */

namespace BlockForm;

/**
 * Activate the plugin core.
 */
function activate_blockform() {
	// Include the core class.
	include_once BLOCKFORM_PATH . 'classes/class-blockform.php';
	BlockForm::get_instance();
}

add_action( 'init', 'BlockForm\activate_blockform' );
