<?php
include_once dirname(dirname(dirname(__FILE__))).'/inmp.vw.php';

$view['params']['menu'] = '/rest/mp/app';

$view['params']['global_js'] = array('tinymce/tinymce.min','matters-xxt');
$view['params']['angular-modules'] = "'channel.matter.mp','matters.xxt','ui.bootstrap'";
$view['params']['css'] = array(array('/mp/app/enroll','detail'));
$view['params']['js'] = array(array('/mp','channel'), array('/mp/app/enroll','detail'));
$view['params']['layout-body'] = '/mp/app/enroll/detail';
