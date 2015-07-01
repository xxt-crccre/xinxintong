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
                return new \ParameterError((string)$xml->result->failmessage);
        } catch (Exception $e) {
            return new \ResponseError($e->getMessage());
        }
    }
    /**
     *
     */
    public function submit_action($mpid='94c9a3a001041bb895430ea7b5014023', $openid='o9HqNs4HQgmCQGQNZYHZL4UJpO8Y')
    {
        $projectid = $this->getProjectId($mpid);
        $billType = "维修单";
        
        $data = new \stdClass;
        $data->clientid = '002AC841470026759456';
        $data->content = 'yy-test';
        $data->houseid = '002AE5170E00190D7C60';
        $data->isowner = 'Y';
        
        return $this->doSubmit($mpid, $openid, $projectid, $data, $billType);
    }
}