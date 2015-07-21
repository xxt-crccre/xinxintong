<?php
namespace cus\crccre\matter;

require_once dirname(dirname(dirname(dirname(__FILE__)))).'/xxt_base.php';
/**
 * 单图文管理 
 */
class article extends \xxt_base {
    /**
     *
     */
    public function get_access_rule() 
    {
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
    public function create_action($mpid, $title, $body, $author='crccre', $summary='', $picurl='', $srcurl='', $hidepic='Y')
    {
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
}
