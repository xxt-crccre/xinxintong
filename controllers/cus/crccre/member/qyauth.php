<?php
namespace cus\crccre\member;
/**
 * 组织机构数据同步到企业号
 */
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/member_base.php';
/**
 * crccre企业号认证用户接口
 */
class qyauth extends \member_base {
	/**
	 *
	 */
	private $soap;
	/**
	 *
	 */
	public function __construct() {
		$this->authurl = '/rest/cus/crccre/member/qyauth';
	}
	/**
	 *
	 */
	public function get_access_rule() {
		$rule_action['rule_type'] = 'white';
		$rule_action['actions'][] = 'import2Qy';
		$rule_action['actions'][] = 'sync2Qy';

		return $rule_action;
	}
	/**
	 *
	 */
	private function uploadUser2Qy($mpid, $user, &$uCounter, &$existUsers, &$warning) {
		$proxy = $this->model('mpproxy/qy', $mpid);

		if (!array_key_exists($user['useraccount'], $existUsers)) {
			$rst = $proxy->userCreate($user['useraccount'], $user);
			$uCounter++;
		} else {
			unset($existUsers[$user['useraccount']]);
			$rst = $proxy->userUpdate($user['useraccount'], $user);
		}
		if ($rst[0] === false) {
			$w = array($user['useraccount'], $rst[1]);
			if (false !== strpos($rst[1], '60003')) {
				$w['department'] = $user['department'];
			}

			if (false !== strpos($rst[1], '40003')) {
				$w['guid'] = $user['guid'];
			}

			$warning[] = $w;
		}
	}
	/**
	 * 删除部门成员
	 */
	private function deleteDeptUser($mpid, $deptId) {
		$proxy = $this->model('mpproxy/qy', $mpid);

		$rst = $proxy->userList($deptId, 1, 0);
		if ($rst[0] === false) {
			return $rst;
		}
		foreach ($rst[1]->userlist as $user) {
			/* 解除用户和部门的关系 */
			$newUser = new \stdClass;
			$userDepts = $user->department;
			unset($userDepts[array_search($deptId, $userDepts)]);
			$newUser->department = $userDepts;
			$rst = $proxy->userUpdate($user->userid, $newUser);
			if ($rst[0] === false) {
				return $rst;
			}
		}
		return array(true);
	}
	/**
	 * 删除子部门
	 */
	private function deleteDeptAndSub($mpid, $deptId, &$existDepts) {
		$proxy = $this->model('mpproxy/qy', $mpid);

		$rst = $proxy->departmentList($deptId);
		if ($rst[0] === false) {
			return $rst;
		}
		if (count($rst[1]->department) === 1) {
			$rst = $proxy->departmentDelete($deptId);
			if ($rst[0] === false) {
				return $rst;
			} else {
				unset($existDepts[$deptId]);
				return array(true);
			}
		} else {
			foreach ($rst[1]->department as $subDept) {
				if ($subDept->parentid == $deptId) {
					continue;
				}
				$rst = $this->deleteDeptAndSub($mpid, $deptId, $existDepts);
			}
		}
		return array(true);
	}
	/**
	 * 本地通讯录中的部门和其下的子部门同步到企业号通讯录
	 */
	private function deleteDeptFromQy($mpid, &$existDepts, &$warning) {
		foreach ($existDepts as $deptId => $dept) {
			/* 删除部门成员 */
			$rst = $this->deleteDeptUser($mpid, $deptId);
			if ($rst[0] === false) {
				$warning[] = array($deptId, $rst[1]);
			}
			/* 删除子部门 */
			$rst = $this->deleteDeptAndSub(mpid, $deptId, $existDepts);
			if ($rst[0] === false) {
				$warning[] = array($deptId, $rst[1]);
			}
		}
		return array(true);
	}
	/**
	 * 本地通讯录中的部门和其下的子部门同步到企业号通讯录
	 */
	private function uploadDept2Qy($mpid, $dept, &$dCounter, &$existDepts, &$localDepts, &$warning) {
		$localDepts[$dept['guid']] = $dept['id'];
		$proxy = $this->model('mpproxy/qy', $mpid);

		if (!array_key_exists($dept['id'], $existDepts)) {
			/*
			 * 创建新部门
			 */
			$rst = $proxy->departmentCreate($dept['title'], $dept['pid'], $dept['order'], $dept['id']);
			if ($rst[0] === false) {
				/**
				 * 失败
				 */
				if (false !== strpos($rst[1], '60008')) {
					/**
					 * 部门名称已存在
					 */
					$rst2 = $proxy->departmentCreate($dept['title'] . '_' . $dept['order'], $dept['pid'], $dept['order'], $dept['id']);
					if ($rst2[0] === false) {
						/**
						 * 无法创建部门
						 */
						$warning[] = array($dept['guid'], $rst[1]);
						return $rst;
					}
				} else {
					/**
					 * 无法创建部门
					 */
					$warning[] = array($dept['guid'], $rst[1]);
					return $rst;
				}
			}
			$dCounter++;
		} else {
			// 如果部门已经存在，就从已存在部门中去除，剩下的部门为应该删除的部门
			unset($existDepts[$dept['id']]);
			/**
			 * 更新部门
			 */
		}
		/**
		 * 同步子部门
		 */
		$children = $this->model('cus/org')->nodes($dept['guid']);
		foreach ($children as $order => $child) {
			/* 员工节点 */
			if ($child['titletype'] === '5') {
				continue;
			}

			$child['order'] = $order + 1;
			$child['pid'] = $dept['id'];
			$rst = $this->uploadDept2Qy($mpid, $child, $dCounter, $existDepts, $localDepts, $warning);
			if ($rst[0] === false) {
				$warning[] = array($dept['guid'], $rst[1]);
			}

		}

		return array(true);
	}
	/**
	 * 将内部组织结构数据全量导入到企业号通讯录
	 *
	 * $mpid
	 * $authid
	 * $next 执行的阶段
	 * $step
	 */
	public function import2Qy_action($mpid, $authid, $next = null, $step = 0) {
		/**
		 * 更新时间戳
		 */
		$timestamp = time();

		if (empty($next)) {
			/**
			 * 获得企业号通讯录中已有的所有部门
			 */
			$rst = $this->model('mpproxy/qy', $mpid)->departmentList(1);
			if ($rst[0] === false) {
				return new \ResponseError($rst[1]);
			}

			$existDepts = array();
			foreach ($rst[1]->department as $rdept) {
				$existDepts[$rdept->id] = $rdept;
			}

			$_SESSION['existDepts'] = $existDepts;

			return new \ResponseData(array('param' => array('next' => 1, 'desc' => '获取企业号通讯录中已有的部门')));
		}
		/**
		 * 更新部门数据
		 */
		if ($next == 1) {
			$warning = array();
			$dCounter = 0;
			$existDepts = $_SESSION['existDepts'];
			$localDepts = array();
			$nodes = $this->model('cus/org')->nodes(); // 获得根部门
			foreach ($nodes as $order => $node) {
				$node['order'] = $order + 1;
				$node['pid'] = 1;
				$this->uploadDept2Qy($mpid, $node, $dCounter, $existDepts, $localDepts, $warning);
			}
			$_SESSION['existDepts'] = $existDepts;
			$_SESSION['dCounter'] = $dCounter;
			$_SESSION['localDepts'] = $localDepts;
			$_SESSION['warning'] = $warning;

			return new \ResponseData(array('param' => array('next' => 2, 'desc' => '完成部门数据到企业号通讯录的同步')));
		}
		/**
		 * 获得企业号通讯录中已有的所有的用户
		 */
		if ($next == 2) {
			$localDepts = $_SESSION['localDepts'];
			$uploadUsers = array();
			$rst = $this->model('mpproxy/qy', $mpid)->userSimpleList(1);
			if ($rst[0] === false) {
				return new \ResponseError($rst[1]);
			}
			$existUsers = array();
			foreach ($rst[1]->userlist as $ruser) {
				$existUsers[$ruser->userid] = $ruser;
			}
			$_SESSION['existUsers'] = $existUsers;

			return new \ResponseData(array('param' => array('next' => 3, 'desc' => '获得企业号通讯录中已有的所有的用户')));
		}
		/**
		 * 获得本地用户数据
		 */
		if ($next == 3) {
			$localDepts = $_SESSION['localDepts'];
			$existUsers = $_SESSION['existUsers'];
			$uploadUsers = array();
			$nodes = $this->model('cus/org')->getNodesByTitleType('5');
			foreach ($nodes as $node) {
				if (isset($uploadUsers[$node['useraccount']])) {
					$user = $uploadUsers[$node['useraccount']];
				} else {
					$mobile = empty($node['mobile']) ? '151' . rand(1000, 9999) . '0000' : $node['mobile'];
					$user = array(
						'guid' => $node['guid'],
						'useraccount' => $node['useraccount'],
						'name' => $node['title'],
						'mobile' => $mobile,
						'department' => array(),
					);
				}
				if (isset($localDepts[$node['parentid']])) {
					$user['department'][] = $localDepts[$node['parentid']];
				}
				$uploadUsers[$node['useraccount']] = $user;
			}
			$_SESSION['uploadUsers'] = $uploadUsers;

			return new \ResponseData(array('param' => array('next' => 4, 'desc' => '获得所有本地用户数据')));
		}
		/**
		 * 更新用户数据
		 */
		if ($next == 4) {
			$uCounter = isset($_SESSION['uCounter']) ? $_SESSION['uCounter'] : 0;
			$warning = $_SESSION['warning'];
			$existUsers = $_SESSION['existUsers'];
			$uploadUsers = $_SESSION['uploadUsers'];
			$counter = 0;
			$user = current($uploadUsers);
			while ($user) {
				$this->uploadUser2Qy($mpid, $user, $uCounter, $existUsers, $warning);
				unset($uploadUsers[$user['useraccount']]);
				$counter++;
				if ($counter === 100) {
					$_SESSION['uploadUsers'] = $uploadUsers;
					$_SESSION['existUsers'] = $existUsers;
					$_SESSION['warning'] = $warning;
					$_SESSION['uCounter'] = $uCounter;
					$step++;
					return new \ResponseData(array('param' => array('next' => 4, 'desc' => '分批同步用户数据', 'step' => $step, 'left' => count($uploadUsers))));
				}
				$user = next($uploadUsers);
			}

			return new \ResponseData(array('param' => array('next' => 5, 'desc' => '同步用户数据')));
		}
		/**
		 * 删除已经不存在的部门
		 */
		if ($next == 5) {
			/*$existDepts = $_SESSION['existDepts'];
		if (count($existDepts) > 0) {
		$this->deleteDeptFromQy($mpid, $existDepts, $warning);

		return new \ResponseData(array('param' => array('next' => 6, 'desc' => '删除已经不存在的部门')));
		}*/
		}
		/**
		 * 清理数据
		 */
		$existDepts = $_SESSION['existDepts'];
		$dCounter = $_SESSION['dCounter'];
		$existUsers = $_SESSION['existUsers'];
		die('cccc:' . count($existUsers));
		$uCounter = isset($_SESSION['uCounter']) ? $_SESSION['uCounter'] : 0;
		$localDepts = $_SESSION['localDepts'];
		$warning = $_SESSION['warning'];
		unset($_SESSION['dCounter']);
		unset($_SESSION['uCounter']);
		unset($_SESSION['existDepts']);
		unset($_SESSION['localDepts']);
		unset($_SESSION['existUsers']);
		unset($_SESSION['uploadUsers']);
		/**
		 * 更新时间戳
		 */
		$this->model()->update(
			'xxt_member_authapi',
			array('sync_to_qy_at' => $timestamp),
			"authid=$authid"
		);

		return new \ResponseData(array($dCounter, $existDepts, $uCounter, $existUsers, $warning));
	}
	/**
	 * 将内部组织结构数据增量导入到企业号通讯录
	 *
	 * $mpid
	 * $authid
	 */
	public function sync2Qy_action($mpid, $authid) {
		/**
		 * 更新时间戳
		 */
		$timestamp = time();

		$last = (int) $this->model()->query_val_ss(array(
			'sync_to_qy_at',
			'xxt_member_authapi',
			"authid=$authid",
		));

		$result = array();

		$proxy = $this->model('mpproxy/qy', $mpid);
		$modelOrg = $this->model('cus/org');
		$logs = $modelOrg->getOperationHistorysByTime($last);

		foreach ($logs as $log) {
			switch ($log['operation']) {
			case '1': // 新建用户
				$parentNode = $modelOrg->getNodeByGUID($log['parentguid']);
				$mobile = empty($log['mobile']) ? '151' . rand(1000, 9999) . '0000' : $log['mobile'];
				$user = array(
					'guid' => $log['guid'],
					'useraccount' => $log['useraccount'],
					'name' => $log['title'],
					'mobile' => $mobile,
					'department' => array($parentNode['id']),
				);
				$rst = $proxy->userCreate($user['useraccount'], $user);
				$result[] = array($log, $rst);
				break;
			case '2': // 修改用户
			case '4': // 迁移用户（阴影部分的parentguid是您唯一需要修改的数据，用户迁移只修改了它的父节点）
				$parentNode = $modelOrg->getNodeByGUID($log['parentguid']);
				$mobile = empty($log['mobile']) ? '151' . rand(1000, 9999) . '0000' : $log['mobile'];
				$user = array(
					'guid' => $log['guid'],
					'useraccount' => $log['useraccount'],
					'name' => $log['title'],
					'mobile' => $mobile,
					'department' => array($parentNode['id']),
				);
				$rst = $proxy->userUpdate($user['useraccount'], $user);
				$result[] = array($log, $rst);
				break;
			case '3': // 删除用户
				$rst = $proxy->userDelete($log['useraccount']);
				$result[] = array($log, $rst);
				break;
			case '5': // 新建组织
				$deptNode = $modelOrg->getNodeByGUID($log['guid']);
				$parentNode = $modelOrg->getNodeByGUID($log['parentguid']);
				$dept = array(
					'id' => $deptNode['id'],
					'title' => $deptNode['title'],
					'pid' => array($parentNode['id']),
					'order' => $deptNode['orderid'],
				);
				$rst = $proxy->departmentCreate($dept['title'], $dept['pid'], $dept['order'], $dept['id']);
				$result[] = array($log, $rst);
				break;
			case '6': // 更新组织
				$deptNode = $modelOrg->getNodeByGUID($log['guid']);
				$rst = $proxy->departmentUpdate($deptNode['id'], $deptNode['title']);
				$result[] = array($log, $rst);
				break;
			case '7': // 删除组织
				$deptNode = $modelOrg->getNodeByGUID($log['guid']);
				$rst = $proxy->departmentDelete($deptNode['id']);
				$result[] = array($log, $rst);
				break;
			case '8': // 虚拟组织添加子节点
				break;
			case '9': // 虚拟组织移除子节点
				break;
			case '10': // 给员工添加岗位
				break;
			case '0': // 新建虚拟根节点
				break;
			case '-1': // 删除虚拟根节点
				break;
			case '-2': // 修改虚拟根节点
				break;
			case '-3': // 应用程序用户规则调整
				break;
			case '-4': // 虚拟角色组织更新
				break;
			}
		}
		/**
		 * 更新时间戳
		 */
		$this->model()->update(
			'xxt_member_authapi',
			array('sync_to_qy_at' => $timestamp),
			"authid=$authid"
		);

		return new \ResponseData($result);
	}
	/**
	 * 将企业号数据增量导入到企业内部组织结构
	 *
	 * $mpid
	 * $authid
	 */
	public function syncFromQy_action($mpid, $authid) {
		return new \ResponseError('not support');
	}
	/**
	 * 返回组织机构组件
	 */
	public function memberSelector_action($authid) {
		$addon = array(
			'js' => '/views/default/cus/crccre/member/memberSelector.js',
			'view' => "/rest/cus/crccre/member/auth/organization?authid=$authid",
		);
		return new \ResponseData($addon);
	}
	/**
	 *
	 */
	public function organization_action($authid) {
		$this->view_action('/cus/crccre/member/memberSelector');
	}
}
