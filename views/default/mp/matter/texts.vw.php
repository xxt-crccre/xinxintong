<?php
include_once dirname(__FILE__).'/common.vw.php';

$view['params']['global_js'] = array('matters-xxt');
$view['params']['angular-modules'] = "'matters.xxt'";
$view['params']['js'] = array(array('/mp/matter','texts'));
$view['params']['msg_type'] = 'texts';
