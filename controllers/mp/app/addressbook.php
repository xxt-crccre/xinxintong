<?php
namespace mp\app;

require_once dirname(__FILE__).'/base.php';
/**
 *
 */
class addressbook extends app_base {
    /**
     *
     */
    protected function getMatterType()
    {
        return 'addressbook';
    }
    /**
     *
     */
    public function index_action() 
    {
        $this->view_action('/mp/app/addressbook');
    }
    /**
     *
     */
    public function get_action($abid=null)
    {
        if (empty($abid)) {
            $abs = $this->model('matter\addressbook')->byMpid($this->mpid);
            return new \ResponseData($abs);
        } else {
            $ab = $this->model('matter\addressbook')->byId($abid);
            /**
             * acl
             */
            $ab->acl = $this->model('acl')->byMatter($this->mpid, 'addressbook', $abid);

            return new \ResponseData($ab);
        }
    }
    /**
     * 创建通讯录
     */
    public function create_action($title='新通讯录') 
    {
        $uid = \TMS_CLIENT::get_client_uid();

        $abid = $this->model('matter\addressbook')->insert_ab($this->mpid, $uid, $title);

        return new \ResponseData($abid);
    }
    /**
     * 删除通讯录
     */
    public function remove_action($id)
    {
        $rst = $this->model('matter\addressbook')->remove_ab($this->mpid, $id);

        if ($rst[0])
            return new \ResponseData('success');
        else
            return new \ResponseError($rst[1]);
    }
    /**
     * 更新通讯录基本设置
     *
     * $nv pair of name and value
     */
    public function update_action($abid) 
    {
        $nv = (array)$this->getPostJson();

        $nv['modify_at'] = time();

        isset($nv['pic']) && $nv['pic'] = $this->model()->escape($nv['pic']);

        $rst = $this->model()->update(
            'xxt_addressbook', 
            (array)$nv,
            "mpid='$this->mpid' and id='$abid'"
        );

        return new \ResponseData($rst);
    }
    /**
     * 获得部门列表
     */
    public function dept_action($abid, $pid=0) 
    {
        $q = array(
            'id,name',
            'xxt_ab_dept',
            "mpid='$this->mpid' and ab_id=$abid and pid=$pid"
        );

        $q2 = array('o'=>'seq');

        $depts = $this->model()->query_objs_ss($q, $q2);

        return new \ResponseData($depts);
    }
    /**
     * 添加部门
     *
     * $pid
     * $seq 如果没有指定位置，就插入到最后。序号从1开始。
     */
    public function addDept_action($abid, $pid=0, $seq=null)
    {
        $dept = $this->model('matter\addressbook')->addDept($this->mpid, $abid, '新部门', $pid, $seq);

        return new \ResponseData($dept);
    }
    /**
     * 更新部门信息
     *
     * $id
     */
    public function updateDept_action($id)
    {
        $nv = $this->getPostJson();

        $rst = $this->model()->update(
            'xxt_ab_dept', 
            (array)$nv, 
            "mpid='$this->mpid' and id=$id"
        );

        return new \ResponseData($rst);
    }
    /**
     * 删除部门
     *
     * 如果存在子部门不允许删除
     * 如果存在部门成员不允许删除
     */
    public function delDept_action($id)
    {
        $rst = $this->model('matter\addressbook')->delDept($this->mpid, $id);

        if ($rst[0] === false)
            return new \ResponseError($rst[1]);
        else
            return new \ResponseData(true);
    }
    /**
     * 设置部门的父部门
     */
    public function setDeptParent_action($id, $pid)
    {
        $rst = $this->model()->update(
            'xxt_ab_dept', 
            array('pid'=>$pid), 
            "mpid='$this->mpid' and id=$id"
        );

        return new \ResponseData($rst);
    }
    /**
     * 获得联系人信息（列表/详细）
     *
     * $id
     * $abbr
     * $page
     * $size
     */
    public function person_action($abid, $id=null, $abbr='', $page=1, $size=30) 
    {
        $model = $this->model('matter\addressbook');

        if (empty($id)) {
            $offset = ($page-1) * $size;
            $dept_id = null;

            $persons = $model->getPersonByAb($this->mpid, $abid, $abbr, $dept_id, $offset, $size);

            return new \ResponseData($persons);
        } else {
            $person = $model->getPersonById($id);
            $person->depts = $model->getDeptByPerson($id);

            return new \ResponseData($person);
        }
    }
    /**
     * 创建新联系人
     */
    public function personCreate_action($abid) 
    {
        $model = $this->model('matter\addressbook');
        $name = '新联系人';

        $id = $model->createPerson($this->mpid, $abid, $name);

        $person = $model->getPersonById($id);

        return new \ResponseData($person);
    }
    /**
     * 更新属性信息
     */
    public function personUpdate_action($id) 
    {
        $u = $this->getPostJson();

        if (isset($u->name))
            $u->pinyin = pinyin($u->name, 'UTF-8');

        $rst = $this->model()->update(
            'xxt_ab_person', 
            (array)$u, 
            "mpid='$this->mpid' and id='$id'"
        );

        return new \ResponseData($rst);
    }
    /**
     * 更新联系人所属的部门
     *
     * $id person's id.
     */
    public function updPersonDept_action($abid, $id) 
    {
        $deptids = $this->getPostJson();
        $rels = array();
        foreach ($deptids as $deptid) {
            $r = array(
                'dept_id' => $deptid
            );
            $r['id'] = $this->model('matter\addressbook')->addPersonDept($this->mpid, $abid, $id, $deptid);
            $rels[] = $r;
        }

        return new \ResponseData($rels);
    }
    /**
     * 删除联系人和部门之间的关联
     */
    public function delPersonDept_action($id, $deptid) 
    {
        /**
         * 删除关联
         */
        $rst = $this->model()->delete(
            'xxt_ab_person_dept', 
            "mpid='$this->mpid' and person_id=$id and dept_id=$deptid"
        );

        return new \ResponseData($rst);
    }
    /**
     * 删除通讯录中的一个联系人
     */
    public function personDelete_action($id) 
    {
        /**
         * remove relation with dept.
         */
        $this->model()->delete(
            'xxt_ab_person_dept',
            "mpid='$this->mpid' and person_id=$id"
        );
        /**
         * remove person.
         */
        $rst = $this->model()->delete(
            'xxt_ab_person', 
            "mpid='$this->mpid' and id=$id"
        );

        return new \ResponseData($rst);
    }

