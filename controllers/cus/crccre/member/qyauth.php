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
	 * 更新用户信息
	 */
	private function uploadUser2Qy($mpid, $user, &$uCounter, &$existUsers, &$warning) {
		$proxy = $this->model('mpproxy/qy', $mpid);

		if (!array_key_exists($user['useraccount'], $existUsers)) {
			/* 新用户 */
			$rst = $proxy->userCreate($user['useraccount'], $user);
			$uCounter++;
		} else {
			/* 更新已有用户 */
			$existUser = $existUsers[$user['useraccount']];
			unset($existUsers[$user['useraccount']]);
			if ($existUser->name !== $user['name'] || $existUser->mobile !== $user['mobile'] || count(array_diff($existUser->department, $user['department'])) > 0 || count(array_diff($user['department'], $existUser->department)) > 0) {
				$rst = $proxy->userUpdate($user['useraccount'], $user);
			}
		}
		if (isset($rst) && $rst[0] === false) {
			$w = new \stdClass;
			$w->{$user['guid']} = $rst[1];
			$w->user = $user;
			unset($w->user['guid']);
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
				if ($subDept->id == $deptId) {
					continue;
				}
				$rst = $this->deleteDeptAndSub($mpid, $subDept->id, $existDepts);
			}
			$rst = $proxy->departmentDelete($deptId);
			if ($rst[0] === false) {
				return $rst;
			} else {
				unset($existDepts[$deptId]);
				return array(true);
			}
		}
		return array(true);
	}
	/**
	 * 本地通讯录中的部门和其下的子部门同步到企业号通讯录
	 */
	private function deleteDeptFromQy($mpid, &$existDepts, $deptId, &$warning) {
		if ($deptId == 1) {
			unset($existDepts[$deptId]);
			return;
		}

		/* 删除部门成员 */
		$rst = $this->deleteDeptUser($mpid, $deptId);
		if ($rst[0] === false) {
			$warning[] = array($deptId, $rst[1]);
		}
		/* 删除子部门 */
		$rst = $this->deleteDeptAndSub($mpid, $deptId, $existDepts);
		if ($rst[0] === false) {
			$warning[] = array($deptId, $rst[1]);
		}

		return array(true);
	}
	/**
	 * 本地通讯录中的部门和其下的子部门同步到企业号通讯录
	 */
	private function uploadDept2Qy($mpid, $dept, &$dCounter, &$existDepts, &$warning) {
		$proxy = $this->model('mpproxy/qy', $mpid);

		if (!array_key_exists((int) $dept['id'], $existDepts)) {
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
						$warning[] = array($dept['guid'], $rst2[1]);
						return $rst2;
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
			$existDept = $existDepts[$dept['id']];
			unset($existDepts[$dept['id']]);
			/**
			 * 更新部门（名称或父节点）
			 */
			if ($existDept->name !== $dept['title'] || $existDept->parentid !== $dept['pid']) {
				$rst = $proxy->departmentUpdate($dept['id'], $dept['title'], $dept['pid']);
				if ($rst[0] === false) {
					/**
					 * 失败
					 */
					if (false !== strpos($rst[1], '60008')) {
						/**
						 * 部门名称已存在
						 */
						if ($existDept->name !== $dept['title'] . '_' . $dept['order'] || $existDept->parentid !== $dept['pid']) {
							$rst2 = $proxy->departmentUpdate($dept['id'], $dept['title'] . '_' . $dept['order'], $dept['pid']);
							if ($rst2[0] === false) {
								/**
								 * 无法跟新部门
								 */
								$warning[] = array($dept['guid'], $rst2[1]);
								return $rst2;
							}
						}
					} else {
						/**
						 * 无法更新部门
						 */
						$warning[] = array($dept['guid'], $rst[1]);
						return $rst;
					}
				}
				$dCounter++;
			}
		}

		return array(true);
	}
	/**
	 * 递归获得组织机构中的部门
	 */
	private function getLocalDepts($mpid, $dept, &$localDepts) {
		$localDepts[] = $dept;
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
			$child['pid'] = (int) $dept['id'];
			$this->getLocalDepts($mpid, $child, $localDepts);
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

			return new \ResponseData(array('param' => array('next' => 1, 'desc' => '完成获取企业号通讯录中已有部门（' . count($existDepts) . '个）')));
		}
		/**
		 * 更新部门数据
		 */
		if ($next == 1) {
			$localDepts = array();
			$localDeptsGuid2Id = array();
			$nodes = $this->model('cus/org')->nodes(); // 获得根部门
			foreach ($nodes as $order => $node) {
				$node['order'] = $order + 1;
				$node['pid'] = 1;
				$this->getLocalDepts($mpid, $node, $localDepts);
			}

			$_SESSION['localDepts'] = $localDepts;
			$_SESSION['localDeptsGuid2Id'] = $localDeptsGuid2Id;

			return new \ResponseData(array('param' => array('next' => 2, 'desc' => '完成获取组织机构中已有部门（' . count($localDepts) . '）')));
		}
		/**
		 * 更新部门数据
		 */
		if ($next == 2) {
			$warning = isset($_SESSION['warning']) ? $_SESSION['warning'] : array();
			$dCounter = isset($_SESSION['dCounter']) ? $_SESSION['dCounter'] : 0;
			$existDepts = $_SESSION['existDepts'];
			$localDepts = $_SESSION['localDepts'];
			$localDeptsGuid2Id = $_SESSION['localDeptsGuid2Id'];
			$counter = 0;
			foreach ($localDepts as $index => $ldept) {
				$this->uploadDept2Qy($mpid, $ldept, $dCounter, $existDepts, $warning);
				$localDeptsGuid2Id[$ldept['guid']] = $ldept['id'];
				unset($localDepts[$index]);
				$counter++;
				if ($counter === 100) {
					$_SESSION['existDepts'] = $existDepts;
					$_SESSION['dCounter'] = $dCounter;
					$_SESSION['localDepts'] = $localDepts;
					$_SESSION['localDeptsGuid2Id'] = $localDeptsGuid2Id;
					$_SESSION['warning'] = $warning;
					$step++;
					$param = array('param' => array('next' => 2, 'step' => $step, 'left' => ceil(count($localDepts) / 100), 'desc' => '批量同步部门数据'));
					return new \ResponseData($param);
				}
			}
			$_SESSION['existDepts'] = $existDepts;
			$_SESSION['dCounter'] = $dCounter;
			unset($_SESSION['localDepts']);
			$_SESSION['localDeptsGuid2Id'] = $localDeptsGuid2Id;
			$_SESSION['warning'] = $warning;
			$step++;

			return new \ResponseData(array('param' => array('next' => 3, 'desc' => '完成部门数据到企业号通讯录的同步')));
		}
		/**
		 * 获得企业号通讯录中已有的所有的用户
		 */
		if ($next == 3) {
			$uploadUsers = array();
			$rst = $this->model('mpproxy/qy', $mpid)->userList(1, 1);
			if ($rst[0] === false) {
				return new \ResponseError($rst[1]);
			}
			$existUsers = array();
			foreach ($rst[1]->userlist as $ruser) {
				$existUsers[$ruser->userid] = $ruser;
			}
			$_SESSION['existUsers'] = $existUsers;

			return new \ResponseData(array('param' => array('next' => 4, 'desc' => '获得企业号通讯录中所有用户（' . count($existUsers) . '）个')));
		}
		/**
		 * 获得本地用户数据
		 */
		if ($next == 4) {
			$localDeptsGuid2Id = $_SESSION['localDeptsGuid2Id'];
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
				if (isset($localDeptsGuid2Id[$node['parentid']])) {
					$user['department'][] = $localDeptsGuid2Id[$node['parentid']];
				}
				$uploadUsers[$node['useraccount']] = $user;
			}
			$_SESSION['uploadUsers'] = $uploadUsers;
			unset($_SESSION['localDeptsGuid2Id']);

			return new \ResponseData(array('param' => array('next' => 5, 'desc' => '获得所有本地用户数据（' . count($uploadUsers) . '）')));
		}
		/**
		 * 更新用户数据
		 */
		if ($next == 5) {
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
				if ($counter === 50) {
					$_SESSION['uploadUsers'] = $uploadUsers;
					$_SESSION['existUsers'] = $existUsers;
					$_SESSION['warning'] = $warning;
					$_SESSION['uCounter'] = $uCounter;
					$step++;
					$left = ceil(count($uploadUsers) / 50);
					$param = array('next' => 5, 'desc' => '分批同步用户数据', 'step' => $step, 'left' => $left);
					return new \ResponseData(array('param' => $param));
				}
				$user = next($uploadUsers);
			}
			$_SESSION['uploadUsers'] = $uploadUsers;
			$_SESSION['existUsers'] = $existUsers;
			$_SESSION['warning'] = $warning;
			$_SESSION['uCounter'] = $uCounter;

			return new \ResponseData(array('param' => array('next' => 6, 'desc' => '完成同步用户数据')));
		}
		/**
		 * 删除已经不存在的用户
		 */
		if ($next == 6) {
			/* 删除用户 */
			$existUsers = $_SESSION['existUsers'];
			if (!empty($existUsers)) {
				$counter = 0;
				foreach ($existUsers as $userid => $user) {
					$counter++;
					$rst = $this->model('mpproxy/qy', $mpid)->userDelete($userid);
					if ($rst[0] === false) {
						$w[$userid] = $rst[1];
					}
					unset($existUsers[$userid]);
					if ($counter === 5) {
						$_SESSION['existUsers'] = $existUsers;
						$step++;
						$left = ceil(count($existUsers) / 5);
						$param = array('next' => 6, 'desc' => '分批删除已经不存在的用户', 'step' => $step, 'left' => $left);
						return new \ResponseData(array('param' => $param));
					}
				}
				$_SESSION['existUsers'] = $existUsers;
			}
			return new \ResponseData(array('param' => array('next' => 7, 'desc' => '完成删除已经不存在的用户')));
		}
		/**
		 * 删除已经不存在的部门
		 */
		if ($next == 7) {
			$existDepts = $_SESSION['existDepts'];
			$warning = $_SESSION['warning'];
			if (count($existDepts) > 0) {
				$counter = 0;
				foreach ($existDepts as $deptId => $dept) {
					$this->deleteDeptFromQy($mpid, $existDepts, $deptId, $warning);
					$counter++;
					if ($counter === 5) {
						$_SESSION['existDepts'] = $existDepts;
						$_SESSION['warning'] = $warning;
						$step++;
						$left = ceil(count($existDepts) / 5);
						$param = array('next' => 7, 'desc' => '分批删除已经不存在的部门', 'step' => $step, 'left' => $left);
						return new \ResponseData(array('param' => $param));
					}
				}
				$_SESSION['existDepts'] = $existDepts;
				$_SESSION['warning'] = $warning;
			}
			return new \ResponseData(array('param' => array('next' => 8, 'desc' => '完成删除已经不存在的部门')));
		}
		/**
		 * 清理数据
		 */
		$existDepts = $_SESSION['existDepts'];
		$dCounter = $_SESSION['dCounter'];
		$existUsers = $_SESSION['existUsers'];
		$uCounter = isset($_SESSION['uCounter']) ? $_SESSION['uCounter'] : 0;
		$warning = $_SESSION['warning'];
		unset($_SESSION['dCounter']);
		unset($_SESSION['uCounter']);
		unset($_SESSION['existDepts']);
		unset($_SESSION['existUsers']);
		unset($_SESSION['uploadUsers']);
		unset($_SESSION['warning']);
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
