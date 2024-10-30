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