    /**
     * import an address book(cvs,utf-8).
     *
     * support fields:name(1),email(1),tel(n),dept(n)
     *
     */
    public function import_action($abid, $cleanExistent='N') 
    {
        if ($cleanExistent === 'Y') {
            $this->model()->delete('xxt_ab_person_dept', "mpid='$this->mpid' and ab_id=$abid");
            $this->model()->delete('xxt_ab_person', "mpid='$this->mpid' and ab_id=$abid");
            $this->model()->delete('xxt_ab_dept', "mpid='$this->mpid' and ab_id=$abid");
            $this->model()->delete('xxt_ab_title', "mpid='$this->mpid' and ab_id=$abid");
        }
        //solving: Maximum execution time of 30 seconds exceeded
        @set_time_limit(0);

        if (!($file = fopen($_FILES['addressbook']['tmp_name'], "r")))
            return new \ResponseError('open file, failed.');

        $all_depts = $this->getDeptsByMp($this->mpid);

        $headers = fgetcsv($file);
        $first_header = $headers[0];
        $first_header = preg_replace('/\xEF\xBB\xBF/', '', $first_header); //remove BOM
        $headers[0] = $first_header;
        /**
         * handle data.
         */
        $model = $this->model('matter\addressbook');
        for ($row = 0; ($contact = fgetcsv($file)) != false; $row++) {
            $name = $email = '';
            $tels = array();
            $depts = array();
            $titles = array();
            foreach ($headers as $h=>$header) {
                switch ($header) {
                case 'name':
                    $name = trim($contact[$h]);
                    $name = preg_replace('/\s/', '', $name);
                    break;
                case 'email':
                    $email = trim($contact[$h]);
                    break;
                case 'tel':
                    $tel = trim($contact[$h]);
                    !empty($tel) && $tels[] = $tel;
                    break;
                case 'org':
                case 'dept':
                    $dept = trim($contact[$h]);
                    !empty($dept) && $depts[] = $dept;
                    break;
                }
            }
            /**
             * new person
             */
            $personId = $model->createPerson($this->mpid, $abid, $name, $email, implode($tels, ','), false);
            /**
             * depts
             */
            $dept_pid = 0;
            foreach ($depts as $sDept) {
                if (isset($all_depts[$sDept]))
                    $oDept = $all_depts[$sDept];
                else {
                    $oDept = $model->addDept($this->mpid, $abid, $sDept, $dept_pid);
                    $all_depts[$sDept] =  $oDept;
                }
                $model->addPersonDept($this->mpid, $abid, $personId, $oDept->id);
                $dept_pid = $oDept->id; 
            }
        }

        if (!feof($file)) {
            return new \ResponseError('unexpected fgets() fail.');
        }
        fclose($file);

        return new \ResponseData($row);
    }
    /**
     *
     */
    private function getDeptsByMp($mpid) 
    {
        $selected = array();
        $q[] = 'id,name';
        $q[] = 'xxt_ab_dept';
        $q[] = "mpid='$mpid'";
        if ($depts = $this->model()->query_objs_ss($q)) {
            foreach ($depts as $oDept) {
                $selected[$oDept->name] = $oDept;
            }
        }
        return $selected;
    } 
}
