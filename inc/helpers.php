<?php

/* Mixpakk helpers functions with unique prefixes: mxp_ */

/* If the $key not exists return an empty string */
function mxp_get_value( $key ) {
	return $key = isset( $key ) ? $key : '';
}

function mxp_is_option_checked( $value ) {
	$checked = '';

	if ( $value == '1' ) {
		$checked = 'checked="checked"';
	}

	return $checked;
}

function mxp_is_radio_checked( $value, $expected_value ) {
	$checked = '';

	if ( $value == $expected_value || ( empty( $value ) && $expected_value == 'felado' ) ) {
		$checked = 'checked="checked"';
	}

	return $checked;
}

function mxp_is_selector_selected( $value, $expected_value ) {
	$selected = '';

	if ( $value == $expected_value ) {
		$selected = 'selected="selected"';
	}

	return $selected;
}

function mxp_post_meta( $post_id, $meta_key, $default_value = '' ) {
	$meta_value = get_metadata( 'post', $post_id, $meta_key, true );

	if ( empty( $meta_value ) ) {
		$meta_value = $default_value;
	}

	return $meta_value;
}