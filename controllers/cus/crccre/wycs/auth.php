<?php
namespace cus\crccre\wycs;

require_once dirname(__FILE__).'/base.php';

class auth extends wycs_base {
    /**
     * 打开认证页面
     *
     * $mpid
     * $authid
     * $openid
     *
     * 打开认证页，完成认证不一定意味着通过认证，可能还需要发送验证邮件或短信验证码
     *
     * 如果公众号支持OAuth，那么应该优先使用OAuth获得openid
     * 只有在无法通过OAuth获得openid时才完全信任直接传入的openid
     * 直接传入的openid不一定可靠
     *
     * 因为微信中OAuth不能在iframe中执行，所以需要在一开始进入页面的时候就执行OAuth，不能等到认证时再执行
     * 所以只有在无法获得之前页面取得OAuth时，认证页面才做OAuth
     *
     */
    public function index_action($mpid, $authid, $code=null) 
    {
        $this->redirect("/rest/mi/matter?mpid=$mpid&id=41&type=article");    
    }
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
    public function bind_action($mpid)
    {
        $custom = $this->getPostJson();
        
        if (empty($custom->phone))
            return new \ParameterError('未提供手机号');
        if (empty($custom->card))
            return new \ParameterError('未提供身份证后4位');
        
        $projectid = $this->getProjectId($mpid);
        
        $openid = $this->getCookieOAuthUser($mpid);
        
        //$vcode = $custom->vcode;
        //if ($vcode != $_SESSION['vcode'])
        //    return new ResponseError('没有获得有效的验证码');
        
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
                return new \ParameterError((string)$xml->result->failmessage);
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
    /**
     * 返回组织机构组件
     */
    public function memberSelector_action($authid)
    {
        $addon = array(
            'js'=>'/views/default/member/memberSelector.js',
            'view'=>"/rest/member/auth/organization?authid=$authid"
        );
        return new \ResponseData($addon);
    }
    /**
     *
     */
    public function organization_action($authid)
    {
        $this->view_action('/member/memberSelector');
    }
    /**
     * 检查指定用户是否在acl列表中
     *
     * $authid
     * $uid
     */
    public function checkAcl_action($authid,$uid)
    {
        $q = array(
            '*',
            'xxt_member',
            "authapi_id=$authid and authed_identity='$uid' and forbidden='N'"
        );
        $members = $this->model()->query_objs_ss($q);
        if (empty($members)) 
            return new \ResponseError('指定的认证用户不存在');

        $acls = $this->getPostJson();

        foreach ($members as $member) {
            foreach ($acls as $acl) {
                switch ($acl->idsrc) {
                case 'D':
                    $depts = json_decode($member->depts);
                    if (!empty($depts)) {
                        $aDepts = array();
                        foreach ($depts as $ds)
                            $aDepts = array_merge($aDepts, $ds);
                        if (in_array($acl->identity, $aDepts))
                            return new \ResponseData('passed');
                    }
                    break;
                case 'T':
                    $aMemberTags = explode(',', $member->tags);
                    $aIdentity = explode(',', $acl->identity);
                    $aIntersect = array_intersect($aIdentity, $aMemberTags);
                    if (count($aIntersect) === count($aIdentity))
                        return new \ResponseData('passed');
                    break;
                case 'M':
                    if ($member->mid === $acl->identity)
                        return new \ResponseData('passed');
                    break;
                case 'DT':
                    $depts = json_decode($member->depts);
                    if (!empty($depts)) {
                        $aMemberDepts = array();
                        foreach ($depts as $ds)
                            $aMemberDepts = array_merge($aMemberDepts, $ds);
                        $aMemberTags = explode(',', $member->tags);
                        /**
                         * 第一个是部门，后面是标签，需要同时匹配
                         */
                        $aIdentity = explode(',', $acl->identity);
                        if (in_array($aIdentity[0], $aMemberDepts)) {
                            unset($aIdentity[0]);
                            $aIntersect = array_intersect($aIdentity, $aMemberTags);
                            if (count($aIntersect) === count($aIdentity))
                                return new \ResponseData('passed');
                        }
                    }
                    break;
                }
            }
        }

        return new \ResponseError('no matched');
    }
}