<?php
namespace cus\crccre\wycs;

require_once dirname(dirname(dirname(dirname(__FILE__)))).'/member_base.php';
/**
 *
 */
class wycs_base extends \member_base {

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
            'http://wycs.crccre.cn/axis2/services/wykf_wechat_Service?wsdl', 
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
    protected function customInfo($mpid, $openid)
    {
        $projectid = $this->getProjectId($mpid);
         
        $param = new \stdClass;
        $param->pk_projectid = $projectid;
        $param->wechatid = $openid;
        $rst = $this->soap()->queryClientInfo($param);
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
                $this->setCustom2Member($mpid, $openid, $custom);
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
        include_once dirname(__FILE__).'/PROJECTS.php';

        return $MPID_TO_PROJECTID[$mpid]['projectid'];
    }
    /**
     * authid=19
     */
    private function setCustom2Member($mpid, $openid, $client, $mobile='')
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
            $house = array();
            foreach ($client['houselist'] as $h) {
                $house[] = $h['name']. (empty($h['clienttype'] || $h['clienttype']==='null') ? '': "（".$h['clienttype']."）");
            }
            $member->house = implode(',', $house);
            /**
             * 打标签
             */
            //$member->tags = $tags;                       
            
            $fan = $this->model('user/fans')->byOpenid($mpid, $openid, 'fid');
            $this->model('user/member')->create($fan->fid, $member, $attrs);
        } else {
            /**
             * 更新已有认证用户
             */
        }
        
        return false;
    }
}
