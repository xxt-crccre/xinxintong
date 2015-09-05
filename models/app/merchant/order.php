<?php
namespace app\merchant;
/**
 *
 */
class order_model extends \TMS_MODEL {
	/**
	 * $id
	 */
	public function &byId($id) {
		$q = array(
			'*',
			'xxt_merchant_order',
			"id=$id",
		);

		$order = $this->query_obj_ss($q);

		return $order;
	}
	/**
	 * $id
	 */
	public function &byShopid($shopId) {
		$q = array(
			'*',
			'xxt_merchant_order',
			"sid=$shopId",
		);
		$q2 = array(
			'o' => 'order_create_time desc',
		);

		$orders = $this->query_objs_ss($q, $q2);

		return $orders;
	}
	/**
	 * 创建订单
	 */
	public function create($skuId, $user, $info) {
		$sku = \TMS_APP::M('app\merchant\sku')->byId($skuId);

		$order = array(
			'mpid' => $sku->mpid,
			'sid' => $sku->sid,
			'order_status' => 1,
			'order_total_price' => $info->product_count * $sku->price,
			'order_create_time' => time(),
			'order_express_price' => 0,
			'buyer_openid' => $user->openid,
			'buyer_nick' => $user->fan->nickname,
			'receiver_name' => $info->receiver_name,
			'receiver_mobile' => $info->receiver_mobile,
			'product_id' => $sku->prod_id,
			'product_name' => '',
			'product_img' => '',
			'product_sku' => $skuId,
			'product_price' => $sku->price,
			'product_count' => $info->product_count,
		);

		$order['id'] = $this->insert('xxt_merchant_order', $order, true);

		return (object) $order;
	}
}
