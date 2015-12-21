<?php
require_once dirname(__FILE__) . '/base.php';
/**
 * 微信公众号代理类
 */
class wx_model extends mpproxy_base {
	/**
	 *
	 * $mpid
	 */
	public function __construct($mpid) {
		parent::__construct($mpid);
	}
	/**
	 *
	 */
	public function reset($mpid) {
		parent::reset($mpid);
		unset($this->wx_token);
	}
	/**
	 * 加密/校验流程：
	 * 1. 将token、timestamp、nonce三个参数进行字典序排序
	 * 2. 将三个参数字符串拼接成一个字符串进行sha1加密
	 * 3. 开发者获得加密后的字符串可与signature对比，标识该请求来源于易信
	 *
	 * 若确认此次GET请求来自易信服务器，请原样返回echostr参数内容，则接入生效，否则接入失败。
	 */
	public function join($data) {
		$signature = $data['signature'];
		$timestamp = $data['timestamp'];
		$nonce = $data['nonce'];
		$echostr = $data['echostr'];

		$mpa = TMS_APP::G('mp\mpaccount');

		$tmpArr = array($mpa->token, $timestamp, $nonce);
		sort($tmpArr, SORT_STRING);
		$tmpStr = implode($tmpArr);
		$tmpStr = sha1($tmpStr);
		if ($tmpStr === $signature) {
			/**
			 * 如果存在，断开公众号原有连接
			 */
			TMS_APP::model()->update(
				'xxt_mpaccount',
				array('wx_joined' => 'N'),
				"wx_appid='$mpa->wx_appid' and wx_appsecret='$mpa->wx_appsecret'");
			/**
			 * 确认建立连接
			 */
			TMS_APP::model()->update(
				'xxt_mpaccount',
				array('wx_joined' => 'Y'),
				"mpid='$this->mpid'");

			return array(true, $echostr);
		} else {
			return array(false, 'failed.');
		}
	}
	/**
	 * 获得与公众平台进行交互的token
	 */
	public function accessToken($newAccessToken = false) {
		$whichToken = "wx_appid,wx_appsecret,wx_token,wx_token_expire_at";
		if ($newAccessToken === false) {
			if (isset($this->wx_token) && time() < $this->wx_token['expire_at'] - 60) {
				/**
				 * 在同一次请求中可以重用
				 */
				return array(true, $this->wx_token['value']);
			}
			/**
			 * 从数据库中获取之前保留的token
			 */
			$app = TMS_APP::model('mp\mpaccount')->byId($this->mpid, $whichToken);
			if (!empty($app->wx_token) && time() < (int) $app->wx_token_expire_at - 60) {
				/**
				 * 数据库中保存的token可用
				 */
				$this->wx_token = array(
					'value' => $app->wx_token,
					'expire_at' => $app->wx_token_expire_at,
				);
				return array(true, $app->wx_token);
			}
		} else {
			/**
			 * 从数据库中获取之前保留的token
			 */
			$app = TMS_APP::model('mp\mpaccount')->byId($this->mpid, $whichToken);
		}
		/**
		 * 重新获取token
		 */
		$url_token = "https://api.weixin.qq.com/cgi-bin/token";
		$url_token .= "?grant_type=client_credential";
		$url_token .= "&appid=$app->wx_appid&secret=$app->wx_appsecret";
		$ch = curl_init($url_token);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
		if (false === ($response = curl_exec($ch))) {
			$err = curl_error($ch);
			curl_close($ch);
			return array(false, $err);
		}
		curl_close($ch);
		$token = json_decode($response);
		if (isset($token->errcode)) {
			return array(false, $token->errmsg);
		}

		/**
		 * 保存获得的token
		 */
		$u["wx_token"] = $token->access_token;
		$u["wx_token_expire_at"] = (int) $token->expires_in + time();

		TMS_APP::model()->update('xxt_mpaccount', $u, "mpid='$this->mpid'");

		$this->wx_token = array(
			'value' => $u["wx_token"],
			'expire_at' => $u["wx_token_expire_at"],
		);

		return array(true, $token->access_token);
	}
	/**
	 * 获得微信JSSDK签名包
	 *
	 * $mpid
	 */
	public function getJssdkSignPackage($url) {
		$mpa = TMS_APP::M('mp\mpaccount')->byId($this->mpid, 'wx_appid');

		$rst = $this->getJsApiTicket();
		if ($rst[0] === false) {
			return $rst;
		}

		$jsapiTicket = $rst[1];

		$timestamp = time();
		$nonceStr = $this->createNonceStr();
		// 这里参数的顺序要按照 key 值 ASCII 码升序排序
		$string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
		$signature = sha1($string);

		$signPackage = array(
			"appId" => $mpa->wx_appid,
			"nonceStr" => $nonceStr,
			"timestamp" => $timestamp,
			"url" => $url,
			"signature" => $signature,
			"rawString" => $string,
		);

		$js = "signPackage={appId:'{$signPackage['appId']}'";
		$js .= ",nonceStr:'{$signPackage['nonceStr']}'";
		$js .= ",timestamp:'{$signPackage['timestamp']}'";
		$js .= ",url:'{$signPackage['url']}'";
		$js .= ",signature:'{$signPackage['signature']}'}";

		return array(true, $js);
	}
	/**
	 *
	 */
	private function createNonceStr($length = 16) {
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$str = "";
		for ($i = 0; $i < $length; $i++) {
			$str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
		}

		return $str;
	}
	/**
	 *
	 */
	private function getJsapiTicket() {
		$mpa = TMS_APP::M('mp\mpaccount')->byId($this->mpid, 'wx_jsapi_ticket,wx_jsapi_ticket_expire_at');

		if (!empty($mpa->wx_jsapi_ticket) && time() < $mpa->wx_jsapi_ticket_expire_at - 60) {
			return array(true, $mpa->wx_jsapi_ticket);
		}

		$cmd = "https://api.weixin.qq.com/cgi-bin/ticket/getticket";
		$params = array('type' => 'jsapi');

		$rst = $this->httpGet($cmd, $params);
		if ($rst[0] === false) {
			return $rst[1];
		}

		$ticket = $rst[1];

		TMS_APP::model()->update(
			'xxt_mpaccount',
			array(
				'wx_jsapi_ticket' => $ticket->ticket,
				'wx_jsapi_ticket_expire_at' => time() + $ticket->expires_in,
			),
			"mpid='$this->mpid'"
		);

		return array(true, $ticket->ticket);
	}
	/**
	 *
	 */
	public function oauthUrl($mpid, $redirect, $state = null) {
		$mpa = TMS_APP::model('mp\mpaccount')->byId($mpid, 'wx_appid');

		$oauth = "https://open.weixin.qq.com/connect/oauth2/authorize";
		$oauth .= "?appid=$mpa->wx_appid";
		$oauth .= "&redirect_uri=" . urlencode($redirect);
		$oauth .= "&response_type=code";
		$oauth .= "&scope=snsapi_base";
		!empty($state) && $oauth .= "&state=$state";
		$oauth .= "#wechat_redirect";

		return $oauth;
	}
	/**
	 *
	 */
	public function getOAuthUser($code) {
		$mpa = TMS_APP::M('mp\mpaccount')->byId($this->mpid, "wx_appid,wx_appsecret");

		$cmd = "https://api.weixin.qq.com/sns/oauth2/access_token";
		$params["appid"] = $mpa->wx_appid;
		$params["secret"] = $mpa->wx_appsecret;
		$params["code"] = $code;
		$params["grant_type"] = "authorization_code";

		$rst = $this->httpGet($cmd, $params, false, false);

		if ($rst[0] === false) {
			return $rst;
		}

		$openid = $rst[1]->openid;

		return array(true, $openid);
	}
	/**
	 * 获得所有的微信粉丝
	 */
	public function userGet($nextOpenid = '') {
		$cmd = 'https://api.weixin.qq.com/cgi-bin/user/get';

		if (!empty($nextOpenid)) {
			$params = array('next_openid' => $nextOpenid);
			$result = $this->httpGet($cmd, $params);
		} else {
			$result = $this->httpGet($cmd);
		}
		return $result;
	}
	/**
	 * 获得一个指定粉丝的信息
	 */
	public function userInfo($openid, $getGroup = false) {
		$cmd = 'https://api.weixin.qq.com/cgi-bin/user/info';

		$params = array('openid' => $openid);

		$userRst = $this->httpGet($cmd, $params);

		if (empty($userRst[1])) {
			return array(false, 'empty openid:' . $openid);
		}

		if ($getGroup && $userRst[0]) {
			/**
			 * 获得粉丝的分组信息
			 */
			$cmd = 'https://api.weixin.qq.com/cgi-bin/groups/getid';
			$posted = json_encode(array("openid" => $openid));
			$groupRst = $this->httpPost($cmd, $posted);
			if ($groupRst[0]) {
				$groupSet = $groupRst[1];
				$groupId = $groupSet->groupid;
				$userRst[1]->groupid = $groupId;
			}
		}

		return $userRst;
	}
	/**
	 * 获得所有的微信粉丝分组
	 */
	public function groupsGet() {
		$cmd = 'https://api.weixin.qq.com/cgi-bin/groups/get';

		$rst = $this->httpGet($cmd);

		return $rst;
	}
	/**
	 * 添加粉丝分组
	 *
	 * 同时在公众平台和本地添加
	 */
	public function groupsCreate($group) {
		/**
		 * 在公众平台上添加
		 */
		$cmd = 'https://api.weixin.qq.com/cgi-bin/groups/create';
		$posted = json_encode(array('group' => $group));
		$rst = $this->httpPost($cmd, $posted);

		return $rst;
	}
	/**
	 * 更新粉丝分组的名称
	 *
	 * 同时修改公众平台的数据和本地数据
	 */
	public function groupsUpdate($group) {
		$cmd = "https://api.weixin.qq.com/cgi-bin/groups/update";
		$posted = json_encode(array('group' => $group));
		$rst = $this->httpPost($posted);

		return $rst;
	}
	/**
	 * 删除粉丝分组
	 */
	public function groupsDelete($group) {
		$group->name = urlencode($group->name);
		$posted = urldecode(json_encode(array('group' => $group)));
		$cmd = "https://api.weixin.qq.com/cgi-bin/groups/delete";
		$rst = $this->httpPost($cmd, $posted);

		return $rst;
	}
	/**
	 * 设置关注用户的分组
	 */
	public function groupsMembersUpdate($openid, $groupid) {
		$cmd = "https://api.weixin.qq.com/cgi-bin/groups/members/update";
		$posted = json_encode(array("openid" => $openid, "to_groupid" => $groupid));
		$rst = $this->httpPost($cmd, $posted);

		return $rst;
	}
	/**
	 * upload menu.
	 */
	public function menuCreate($menu) {
		$cmd = 'https://api.weixin.qq.com/cgi-bin/menu/create';

		$rst = $this->httpPost($cmd, $menu);

		return $rst;
	}
	/**
	 * upload menu.
	 */
	public function menuDelete() {
		$cmd = 'https://api.weixin.qq.com/cgi-bin/menu/delete';

		$rst = $this->httpGet($cmd);

		return $rst;
	}
	/**
	 * 获得下载媒体文件的链接
	 *
	 * $mediaid
	 */
	public function mediaGetUrl($mediaId) {
		$rst = $this->accessToken();
		if ($rst[0] === false) {
			return $rst[1];
		}

		$url = 'http://file.api.weixin.qq.com/cgi-bin/media/get';
		$url .= "?access_token={$rst[1]}";
		$url .= "&media_id=$mediaId";

		return array(true, $url);
	}
	/**
	 * 将图文消息上传到微信公众号平台
	 */
	public function mediaUploadNews($message) {
		/**
		 * 拼装消息
		 */
		$uploaded = array();
		$articles = $message['news']['articles'];
		foreach ($articles as $a) {
			$body = str_replace(array("\r\n", "\n", "\r"), '', $a['body']);
			$uploaded[] = array(
				"thumb_media_id" => $a['thumb_media_id'],
				"title" => urlencode($a['title']),
				"content" => urlencode(addslashes($body)),
				"digest" => urlencode($a['description']),
				"show_cover_pic" => "1",
			);
			!empty($a->url) && $uploaded['content_source_url'] = $a->url;
			!empty($a->author) && $uploaded['author'] = $a->author;
		}
		$uploaded = array('articles' => $uploaded);
		$uploaded = urldecode(json_encode($uploaded));
		/**
		 * 发送消息
		 */
		$cmd = "https://api.weixin.qq.com/cgi-bin/media/uploadnews";

		$rst = $this->httpPost($cmd, $uploaded);
		if ($rst[0] === false) {
			return $rst;
		}

		$media_id = $rst[1]->media_id;

		return array(true, $media_id);
	}
	/**
	 * 将图片上传到公众号平台
	 *
	 * $imageUrl
	 * $imageType
	 */
	public function mediaUpload($mediaUrl, $mediaType = 'image') {
		$tmpfname = $this->fetchUrl($mediaUrl);
		/**
		 * 解决php版本兼容性问题
		 */
		if (class_exists('\CURLFile')) {
			$uploaded['media'] = new \CURLFile($tmpfname);
		} else {
			$uploaded['media'] = '@' . $tmpfname;
		}
		/**
		 * upload image
		 */
		$cmd = 'http://file.api.weixin.qq.com/cgi-bin/media/upload';
		$cmd .= "?type=$mediaType";

		$rst = $this->httpPost($cmd, $uploaded);
		if ($rst[0] === false) {
			return $rst;
		}

		$media_id = $rst[1]->media_id;

		return array(true, $media_id);
	}
	/**
	 * 将图文素材转换为媒体文件
	 *
	 * 微信的群发素材必须上传到腾讯才能发送
	 */
	public function news2Media($message) {
		/**
		 * 图文消息需要上传
		 */
		$articles = &$message['news']['articles'];
		foreach ($articles as &$a) {
			$rst = $this->mediaUpload(urldecode($a['picurl']));
			if ($rst[0] === false) {
				return array(false, '上传头图失败：' . $rst[1]);
			}

			$a['thumb_media_id'] = $rst[1];
		}
		/**
		 * 上传消息
		 */
		$rst = $this->mediaUploadNews($message);
		if ($rst[0] === false) {
			return $rst;
		}

		$message = array(
			'mpnews' => array(
				"media_id" => $rst[1],
			),
			'msgtype' => "mpnews",
		);

		return array(true, $message);
	}
	/**
	 * 将图片上传到公众号平台
	 *
	 * $imageUrl
	 * $imageType
	 */
	public function materialAddMaterial($mediaUrl, $mediaType = 'image') {
		$tmpfname = $this->fetchUrl($mediaUrl);
		/**
		 * 解决php版本兼容性问题
		 */
		if (class_exists('\CURLFile')) {
			$uploaded['media'] = new \CURLFile($tmpfname);
		} else {
			$uploaded['media'] = '@' . $tmpfname;
		}
		/**
		 * upload image
		 */
		$cmd = 'https://api.weixin.qq.com/cgi-bin/material/add_material';
		$cmd .= "?type=$mediaType";

		$rst = $this->httpPost($cmd, $uploaded);
		if ($rst[0] === false) {
			return $rst;
		}

		$media_id = $rst[1]->media_id;
		$url = $rst[1]->url;

		return array(true, $media_id, $url);
	}
	/**
	 * 添加永久素材
	 */
	public function materialAddNews($message) {
		/**
		 * 图文消息需要上传
		 */
		$articles = &$message['news']['articles'];
		foreach ($articles as &$a) {
			$rst = $this->materialAddMaterial(urldecode($a['picurl']));
			if ($rst[0] === false) {
				return array(false, '上传头图失败：' . $rst[1]);
			}

			$a['thumb_media_id'] = $rst[1];
		}
		/**
		 * 拼装消息
		 */
		$uploaded = array();
		$articles = $message['news']['articles'];
		foreach ($articles as $a) {
			$body = str_replace(array("\r\n", "\n", "\r"), '', $a['body']);
			$one = array(
				"thumb_media_id" => $a['thumb_media_id'],
				"title" => urlencode($a['title']),
				"content" => urlencode(addslashes($body)),
				"digest" => urlencode($a['description']),
				"show_cover_pic" => "1",
			);
			!empty($a['author']) && $one['author'] = urlencode($a['author']);
			!empty($a['url']) && $one['content_source_url'] = urlencode($a['url']);
			$uploaded[] = $one;
		}
		$uploaded = array('articles' => $uploaded);
		$uploaded = urldecode(json_encode($uploaded));
		/**
		 * 发送消息
		 */
		$cmd = "https://api.weixin.qq.com/cgi-bin/material/add_news";

		$rst = $this->httpPost($cmd, $uploaded);
		if ($rst[0] === false) {
			return $rst;
		}

		$mediaId = $rst[1]->media_id;

		return array(true, $mediaId);
	}
	/**
	 * 更新永久素材
	 */
	public function materialUpdateNews($mediaId, $article) {
		/**
		 * 上传头图
		 */
		$rst = $this->materialAddMaterial(urldecode($article['picurl']));
		if ($rst[0] === false) {
			return array(false, '上传头图失败：' . $rst[1]);
		}

		$article['thumb_media_id'] = $rst[1];
		/**
		 * 拼装消息
		 */
		$body = str_replace(array("\r\n", "\n", "\r"), '', $article['body']);
		$newOne = array(
			"thumb_media_id" => $article['thumb_media_id'],
			"title" => urlencode($article['title']),
			"content" => urlencode(addslashes($body)),
			"digest" => urlencode($article['description']),
			"show_cover_pic" => "1",
		);
		!empty($article['author']) && $newOne['author'] = urlencode($article['author']);
		!empty($article['url']) && $newOne['content_source_url'] = urlencode($article['url']);

		$uploaded = array(
			'media_id' => $mediaId,
			'articles' => $newOne,
		);
		$uploaded = urldecode(json_encode($uploaded));
		/**
		 * 发送消息
		 */
		$cmd = "https://api.weixin.qq.com/cgi-bin/material/update_news";

		$rst = $this->httpPost($cmd, $uploaded);
		if ($rst[0] === false) {
			return $rst;
		}

		return array(true);
	}
	/**
	 * 删除永久素材
	 */
	public function materialDelMaterial($mediaId) {
		$posted = "{media_id:'$mediaId'}";
		/**
		 * 发送消息
		 */
		$cmd = "https://api.weixin.qq.com/cgi-bin/material/del_material";

		$rst = $this->httpPost($cmd, $posted);
		if ($rst[0] === false) {
			return $rst;
		}

		return array(true);
	}
	/**
	 * 向用户群发消息
	 */
	public function messageMassSendall($message) {
		$cmd = 'https://api.weixin.qq.com/cgi-bin/message/mass/sendall';

		$rst = $this->httpPost($cmd, $message);

		return $rst;
	}
	/**
	 * 向用户发送预览消息
	 */
	public function messageMassPreview($message, $openid) {
		if ($message['msgtype'] == 'news') {
			/**
			 * 图文消息需要上传
			 */
			$rst = $this->news2Media($message);
			if ($rst[0] === false) {
				return $rst;
			}

			$message = $rst[1];
		}
		/**
		 * 发送消息
		 */
		$message['touser'] = $openid;
		$message = urldecode(json_encode($message));

		$cmd = 'https://api.weixin.qq.com/cgi-bin/message/mass/preview';

		$rst = $this->httpPost($cmd, $message);

		return $rst;
	}
	/**
	 * 发送客服消息
	 *
	 * $openid
	 * $message
	 */
	public function messageCustomSend($message, $openid) {
		$message['touser'] = $openid;
		$cmd = 'https://api.weixin.qq.com/cgi-bin/message/custom/send';
		$posted = urldecode(json_encode($message));

		$rst = $this->httpPost($cmd, $posted);

		return $rst;
	}
	/**
	 * 发送模板消息
	 */
	public function messageTemplateSend($message) {
		$cmd = 'https://api.weixin.qq.com/cgi-bin/message/template/send';

		$posted = json_encode($message);

		$rst = $this->httpPost($cmd, $posted);

		return $rst;
	}
	/**
	 * 创建一个二维码响应
	 * 微信的永久二维码最大值100000
	 */
	public function qrcodeCreate($scene_id, $oneOff = true, $expire = 1800) {
		$cmd = 'https://api.weixin.qq.com/cgi-bin/qrcode/create';

		if ($oneOff) {
			$posted = array(
				"action_name" => "QR_SCENE",
				"action_info" => array(
					"expire_seconds" => $expire,
					"scene" => array("scene_id" => $scene_id),
				),
			);
		} else {
			$posted = array(
				"action_name" => "QR_LIMIT_SCENE",
				"action_info" => array(
					"scene" => array("scene_id" => $scene_id),
				),
			);
		}

		$posted = json_encode($posted);
		$rst = $this->httpPost($cmd, $posted);
		if (false === $rst[0]) {
			return $rst;
		}

		$ticket = $rst[1]->ticket;
		$pic = "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=$ticket";

		$d = array(
			'scene_id' => $scene_id,
			'pic' => $pic,
		);
		$oneOff && $d['expire_seconds'] = $expire;

		return array(true, (object) $d);
	}
	/**
	 * 向微信用户群发消息
	 */
	public function send2group($message) {
		if ($message['msgtype'] == 'news') {
			$filter = $message['filter'];
			$rst = $this->news2Media($message);
			if ($rst[0] === false) {
				return $rst;
			}

			$message = $rst[1];
			$message['filter'] = $filter;
		}
		/**
		 * 发送消息
		 */
		$message = urldecode(json_encode($message));
		$rst = $this->messageMassSendall($message);

		return $rst;
	}
}
