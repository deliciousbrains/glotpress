<?php

/**
 * @group meta
 */
class GP_Test_Meta extends GP_UnitTestCase {

	/**
	 * @dataProvider data_meta_keys
	 */
	function test_gp_sanitize_meta_key( $expected, $meta_key ) {
		$this->assertSame( $expected, gp_sanitize_meta_key( $meta_key ) );
	}

	function data_meta_keys() {
		return array(
			array( 'foo', 'foo' ),
			array( 'fooBar', 'fooBar' ),
			array( 'foobar', 'foo-bar' ),
			array( 'foobar', 'foo.bar' ),
			array( 'foobar', 'foo:bar' ),
			array( 'foo_bar', 'foo_bar' ),
			array( 'foobar123', 'foobar123' ),
			array( 'foobar', 'foo?#+bar' ),
		);
	}

	function test_gp_get_meta_returns_false_for_falsely_object_ids() {
		$this->assertFalse( gp_get_meta( 'foo', null ) );
		$this->assertFalse( gp_get_meta( 'foo', false ) );
		$this->assertFalse( gp_get_meta( 'foo', 0 ) );
		$this->assertFalse( gp_get_meta( 'foo', '' ) );
		$this->assertFalse( gp_get_meta( 'foo', 'bar' ) );
	}

	function test_gp_get_meta_returns_false_for_falsely_object_types() {
		$this->assertFalse( gp_get_meta( null, 1 ) );
		$this->assertFalse( gp_get_meta( false, 1 ) );
		$this->assertFalse( gp_get_meta( '', 1 ) );
		$this->assertFalse( gp_get_meta( 0, 1 ) );
	}

	function test_gp_update_meta_returns_false_for_falsely_object_ids() {
		$this->assertFalse( gp_update_meta( 'key', 'value', 'type', null ) );
		$this->assertFalse( gp_update_meta( 'key', 'value', 'type', false  ) );
		$this->assertFalse( gp_update_meta( 'key', 'value', 'type', 0  ) );
		$this->assertFalse( gp_update_meta( 'key', 'value', 'type', '' ) );
		$this->assertFalse( gp_update_meta( 'key', 'value', 'type', 'bar' ) );
	}

	function test_gp_delete_meta_returns_false_for_falsely_object_ids() {
		$this->assertFalse( gp_delete_meta( 'key', 'value', 'type', null ) );
		$this->assertFalse( gp_delete_meta( 'key', 'value', 'type', false ) );
		$this->assertFalse( gp_delete_meta( 'key', 'value', 'type', 0 ) );
		$this->assertFalse( gp_delete_meta( 'key', 'value', 'type', '' ) );
		$this->assertFalse( gp_delete_meta( 'key', 'value', 'type', 'bar' ) );
	}

	function test_update_meta_should_set_meta() {
		gp_update_meta( 'foo', 'bar', 'thing', '1' );
		$this->assertEquals( 'bar', gp_get_meta( 'thing', '1', 'foo' ) );
	}

	function test_gp_update_meta_updates_an_existing_meta_value() {
		$this->assertInternalType( 'int', gp_update_meta( 'key', 'value-1', 'thing', '1' ) );
		$this->assertTrue( gp_update_meta( 'key', 'value-2', 'thing', '1'  ) );
		$this->assertSame( 'value-2', gp_get_meta( 'thing', '1', 'key' ) );
	}

	function test_delete_meta_without_value_should_delete_meta() {
		gp_update_meta( 'foo', 'bar', 'thing', '1' );
		gp_delete_meta( 'foo', null, 'thing', '1' );
		$this->assertEquals( null, gp_get_meta( 'thing', '1', 'foo' ) );
	}

	function test_delete_meta_with_value_should_delete_only_meta_with_value() {
		gp_update_meta( 'foo', 'bar', 'thing', '1' );
		gp_delete_meta( 'foo', 'bar', 'thing', '1' );
		$this->assertEquals( null, gp_get_meta( 'thing', '1', 'foo' ) );

		gp_update_meta( 'foo', 'foo', 'thing', '1' );
		gp_delete_meta( 'foo', 'bar', 'thing', '1' );
		$this->assertNotEquals( null, gp_get_meta( 'thing', '1', 'foo' ) );
	}

	function test_gp_update_meta_does_not_update_if_prev_value_equals_new_value() {
		$this->assertInternalType( 'int', gp_update_meta( 'foo', 'foo', 'thing', '1' ) );
		$this->assertTrue( gp_update_meta( 'foo', 'foo', 'thing', '1' ) ); // @todo Is this the correct return value?
	}

	/**
	 * @ticket 480
	 */
	function test_get_meta_uses_cache() {
		global $wpdb;

		gp_update_meta( 'foo', 'bar', 'thing', '1' );

		$num_queries = $wpdb->num_queries;

		// Cache is not primed, expect 1 query.
		gp_get_meta( 'thing', '1', 'foo' );
		$this->assertEquals( $num_queries + 1, $wpdb->num_queries );

		$num_queries = $wpdb->num_queries;

		// Cache is primed, expect no queries.
		gp_get_meta( 'thing', '1', 'foo' );
		$this->assertEquals( $num_queries, $wpdb->num_queries );
	}

	/**
	 * @ticket 480
	 */
	function test_get_meta_without_meta_key() {
		gp_update_meta( 'key1', 'foo', 'thing', '1' );
		gp_update_meta( 'key2', 'foo', 'thing', '1' );

		$meta = gp_get_meta( 'thing', '1' );
		$this->assertCount( 2, $meta );
		$this->assertEqualSets( array( 'key1', 'key2' ), array_keys( $meta ) );
	}
}
