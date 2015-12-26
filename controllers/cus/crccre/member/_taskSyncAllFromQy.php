<?php
namespace cus\crccre\member;
/**
 * 组织机构数据同步到企业号
 */
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/member/auth.php';
/**
 * 企业号用户数据批量同步接口
 */
class _taskSyncAllFromQy extends \member\auth {
	/**
	 *
	 */
	public function get_access_rule() {
		$rule_action['rule_type'] = 'white';
		$rule_action['actions'][] = 'syncFromQy';

		return $rule_action;
	}
	/**
	 *
	 */
	public function exec_action() {
		$result = array();
		$modelMpa = $this->model('mp\mpaccount');
		$modelLog = $this->model('log');

		$q = array(
			'mpid,authid',
			'xxt_member_authapi',
			"type='inner' and used=0 and valid='Y'",
		);
		$authapis = $this->model()->query_objs_ss($q);
		foreach ($authapis as $authapi) {
			$mp = $modelMpa->byId($authapi->mpid, 'state,mpsrc,qy_joined,name');
			if ($mp->state !== '1' || $mp->mpsrc !== 'qy' || $mp->qy_joined !== 'Y') {
				continue;
			}
			$log = new \stdClass;
			$log->begin = time();
			$rst = $this->syncFromQy_action($authapi->mpid, $authapi->authid);
			$log->authid = $authapi->authid;
			$log->result = $rst->err_msg;
			$log->end = time();
			$modelLog->log($authapi->mpid, 'taskSyncAllFromQy', json_encode($log));
			$log->mpid = $authapi->mpid;
			$log->mp = $mp->name;
			$result[] = $log;
		}

		return new \ResponseData($result);
	}
}