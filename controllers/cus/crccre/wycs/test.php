<?php
namespace cus\crccre\wycs;
    
require_once dirname(__FILE__).'/submit_base.php';
/**
 * 提交投诉单
 */
class test extends submit_base {
    /**
     *
     */
    public function customInfo_action($mpid, $openid)
    {
        $rst = $this->customInfo($mpid, $openid);
        
        return new \ResponseData($rst[1]);
    }
    /**
     *
     */
    public function memberInfo_action($mpid, $openid)
    {
        $rst = $this->model('user/member')->byOpenid($mpid, $openid);
        
        return new \ResponseData($rst);
    }
}
