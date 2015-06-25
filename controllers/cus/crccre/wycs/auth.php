<?php
namespace cus\crccre\wycs;

require_once dirname(__FILE__).'/base.php';

class auth extends wycs_base {
    /**
     * 发送短信验证码
     */
    public function vcode_action($phone)
    {
        $vcode = mt_rand(1000,9999);
        
        $_SESSION['vcode'] = $vcode;
        $str = "尊敬的客户您的验证码是".$vcode;
        
        $rst = $this->sendSms2($phone, $str);
        
        if ($rst[0] === true)
            return new \ResponseData('ok');
        else 
            return new \ResponseError($rst[1]);
    }
    /**
     * 微信用户与业主身份绑定
     */
    public function bind_action($mpid, $mocker=null)
    {
        $projectid = $this->getProjectId($mpid);
        
        $openid = empty($mocker) ? $this->getCookieOAuthUser($mpid) : $mocker;
        
        $custom = $this->getPostJson();
        
        $vcode = $custom->vcode;
        if ($vcode != $_SESSION['vcode'])
            return new ResponseError('没有获得有效的验证码');
        
        $card = $custom->card;
        $phone = $custom->phone;
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
    /**
     *
     */
    public function user_action($mpid, $mocker='') 
    {
        $openid = empty($mocker) ? $this->getCookieOAuthUser($mpid) : $mocker;

        $rst = $this->customInfo($mpid, $openid);
        if ($rst[0] === false)
            return new \ResponseError($rst[1]);

        return new \ResponseData($rst[1]);
    }
}
