<?php
/**
 * Test for the customer dev api
 *
 * @author John Dillick
 * @since 1.2
 **/
class CustomerDevAPITests extends ShoppTestCase {
	function test_shopp_customer () {
		// Lookup by email
		$Customer = shopp_customer('shopp@shopplugin.net', 'email');

		$this->AssertFalse(!$Customer);
		$this->AssertEquals('Jonathan', $Customer->firstname);
		$this->AssertEquals('Davis', $Customer->lastname);
		$this->AssertEquals('555-555-5555', $Customer->phone);
		$this->AssertEquals('Ingenesis Limited', $Customer->company);

		$this->AssertFalse(!$Customer->Billing);
		$this->AssertEquals('1 N Main Street', $Customer->Billing->address);
		$this->AssertEquals('San Jose', $Customer->Billing->city);
		$this->AssertEquals('CA', $Customer->Billing->state);
		$this->AssertEquals('US', $Customer->Billing->country);
		$this->AssertEquals('95131', $Customer->Billing->postcode);

		$this->AssertFalse(!$Customer->Shipping);
		$this->AssertEquals('1 N Main Street', $Customer->Shipping->address);
		$this->AssertEquals('San Jose', $Customer->Shipping->city);
		$this->AssertEquals('CA', $Customer->Shipping->state);
		$this->AssertEquals('US', $Customer->Shipping->country);
		$this->AssertEquals('95131', $Customer->Shipping->postcode);

		$ID = $Customer->wpuser;

		$Customer = false;

		// Lookup by WordPress user ID
		$Customer = shopp_customer($ID, 'wpuser');

		$this->AssertFalse(!$Customer);
		$this->AssertEquals('Jonathan', $Customer->firstname);
		$this->AssertEquals('Davis', $Customer->lastname);
		$this->AssertEquals('555-555-5555', $Customer->phone);
		$this->AssertEquals('Ingenesis Limited', $Customer->company);

		$this->AssertFalse(!$Customer->Billing);
		$this->AssertEquals('1 N Main Street', $Customer->Billing->address);
		$this->AssertEquals('San Jose', $Customer->Billing->city);
		$this->AssertEquals('CA', $Customer->Billing->state);
		$this->AssertEquals('US', $Customer->Billing->country);
		$this->AssertEquals('95131', $Customer->Billing->postcode);

		$this->AssertFalse(!$Customer->Shipping);
		$this->AssertEquals('1 N Main Street', $Customer->Shipping->address);
		$this->AssertEquals('San Jose', $Customer->Shipping->city);
		$this->AssertEquals('CA', $Customer->Shipping->state);
		$this->AssertEquals('US', $Customer->Shipping->country);
		$this->AssertEquals('95131', $Customer->Shipping->postcode);

		$cid = $Customer->id;
		$Customer = false;

		//Lookup by Shopp customer id
		$Customer = shopp_customer($cid);

		$this->AssertFalse(!$Customer);
		$this->AssertEquals('Jonathan', $Customer->firstname);
		$this->AssertEquals('Davis', $Customer->lastname);
		$this->AssertEquals('555-555-5555', $Customer->phone);
		$this->AssertEquals('Ingenesis Limited', $Customer->company);

		$this->AssertFalse(!$Customer->Billing);
		$this->AssertEquals('1 N Main Street', $Customer->Billing->address);
		$this->AssertEquals('San Jose', $Customer->Billing->city);
		$this->AssertEquals('CA', $Customer->Billing->state);
		$this->AssertEquals('US', $Customer->Billing->country);
		$this->AssertEquals('95131', $Customer->Billing->postcode);

		$this->AssertFalse(!$Customer->Shipping);
		$this->AssertEquals('1 N Main Street', $Customer->Shipping->address);
		$this->AssertEquals('San Jose', $Customer->Shipping->city);
		$this->AssertEquals('CA', $Customer->Shipping->state);
		$this->AssertEquals('US', $Customer->Shipping->country);
		$this->AssertEquals('95131', $Customer->Shipping->postcode);
	}

	function test_shopp_customer_exists () {}

	function test_shopp_customer_marketing () {}

	function test_shopp_customer_marketing_list () {}

	function test_shopp_add_customer () {}

	function test_shopp_add_customer_address () {}

	function test_shopp_rmv_customer () {}

	function test_shopp_address () {}

	function test_shopp_customer_address_count () {}

	function test_shopp_customer_addresses () {}

	function test_shopp_rmv_customer_address () {}
}
?>