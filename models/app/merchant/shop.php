<?php
namespace app\merchant;
/**
 *
 */
class shop_model extends \TMS_MODEL {
	/*
	 *
	 */
	public function &byId($id) {
		$q = array(
			'*',
			'xxt_merchant_shop s',
			"id='$id'",
		);

		$shop = $this->query_obj_ss($q);

		return $shop;
	}
	/**
	 * $mpid
	 */
	public function &byMpid($mpid) {
		$q = array(
			'*',
			'xxt_merchant_shop s',
			"mpid='$mpid'",
		);
		$q2 = array('o' => 'create_at desc');

		$shops = $this->query_objs_ss($q, $q2);

		return $shops;
	}
	/**
	 *
	 */
	public function &staffAcls($mpid, $shopid, $role) {
		/**
		 * 直接指定
		 */
		$q = array(
			's.id,s.identity,s.idsrc,s.label',
			'xxt_merchant_staff s',
			"s.mpid='$mpid' and s.shopid='$shopid' and role='$role'",
		);
		$acls = $this->query_objs_ss($q);

		return $acls;
	}
}
