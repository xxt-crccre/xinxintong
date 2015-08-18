<?php
namespace cus\crccre\matter;

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/xxt_base.php';
/**
 * 单图文管理
 */
class article extends \xxt_base {
	/**
	 *
	 */
	public function get_access_rule() {
		$rule_action['rule_type'] = 'black';
		$rule_action['actions'] = array();

		return $rule_action;
	}
	/**
	 * 创建单图文消息
	 *
	 * $mpid 单图文所属的公众号
	 * $title 发送的卡片消息标题
	 * $body html格式的消息体
	 * $author 作者，8个字符
	 * $summary 文本摘要 120个汉字或240个字符，可选
	 * $picurl  头图 可选
	 * $srcurl 原文链接 可选
	 */
	public function create_action($mpid, $title, $body, $author = 'crccre', $summary = '', $picurl = '', $srcurl = '', $hidepic = 'Y') {
		$current = time();

		$d['mpid'] = $mpid;
		$d['creater'] = '';
		$d['creater_src'] = 'I';
		$d['creater_name'] = 'crccre';
		$d['author'] = $author;
		$d['create_at'] = $current;
		$d['modify_at'] = $current;
		$d['title'] = $title;
		$d['pic'] = $picurl;
		$d['hide_pic'] = $hidepic;
		$d['summary'] = $summary;
		$d['url'] = $srcurl;
		$d['body'] = $body;

		$id = $this->model()->insert('xxt_article', $d, true);

		return new \ResponseData($id);
	}
	/**
	 * 创建单图文消息
	 *
	 * $mpid 单图文所属的公众号
	 */
	public function upload_action($mpid) {
		$posted = $this->getPostJson();

		$current = time();

		if (empty($posted->title)) {
			return new \ResponseError('文章标题不允许为空');
		}

		if (empty($posted->body)) {
			return new \ResponseError('文章内容不允许为空');
		}
		/**
		 * 替换头图的图片
		 */
		if (!empty($posted->coverpic)) {
			$newUrl = $this->storeUrl($mpid, $posted->coverpic);
			if ($newUrl === false) {
				return new \ResponseError('图片' . $posted->coverpic . '转存失败');
			}
			/* 替换正文中的url*/
			$posted->coverpic = $newUrl;
		} else {
			$posted->coverpic = '';
		}
		/**
		 * 替换文章中的图片
		 */
		if (!empty($posted->body)) {
			$posted->body = urldecode($posted->body);
			foreach ($posted->bodyimgs as $img) {
				/* 将图片保存到本地 */
				$newUrl = $this->storeUrl($mpid, $img);
				if ($newUrl === false) {
					return new \ResponseError('图片' . $img . '转存失败');
				}

				/* 替换正文中的url*/
				$posted->body = str_replace($img, $newUrl, $posted->body);
			}
		} else {
			$posted->body = '';
		}

		/**
		 * 创建单图文
		 */
		$d['mpid'] = $mpid;
		$d['creater'] = '';
		$d['creater_src'] = 'I';
		$d['creater_name'] = 'crccre';
		$d['author'] = isset($posted->author) ? $posted->author : 'crccre';
		$d['create_at'] = $current;
		$d['modify_at'] = $current;
		$d['title'] = $posted->title;
		$d['pic'] = $posted->coverpic;
		$d['hide_pic'] = 'Y';
		$d['summary'] = isset($posted->summary) ? $posted->summary : '';
		$d['url'] = isset($posted->srcurl) ? $posted->srcurl : '';
		$d['body'] = $this->model()->escape($posted->body);

		$id = $this->model()->insert('xxt_article', $d, true);

		return new \ResponseData($id);
	}
	/**
	 * 将指定url的文件转存到oss
	 */
	protected function storeUrl($mpid, $url) {
		/**
		 * 下载文件
		 */
		$ext = 'jpg';
		$response = file_get_contents($url);
		$responseInfo = $http_response_header;
		foreach ($responseInfo as $loop) {
			if (strpos($loop, "Content-disposition") !== false) {
				$disposition = trim(substr($loop, 21));
				$filename = explode(';', $disposition);
				$filename = array_pop($filename);
				$filename = explode('=', $filename);
				$filename = array_pop($filename);
				$filename = str_replace('"', '', $filename);
				$filename = explode('.', $filename);
				$ext = array_pop($filename);
				break;
			}
		}
		/* 每个小时分一个目录 */
		$storename = date("ymdH") . '/' . date("is") . rand(10000, 99999) . "." . $ext;
		/**
		 * 写到公众号的存储空间
		 */
		$fs = $this->model('fs/local', $mpid, '图片');
		$newUrl = $fs->write($storename, $response);

		return $newUrl;
	}
}
