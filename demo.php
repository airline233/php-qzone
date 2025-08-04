<?php
require 'qzone.class.php';
$apiaddr = 'http://127.0.0.1:5000';
$token = 'your_token_here';
$instance = new qzone($apiaddr, $token);
$imgfile = base64_encode(file_get_contents('test.jpg'));
$richval = $instance -> upload($imgfile);
$tid = $instance -> publish('test11',1,$richval);
$instance = new qzone($apiaddr, $token);
$richval = $instance -> upload($imgfile,'base64','url');
print_r($instance -> comment($tid,'测试带图评论1',1,$richval));
print_r($instance -> comment($tid,'测试普通评论1'));
print_r($instance -> comment($tid,'测试普通评论2'));
print_r($instance -> delete($tid));
?>