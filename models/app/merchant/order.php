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
	 * @param string $tradeNo
	 */
	public function &byTradeNo($tradeNo) {
		$q = array(
			'*',
			'xxt_merchant_order',
			"trade_no='$tradeNo'",
		);

		$order = $this->query_obj_ss($q);

		return $order;
	}
	/**
	 * $id
	 */
	public function &byShopid($shopId, $openid = null) {
		$q = array(
			'*',
			'xxt_merchant_order',
			"sid=$shopId",
		);
		!empty($openid) && $q[2] .= " and buyer_openid='$openid'";

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
		// 订单号
		$trade_no = date('YmdHis') . mt_rand(100000, 999999);

		$sku = \TMS_APP::M('app\merchant\sku')->byId($skuId);

		$product = \TMS_APP::M('app\merchant\product')->byId($sku->prod_id);

		if (empty($info->extPropValues)) {
			$info->extPropValues = new \stdClass;
			$epv = '{}';
		} else {
			$epv = new \stdClass;
			foreach ($info->extPropValues as $k => $v) {
				$epv->{$k} = urlencode($v);
			}
			$epv = urldecode(json_encode($epv));
		}

		$order = array(
			'trade_no' => $trade_no,
			'mpid' => $sku->mpid,
			'sid' => $sku->sid,
			'order_status' => 1,
			'order_total_price' => $info->product_count * $sku->price,
			'order_create_time' => time(),
			'order_express_price' => 0,
			'ext_prop_value' => $epv,
			'buyer_openid' => $user->openid,
			'buyer_nick' => $user->fan->nickname,
			'receiver_name' => $info->receiver_name,
			'receiver_mobile' => $info->receiver_mobile,
			'product_id' => $sku->prod_id,
			'product_name' => $product->name,
			'product_img' => $product->main_img,
			'product_sku' => $skuId,
			'product_price' => $sku->price,
			'product_count' => $info->product_count,
		);

		$order['id'] = $this->insert('xxt_merchant_order', $order, true);
		$order['extPropValue'] = $info->extPropValues;

		return (object) $order;
	}
}