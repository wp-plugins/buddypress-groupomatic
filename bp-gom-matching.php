<?php

function bp_gom_matching_token_to_value( $pattern, $value )
{
	return str_replace( BP_GOM_TOKEN_ANSWER, $value, $pattern );
}

function bp_gom_matching_all_fields()
{
	global $wpdb, $bp;

	// fields to return
	$fields = array();

	// prep statement
	$sql = $wpdb->prepare( "SELECT id FROM " . $bp->profile->table_name_fields );

	// get all ids as array
	$field_ids = $wpdb->get_col( $sql );

	// loop all ids and create object
	foreach ( $field_ids as $field_id ) {
		$fields[] = new BP_XProfile_Field( $field_id );
	}

	return $fields;
}

function bp_gom_matching_group_lookup( BP_XProfile_Field $field, $user_id )
{
	global $bp, $wpdb;
	
	// get group-o-matic field meta
	$field_meta = new BP_Gom_Field_Meta( $field->id, true );

	// must have a method and pattern
	switch ( true ) {
		case ( empty( $field_meta->method ) ):
		case ( empty( $field_meta->operator ) ):
		case ( empty( $field_meta->pattern ) ):
			// matching NOT possible
			return false;
	}

	// get user's value entered
	$field_data = $field->get_field_data( $user_id );
	$field_value = null;

	// do we have data?
	if ( $field_data instanceof BP_XProfile_ProfileData ) {
		// yep, get value
		$field_value = $field_data->value;
	}

	// null value means impossible to match
	if ( null == $field_value ) {
		return false;
	}

	// if method is slug, lower case the value
	if ( $field_meta->method == 'slug' ) {
		$field_value = strtolower( $field_value );
	}

	// replace tokens
	$pattern = bp_gom_matching_token_to_value( $field_meta->pattern, $field_value );
	
	// default sql vars
	$column = apply_filters( 'bp_gom_matching_group_lookup_column', $field_meta->method, $field_meta );;
	$pattern = apply_filters( 'bp_gom_matching_group_lookup_pattern', $pattern, $field_meta );
	$operator = apply_filters( 'bp_gom_matching_group_lookup_operator', '=', $field_meta );

	if ( is_numeric( $pattern ) ) {
		$pattern_sql = $wpdb->prepare( '%d', $pattern );
	} else {
		$pattern_sql = $wpdb->prepare( '%s', $pattern );
	}

	$sql = $wpdb->prepare(
		"SELECT id FROM {$bp->groups->table_name} WHERE $column $operator $pattern_sql LIMIT 1"
	);

	return $wpdb->get_var( $sql );
}

function bp_gom_matching_groups_meta( $user_id, $use_cache = true )
{
	// load data from cache
	$user_groups_meta = new BP_Gom_User_Groups_Meta( $user_id );

	// use cache data as is?
	if ( $use_cache ) {
		// return stored values
		return $user_groups_meta;
	}

	// loop all fields
	foreach ( bp_gom_matching_all_fields() as $field ) {
		// try to get a matching group
		$group_id = bp_gom_matching_group_lookup( $field, $user_id );
		// get a group?
		if ( $group_id ) {
			$field_meta = new BP_Gom_Field_Meta( $field->id );
			$user_groups_meta->add_group( $group_id, $field_meta );
		}
	}

	// update cache
	$user_groups_meta->update();

	return $user_groups_meta;
}

?>
