<?php
require 'qzone.class.php';
$apiaddr = 'http://172.16.0.2:15000';
$token = 'al233';
$instance = new qzone($apiaddr, $token);
$imgfile = base64_encode(file_get_contents('test.jpg'));
echo $instance -> upload($imgfile);

?>