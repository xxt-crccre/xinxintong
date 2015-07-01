<?php
namespace cus\crccre\wycs;

require_once dirname(dirname(dirname(dirname(__FILE__)))).'/member_base.php';
/**
 *
 */
class wycs_base extends \member_base {
    
    protected $MPID_TO_PROJECTID = array(
        '94c9a3a001041bb895430ea7b5014023'=>array(
            'projectid'=>'0044C0760E005433F21D',
        )
    );

    public function get_access_rule() 
    {
        $rule_action['rule_type'] = 'black';
        $rule_action['actions'] = array();

        return $rule_action;
    }
    /**
     *
     */
    protected function soap() 
    {
        ini_set('soap.wsdl_cache_enabled', '0');
        $soap = new \SoapClient(
            'http://wykf.crccre.cn/axis2/services/wykf_wechat_Service?wsdl', 
            array(
                'soap_version' => SOAP_1_2,
                'encoding'=>'utf-8',
                'exceptions'=>true, 
                'trace'=>1, 
            )
        );

        return $soap;
    }
    /**
     * 返回当前用户相关信息
     */
    protected function customInfo($mpid, $openid, $mobile='')
    {
        $projectid = $this->getProjectId($mpid);
        
        if (!$projectid) return array(false, '没有找到公众号匹配的项目');
        
        $param = new \stdClass;
        $param->pk_projectid = $projectid;
        $param->wechatid = $openid;
        try {
            $rst = $this->soap()->queryClientInfo($param);
        } catch (Exception $e) {
            return array(false, $e->getMessage());
        }
        $xml = simplexml_load_string($rst->return);
        if ((string)$xml->result['name'] === 'success') {
            if (!isset($xml->result->client)) {
                /**
                 * 没有获得绑定的业主身份，跳转到绑定页
                 */
                return array(false, '用户不存在');
            } else {
                /**
                 * 获取业主绑定的房屋信息
                 */
                foreach ($xml->result->client->attributes() as $n => $v)
                    $client[$n] = (string)$v;
                foreach ($xml->result->houselist->children() as $nodehouse) {
                    foreach ($nodehouse->attributes() as $n => $v)
                        $house[$n] = (string)$v;
                    $houselist[] = $house;
                }
                $custom = array('client'=>$client,'houselist'=>$houselist);
                $this->setCustom2Member($mpid, $openid, $custom, $mobile);
                return array(true, $custom);
            }
        } else 
            return array(false, (string)$xml->result->failmessage);
    }
    /**
     *
     */
    protected function getProjectId($mpid)
    {
        $projectId = isset($this->MPID_TO_PROJECTID[$mpid]) ? $this->MPID_TO_PROJECTID[$mpid]['projectid'] : false;
        
        return $projectId;
    }
    /**
     * authid=19
     */
    protected function setCustom2Member($mpid, $openid, $client, $mobile='')
    {
        $authid = 19;
        /**
         * get auth settings.
         */
        $attrs = $this->model('user/authapi')->byId($authid, 'attr_mobile,attr_email,attr_name,attr_password,extattr'); 
        
        $userModel = $this->model('user/member');
        if (false === ($member = $userModel->byOpenid($mpid, $openid, 'mid', $authid))) {
            /**
             * 创建新认证用户
             */
            $mapTags = array();
            $existentTags = $this->model('user/tag')->byAuthid($authid, 'id,name');
            foreach ($existentTags as $etag) {
                $mapTags[$etag->name] = $etag->id;
            }
            /**
             * 基本信息
             */
            $member = new \stdClass;
            $member->mpid = $mpid;
            $member->authapi_id = $authid;
            $member->authed_identity = $mobile;
            $member->sync_at = time();
            $member->name = $client['client']['name'];
            $member->mobile = $mobile;
            /**
             * 房屋信息
             */
            $memberTags = array();
            $house = array();
            foreach ($client['houselist'] as $h) {
                $housetype = $h['housetype'];
                $clienttype = (empty($h['clienttype']) || $h['clienttype']==='null') ? '' : $h['clienttype'];
                if (!empty($housetype)) {
                    if (isset($mapTags[$housetype])) {
                        $tagid = $mapTags[$housetype];
                    } else {
                        $ntag = new \stdClass;
                        $ntag->authapi_id = $authid;
                        $ntag->name = $housetype;
                        $ntag = $this->model('user/tag')->create($mpid, $ntag);
                        $tagid = $ntag['id'];
                    }
                    isset($tagid) && !in_array($tagid, $memberTags) && $memberTags[] = $tagid;
                }
                if (!empty($clienttype)) {
                    if (isset($mapTags[$clienttype])) {
                        $tagid = $mapTags[$clienttype];
                    } else {
                        $ntag = new \stdClass;
                        $ntag->authapi_id = $authid;
                        $ntag->name = $clienttype;
                        $ntag = $this->model('user/tag')->create($mpid, $ntag);
                        $tagid = $ntag['id'];
                    }
                    isset($tagid) && !in_array($tagid, $memberTags) && $memberTags[] = $tagid;
                }
                
                $house[] = $h['name'] . (empty($clienttype) ? '': "（" . $clienttype . "）");
            }
            $member->house = implode(',', $house);
            /**
             * 打标签
             */
            $member->tags = implode(',', $memberTags);     
            
            $fan = $this->model('user/fans')->byOpenid($mpid, $openid, 'fid');
            $this->model('user/member')->create($fan->fid, $member, $attrs);
        } else {
            /**
             * 更新已有认证用户
             */
        }
        
        return false;
    }
    /**
     * 发送短信
     */    
    protected function sendSms($mobile, $msg)
    {
        $url = "http://if.crccre.cn/WebServices/CallMethod.ashx";
        $url .= "?GUID=D0A54ECB-69B4-4038-88CB-AE7B30EEA37C";
        $url .= "&AppGUID=3a9113bb-720c-4a05-a9fa-bc827ae7afd2";
        $url .= "&Telphone=$mobile";
        $url .= "&Message=$msg";
        
        $rsp = file_get_contents($url);
        
        return $rsp;
    }
    /**
     *
     */
    protected function sendSms2($mobile, $msg)
    {
        ini_set('soap.wsdl_cache_enabled', '0');
        $soap = new \SoapClient(
            'http://111.11.26.232/SmsService/service/SMSPlatformService?wsdl', 
            array(
                'soap_version' => SOAP_1_1,
                'encoding'=>'utf-8',
                'exceptions'=>true, 
                'trace'=>1, 
            )
        );
        
        try {
            $smscontent = $msg;
            $billcode = 'sms6008';
            
            $param = new \stdClass;
            $param->serialNu = 1000052720;
            $param->serialpw = '2014'; 
            $param->numberCount = 1; 
            $param->smcontent = $smscontent;
            $param->billcode = 'sms6008';
            $rst = $soap->auditMessage($param);
            $auditId = (int)$rst->return;
            if ($auditId <= 0) return array(false, $auditId);
            
            $param = new \stdClass;
            $param->phonenumber = array($mobile);
            $param->smscontent = $smscontent;
            $param->billcode = 113; 
            $param->seqno = 1000052720;
            $param->sendtime = '';
            $param->oldpw = '2014';
            $param->auditId = $auditId;
            
            $rst = $soap->sendSMS($param);
            $rst = (int)$rst->return;
            
            return array(true, $rst);
        } catch (Exception $e) {
            return array(false, $e->getMessage());
        }
    }
}
