<?php
namespace cus\crccre\wycs;

require_once dirname(__FILE__).'/base.php';

class auth extends wycs_base {
    /**
     *
     */
    public function vcode_action($phone)
    {
        $vcode = mt_rand(1000,9999);
        
        $_SESSION['vcode'] = $vcode;
        $str = urlencode("您的验证码为".$vcode);
        
        $rst = $this->sendSms($phone, $str);
        if ($rst === '1')
            return new \ResponseData('ok');
        else 
            return new \ResponseError($rst);
    }
    /**
     *
     */
    public function bind_action($mpid, $mocker=null)
    {
        $projectid = $this->getProjectId($mpid);
        
        $openid = empty($mocker) ? $this->getCookieOAuthUser($mpid) : $mocker;
        
        $custom = $this->getPostJson();
        
        //$vcode = $custom->vcode;
        //if ($vcode != $_SESSION['vcode'])
        //    return new ResponseError('没有获得有效的验证码');
        
        $card = $custom->card;
        $phone = $custom->phone;
        /**
         * 调用sso接口进行身份验证
         */
        $rst = $this->customInfo($)
        try {
            $soap = $this->soap();
            $param = new \stdClass;
            $param->pk_projectid = $projectid;
            $param->phone = $phone;
            $param->idcard = $card;
            $param->wechatid = $openid;

            $rst = $soap->checkHouseOwner($param);
            $xml = simplexml_load_string($rst->return);
            if ((string)$xml->result['name'] === 'success') {
                foreach ($xml->result->client->attributes() as $n => $v)
                    $client[$n] = (string)$v;
                foreach ($xml->result->houselist->children() as $nodehouse) {
                    $house = array();
                    foreach ($nodehouse->attributes() as $n => $v)
                        $house[$n] = (string)$v;
                    $houselist[] = $house;
                }
                $custom = array('client'=>$client,'houselist'=>$houselist);
                $this->setCustom2Member($mpid, $openid, $custom, $phone);
                return new \ResponseData($custom);
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
