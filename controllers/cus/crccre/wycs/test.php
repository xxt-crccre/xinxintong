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
    /**
     *
     */
    public function memberRemove_action($mpid, $mid)
    {
        $rst = $this->model()->delete('xxt_member', "mpid='$mpid' and mid='$mid'");
        
        return new \ResponseData($rst);
    }
    /**
     * 微信用户与业主身份绑定
     */
    public function bind_action($mpid, $mocker, $phone, $card)
    {
        $projectid = $this->getProjectId($mpid);
        
        $openid = $mocker;
        /**
         * 调用sso接口进行身份验证
         */
        try {
            $soap = $this->soap();
            $param = new \stdClass;
            $param->pk_projectid = $projectid;
            $param->phone = $phone;
            $param->idcard = $card;
            $param->wechatid = $openid;

            $rst = $soap->checkHouseOwner($param); // ??? 返回的结果有错误
            $xml = simplexml_load_string($rst->return);
            if ((string)$xml->result['name'] === 'success') {
                $rst = $this->customInfo($mpid, $openid, $phone);
                if ($rst[0] === false)
                    return new \ResponseError($rst[1]);
                return new \ResponseData($rst[1]);
            } else 
                return new \ResponseError((string)$xml->result->failmessage);
        } catch (Exception $e) {
            return new \ResponseError($e->getMessage());
        }
    }
}
