<?php
include_once dirname(dirname(__FILE__)).'/wrap.vw.php';

$view['params']['angular-modules'] = "'ui.bootstrap','matters.xxt'";
$view['params']['global_js'] = array('matters-xxt');
$view['params']['js'] = array(array('/mp/user/send','main'));
$view['params']['subView'] = '/mp/user/send/main';
