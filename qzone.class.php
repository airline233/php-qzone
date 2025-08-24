<?php
class qzone {
    /*
        全局变量说明:
            HostUin 登录QQ空间的QQ号
            Cookies 登录QQ空间的Cookies 直接调用init()函数可直接设置（调用NapCatApi）
    */
    public $HostUin;
    public $Cookies;
    private $skey;
    private $pskey;
    private $token;

    public function __construct ($apiaddr, $actk=null) {
        /*
            * 自动获取QQ空间Cookies
            * 错误时自动PRINT HTTP状态码
            * apiaddr: NapCatApi地址 如http://172.16.0.1:5000
            * actk: NapCatApi的token
        */
        $rt = $this -> curl($apiaddr."/get_cookies?access_token=$actk",'domain=qzone.qq.com');
        if(is_numeric($rt)) print $rt;
        $rt = json_decode($rt, true);
        if($rt['status'] != 'ok') print $rt;
        $this -> Cookies = $rt['data']['cookies'];
        $ckarr = explode(';',$this -> Cookies);
        foreach($ckarr as $single) {
            $single_arr = explode('=',str_replace(" ","",$single));
            $ckarr[$single_arr[0]] = $single_arr[1];
        }
        $this -> skey = $ckarr['skey'];
        $this -> pskey = $ckarr['p_skey'];
        $this -> token = $rt['data']['bkn'];
        $this -> HostUin = json_decode($this -> curl($apiaddr."/get_login_info?access_token=$actk"),1)['data']['user_id'];
        return 1;
    }

    public function publish ($Content, $RichType = null, $Richval = null, $setTime = null, $ugcRight = 1, $allowUins = null) {
        /*
            Content: 发布的文本内容
            RichType: 
                    null: 普通说说
                    1: 带图说说
                    视频说说暂不支持
            Richval: 带图说说时的图片信息
                    e.g.:,albumid,lloc,sloc,type,height,width,,height,width
                    调用Upload函数会给出Richval
            注意：RichType和Richval必须同时存在或同时不存在
            setTime: 定时发布时传入 10位unix时间戳格式 精确到秒
            ugcRight: 说说查看权限
                    1为所有人可见 4为好友可见
                    16为部分好友可见（通过allow_uins传入qq号）
                    64为仅自己可见
                    128为部分好友不可见 qq号传入规则同16
            allowUins: 权限限制时传入 多个qq用|分隔
                    e.g. 10000 或 10001|10002|...|10005
            返回：说说Tid，修改/删除/评论用；失败则返回原始array
        */
        $data = array(
            'syn_tweet_verson' => 1,
            'paramstr' => 1,
            'pic_template' => null,
            'richtype' => $RichType,
            'richval' => $Richval,
            'special_url' => null,
            'subrichtype' => null,
            'con' => $Content,
            'feedversion' => '1&ver=1', //这俩应该都不动 写一起了 实际上是两个参数
            'ugc_right' => $ugcRight, //权限 1为所有人可见 4为好友可见 64为仅自己可见
            'allow_uins' => $allowUins,
            'who' => (empty($allowUins)) ? null : 1,
            'to_sign' => 0, //同步至个签
            'time' => $setTime,
            'hostuin' => $this -> HostUin, 
            'code_version' => 1,
            'format' => 'json', //居然可以让它直接返回json！
            'qzreferrer' => "https%3A%2F%2Fuser.qzone.qq.com%2F{$this -> HostUin}%2Fmain"
        );
        $ist = (isset($setTime)) ? 'timershuoshuo_' : '';
        $result = $this -> post('/emotion_cgi_publish_'.$ist.'v6', $data);
        $arr = json_decode($result,1);
        if($arr['subcode'] != 0) return $arr;
        return $arr['t1_tid'] ?? $arr['tid'];
    }

    public function upload ($File, $Type = 'base64', $rtType = 'Richval') {
        /*
            * 上传图片
            * File: 图片文件路径/Base64编码字符串/URL
            * Type: 默认为base64，表示File为Base64编码字符串
                    url：表示File为图片URL
                    file：表示File为图片文件路径
            * rtType：返回值 默认为Richval字符串 e.g.: ,albumid,lloc,sloc,type,height,width,,height,width
                    也可以传入‘url’，用于评论
        */
        switch ($Type) {
            case 'file':
                $image = base64_encode(file_get_contents($File));
                break;
            case 'base64':
                $image = $File;
                break;
            case 'url':
                $image = $this -> curl($File);
                if (is_numeric($image)) return array('code' => 0,'msg' => 'Req error Httpcode:'.$image); // 返回HTTP状态码
                $image = base64_encode($image);
                break;
            default:
                return array('code' => 0,'msg' => 'Invalid Type');
        }

        if(strlen(base64_decode($image)) > 1024 * 1024 * 3)  //>3MB
            $image = $this -> compressImage(base64_decode($image));

        $data = array(
            'filename' => 'filename',
            'uin' => $this -> HostUin,
            'skey' => $this -> skey,
            'zzpaneluin' => $this -> HostUin,
            //'zzpanelkey' => null,
            'p_uin' => $this -> HostUin,
            'p_skey' => $this -> pskey,
            //qzonetoken => null,
            'uploadtype' => 1,
            'albumtype' => 7,
            'exttype' => 0,
            'refer' => 'shuoshuo',
            'output_type' => 'jsonhtml', //这边改成json行不通...
            'charset' => 'utf-8',
            'output_charset' => 'utf-8',
            'upload_hd' => 1,
            'hd_width' => 2048,
            'hd_height' => 10000,
            'hd_quality' => 96,
            'backUrls' => 'http://upbak.photo.qzone.qq.com%2Fcgi-bin%2Fupload%2Fcgi_upload_image%2Chttp%3A%2F%2F119.147.64.75%2Fcgi-bin%2Fupload%2Fcgi_upload_image&url=https%3A%2F%2Fup.qzone.qq.com%2Fcgi-bin%2Fupload%2Fcgi_upload_image%3Fg_tk%3D'.$this -> token,
            'base64' => 1,
            'jsonhtml_callback' => 'callback',
            'picfile' => $image,
            'qzreferrer' => 'https%3A%2F%2Fuser.qzone.qq.com%2F'.$this -> HostUin.'%2Fmain'
        );
        $Path = '/upload/cgi_upload_image';
        $result = $this -> post($Path, $data, 'upload');
        if (is_numeric($result)) return array('code' => 0,'msg' => 'Req error Httpcode:'.$result); // 返回HTTP状态码
        $result = $this -> cut("frameElement.callback","</script>",$result);
        $arr = json_decode($this -> cut("(",")",$result),1);
        switch($rtType) {
            case 'url':
                $url = $arr['data']['url'];
                return $url;
                break;
            case 'Richval':
                $albumid = $arr['data']['albumid'];
                $lloc = $arr['data']['lloc'];
                $sloc = $lloc; //这俩似乎是一个东西
                $type = $arr['data']['type'];
                $height = $arr['data']['height'];
                $width = $arr['data']['width'];
                return ",$albumid,$lloc,$sloc,$type,$height,$width,,$height,$width";
                break;
            dafault:
                return 'Invalid rtType';
                break;
        }
    }

    public function upvideo ($File, $desc = '') {
        /*
            * 上传并发布视频
            * File: 视频路径
            * desc: 视频描述（说说的文本内容）
        */
        $albuminfo = $this -> curl("https://user.qzone.qq.com/proxy/domain/photo.qzone.qq.com/fcgi-bin/fcg_list_album_v3?g_tk={$this -> token}&callback=shine0_Callback&t=975885809&hostUin={$this -> HostUin}&uin={$this -> HostUin}&appid=4&inCharset=utf-8&outCharset=utf-8&source=qzone&plat=qzone&format=json&notice=0&filter=1&handset=4&pageNumModeSort=40&pageNumModeClass=15&needUserInfo=1&idcNum=4&callbackFun=shine0");
        if(is_numeric($albuminfo)) return "Get Album Error!!";
        /*$albumdata = json_decode($albuminfo,1)['data'];
        if(!isset($albumdata)) $albumid = json_decode($this -> curl("https://user.qzone.qq.com/proxy/domain/photo.qzone.qq.com/cgi-bin/common/cgi_add_album_v2","inCharset=utf-8&outCharset=utf-8&hostUin={$this -> HostUin}&notice=0&callbackFun=_Callback&format=fs&plat=qzone&source=qzone&appid=4&uin={$this -> HostUin}&album_type=&birth_time=&degree_type=0&enroll_time=&albumname=Video&albumdesc=&albumclass=100&priv=1&question=&answer=&whiteList=&bitmap=10000000&qzreferrer=https%3A%2F%2Fuser.qzone.qq.com%2F{$this -> HostUin}%2Fmain"),1)['data']['album']['id']; //没有相册就创建相册 名称默认Video
        else $albumid = $albumdata['albumListModeSort'][0]['id']; //有相册就默认取第一个
        $albumname = $albumdata['albumListModeSort'][0]['name'] ?? 'Video';*/
        //默认相册几乎都是仅自己可见，所以这边统一单独创建一个名为Video的相册
        foreach (json_decode($albuminfo,1)['data']['albumListModeSort'] as $arr) if($arr['name'] == 'Video') $albumid = $arr['id']; 
        if(!isset($albumid)) $albumid = json_decode($this -> curl("https://user.qzone.qq.com/proxy/domain/photo.qzone.qq.com/cgi-bin/common/cgi_add_album_v2?g_tk={$this -> token}","inCharset=utf-8&outCharset=utf-8&hostUin={$this -> HostUin}&notice=0&callbackFun=_Callback&format=json&plat=qzone&source=qzone&appid=4&uin={$this -> HostUin}&album_type=&birth_time=&degree_type=0&enroll_time=&albumname=Video&albumdesc=&albumclass=100&priv=1&question=&answer=&whiteList=&bitmap=10000000&qzreferrer=https%3A%2F%2Fuser.qzone.qq.com%2F{$this -> HostUin}%2Fmain"),1)['data']['album']['id']; //创建一个所有人可见 名为Video的相册
        $albumname = 'Video';
        require_once('getid3/getid3.php');
        $binary = file_get_contents($File);
        $len = strlen($binary);
        $sha1 = sha1($binary);
        $getid3 = new getID3();
        $videotime = round($getid3 -> analyze($File)['playtime_seconds'] * 1000,2); //精确到0.01毫秒
        $time = time();
        $Params = "{\"control_req\":[{\"uin\":\"{$this -> HostUin}\",\"token\":{\"type\":4,\"data\":\"{$this -> pskey}\",\"appid\":5},\"appid\":\"video_qzone\",\"checksum\":\"{$sha1}\",\"check_type\":1,\"file_len\":{$len},\"env\":{\"refer\":\"qzone\",\"deviceInfo\":\"h5\"},\"model\":0,\"biz_req\":{\"sPicTitle\":\"upload.mp4\",\"sPicDesc\":\"\",\"sAlbumName\":\"\",\"sAlbumID\":\"\",\"iAlbumTypeID\":0,\"iBitmap\":0,\"iUploadType\":3,\"iUpPicType\":0,\"iBatchID\":0,\"sPicPath\":\"\",\"iPicWidth\":0,\"iPicHight\":0,\"iWaterType\":0,\"iDistinctUse\":0,\"sTitle\":\"upload\",\"sDesc\":\"\",\"iFlag\":0,\"iUploadTime\":{$time},\"iPlayTime\":{$videotime},\"sCoverUrl\":\"\",\"iIsNew\":111,\"iIsOriginalVideo\":0,\"iIsFormatF20\":0,\"extend_info\":{\"video_type\":\"3\",\"domainid\":\"5\"}},\"session\":\"\",\"asy_upload\":0,\"cmd\":\"FileUploadVideo\"}]}";
        $response = $this -> curl("https://h5.qzone.qq.com/webapp/json/sliceUpload/FileBatchControl/{$sha1}?g_tk={$this -> token}",$Params, 1);
        $starttime = strtotime($response[0]['Date']);
        $rt_arr = json_decode($response[1],1);
        $session = $rt_arr['data']['session'];
        $slicesize = $rt_arr['data']['slice_size'];
        $num = ceil($len / $slicesize);

        for($i=0;$i<$num;$i++) {
            $offset = $i * $slicesize;
            if($i+1 < $num) $end= $i * $slicesize;
            else $end = $len;
            $url = "https://h5.qzone.qq.com/webapp/json/sliceUpload/FileUploadVideo?seq={$i}&retry=0&offset={$offset}&end={$end}&total={$len}&type=json&g_tk={$this -> token}";
            $base64 = base64_encode(substr($binary,$offset,$slicesize));
            $Params = "{\"uin\":\"{$this -> HostUin}\",\"appid\":\"video_qzone\",\"session\":\"{$session}\",\"offset\":{$offset},\"data\":\"{$base64}\",\"checksum\":\"\",\"check_type\":1,\"retry\":0,\"seq\":{$i},\"end\":{$end},\"cmd\":\"FileUploadVideo\",\"slice_size\":{$slicesize},\"biz_req\":{}}";
            $rt_arr = json_decode($this -> curl($url,$Params),1);
            if(isset($rt_arr['data']['biz']['sVid'])) $sVid = $rt_arr['data']['biz']['sVid']; //这边用flag判断会有问题 我也不知道为什么 懒得调试了
            if($rt_arr['ret'] != 0) return 'Upload Video File ERROR';
        } //分片上传视频

        $command = "ffmpeg -ss 00:00:01 -i $File -vframes 1 -f image2 {$File}.jpg";
        @shell_exec($command); //生成视频缩略图
        $binary = file_get_contents("{$File}.jpg");
        if(!$binary) return "Generate JPEG File ERROR(Maybe permission denied or lack FFmpeg";
        $len = strlen($binary);
        $md5 = md5($binary); //卧槽傻逼一个字段有两种校验方式
        $microtime = str_replace('.','',microtime(1));
        $params = <<<json
{"control_req":[{"uin":"{$this->HostUin}","token":{"type":4,"data":"{$this -> pskey}","appid":5},"appid":"pic_qzone","checksum":"{$md5}","check_type":0,"file_len":{$len},"env":{"refer":"huodong","deviceInfo":"h5"},"model":0,"biz_req":{"sPicTitle":"upload","sPicDesc":"","sAlbumName":"{$albumname}","sAlbumID":"{$albumid}","iAlbumTypeID":0,"iBitmap":0,"iUploadType":2,"iUpPicType":0,"iBatchID":{$microtime},"sPicPath":"","iPicWidth":0,"iPicHight":0,"iWaterType":0,"iDistinctUse":0,"mutliPicInfo":{"iBatUploadNum":1,"iCurUpload":0,"iSuccNum":0,"iFailNum":0},"iNeedFeeds":1,"iUploadTime":{$starttime},"stExtendInfo":{"mapParams":{"vid":"{$sVid}","photo_num":"undefined","video_num":"undefined"}},"stExternalMapExt":{"is_client_upload_cover":"1","is_pic_video_mix_feeds":"1"},"mapExt":{},"sExif_CameraMaker":"","sExif_CameraModel":"","sExif_Time":"","sExif_LatitudeRef":"","sExif_Latitude":"","sExif_LongitudeRef":"","sExif_Longitude":""},"session":"","asy_upload":0}]}
json;
        $rt_arr = json_decode($this -> curl("https://h5.qzone.qq.com/webapp/json/sliceUpload/FileBatchControl/{$md5}?g_tk={$this -> token}",$params),1);
        $session = $rt_arr['data']['session'];
        $slicesize = $rt_arr['data']['slice_size'];
        $num = ceil($len / $slicesize);
        for($i=0;$i<$num;$i++) {
            $offset = $i * $slicesize;
            if($i+1 < $num) $end= $i * $slicesize;
            else $end = $len;
            $url = "https://h5.qzone.qq.com/webapp/json/sliceUpload/FileUpload?seq={$i}&retry=0&offset={$offset}&end={$end}&total={$len}&type=json&g_tk={$this -> token}";
            $base64 = base64_encode(substr($binary,$offset,$slicesize));
            $Params = <<<json
{"uin":"{$this -> HostUin}","appid":"pic_qzone","session":"{$session}","offset":{$offset},"data":"{$base64}","checksum":"","check_type":0,"retry":0,"seq":{$i},"end":{$end},"slice_size":{$slicesize},"biz_req":{"iUploadType":2}}
json;
            $rt_arr = json_decode($this -> curl($url,$Params),1);
            if(isset($rt_arr['data']['biz']['sPhotoID'])) $sPhotoID = $rt_arr['data']['biz']['sPhotoID'];
            if($rt_arr['ret'] != 0) return ['Upload JPEG File ERROR',$rt_arr, $url, $Params];
        } //分片上传视频缩略图 我也不知道傻逼腾讯为什么要分片
        @unlink("{$File}.jpg");
        
        if(isset($desc)) :
            $rt_arr = json_decode($this -> curl("https://user.qzone.qq.com/proxy/domain/photo.qzone.qq.com/cgi-bin/common/cgi_modify_multipic_v2?g_tk={$this -> token}","qzreferrer=https%3A%2F%2Fuser.qzone.qq.com%2F{$this -> HostUin}&inCharset=utf-8&outCharset=utf-8&hostUin={$this -> HostUin}&notice=0&callbackFun=_Callback&format=json&plat=qzone&source=qzone&appid=4&uin={$this -> HostUin}&albumId={$albumid}&nvip=1&pub=1&albumTitle={$albumname}&albumDesc=&picCount=2&priv=1&afterUpload=1&total=1&modifyType=1&type=010&name=&desc=test11&tag=&codeList={$sPhotoID}?010??{$desc}??"),1);
            if($rt_arr == 1) return 1;
        endif;
        if(isset($sPhotoID)) return 1;
        return 0;
    }

    public function delete ($Tid) {
        /*
            * 删除说说
            * Tid: publish时返回的tid
            * 返回：array code:0/1 
        */
        $postdata = array(
            'hostuin' => $this -> HostUin,
            'tid' => $Tid,
            't1_source' => '1&code_version=1',
            'format' => 'json',
            'qzreferrer=https%3A%2F%2Fuser.qzone.qq.com%2F'. $this -> HostUin
        );
        $result = $this -> post('/emotion_cgi_delete_v6',$postdata);
        if (is_numeric($result)) return array('code' => 0,'msg' => 'Req error Httpcode:'.$result); // 请求失败的话返回HTTP状态码
        /*$result = '(' . $this -> cut("frameElement.callback(","</script>",$result);
        $arr = json_decode($this -> cut("(",");",$result),1);*/ 
        $arr = json_decode($result,1);
        if($arr['subcode'] == 0) return array('code' => 1); //成功时 subcode返回的是0，失败-200
        return array('code' => 0);
    }

    public function comment ($Tid, $Content, $RichType = null, $Richval = null) {
        /*
            * 评论说说（无论是自己的还是别人发的都可以用这个评论，传入Tid即可）
            * Tid: publish时返回的tid
            * Content: 评论内容
            * RichType和Richval不同于publish传入的，这里RichType=1时，Richval需要传入图片直链（通过upload的第三个参可以拿到）
            * 返回：array code:0/1 
        */
        $uin = $this -> HostUin;
        $postdata = array(
            "qzreferrer" => "https%3A%2F%2Fuser.qzone.qq.com%2F".$uin,
            "topicId" => "{$uin}_{$Tid}__1",
            "feedsType" => "100",
            "inCharset" => "utf-8",
            "outCharset" => "utf-8",
            "plat" => "qzone",
            "source" => "ic",
            "hostUin" => $uin,
            "isSignIn" => "",
            "platformid" => "50",
            "uin" => $uin,
            "format" => "json",
            "ref" => "feeds",
            "content" => $Content,
            "richval" => $Richval,
            "richtype" => $RichType,
            "private" => "0",
            "paramstr" => "1"
        );
        $result = $this -> post('/emotion_cgi_re_feeds',$postdata);
        if (is_numeric($result)) return array('code' => 0,'msg' => 'Req error Httpcode:'.$result); // 请求失败的话返回HTTP状态码
        /*$result = '(' . $this -> cut("frameElement.callback(","</script>",$result);
        $arr = json_decode($this -> cut("(",");",$result),1); 历史遗留 */
        $arr = json_decode($result,1);
        if($arr['subcode'] == 0) return array('code' => 1); //成功时 subcode返回的是0，失败-800（也有可能是其他的）
        return array('code' => 0,'msg' => $arr['message'],'subcode' => $arr['subcode']);
    }

    private function post ($Path, $Params, $Type = 'user') { 
        /*
            * 本文件中大部分QQ空间相关操作均为POST方式
            * Path: /cgi-bin之后的内容 以/开头
            * Type: 默认为user:发布说说、删除说说、发表评论
                    upload：上传图片
            * Params array形式
        */
        if ($Type == 'user') $url = 'https://user.qzone.qq.com/proxy/domain/taotao.qzone.qq.com/cgi-bin'.$Path.'?g_tk='.$this -> token;
        elseif ($Type == 'upload') $url = 'https://up.qzone.qq.com/cgi-bin'.$Path.'?g_tk='.$this -> token;
        else return array('code' => 0,'msg' => 'Invalid Type');
        $postdata = $Params;
        if(is_array($Params)) 
            http_build_query($Params); //更优雅地处理post数据
            //foreach ($Params as $key => $value) $postdata .= "$key=".urlencode($value)."&";
        $postdata = rtrim($postdata, '&');
        $result = $this -> curl($url, $postdata);
        return $result;
    }

    private function curl($url, $data=null, $reheader = false) {
        /*
            * 发送curl请求
            * url: 请求的URL
            * data: POST数据
            * 返回值: 请求结果内容或HTTP状态码
        */
        $ch = curl_init();
        $cu[CURLOPT_URL] = $url;
        $cu[CURLOPT_HEADER] = $reheader;
        $cu[CURLOPT_RETURNTRANSFER] = true;
        $cu[CURLOPT_FOLLOWLOCATION] = true;
        if($data):
          $cu[CURLOPT_POST] = true;
          $cu[CURLOPT_POSTFIELDS] = $data;
        endif;
        $cu[CURLOPT_HTTPHEADER] = array("Cookie: ".$this -> Cookies);
        if($this -> isJson($data)) $cu[CURLOPT_HTTPHEADER][] = "Content-Type: application/json";
        $cu[CURLOPT_SSL_VERIFYPEER] = false;
        $cu[CURLOPT_SSL_VERIFYHOST] = false;
        $cu[CURLOPT_USERAGENT] = "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:140.0) Gecko/20100101 Firefox/140.0";
        $cu[CURLOPT_TIMEOUT] = "10";
        curl_setopt_array($ch, $cu);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode != 200) {
          return $httpCode;
        }
        if ($reheader == 1):
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headerStr = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);

            // 6. 解析响应头字符串为数组
            $headerRows = explode("\r\n", trim($headerStr));
            $parsedHeaders = [];
            foreach ($headerRows as $row) {
                // 第一行是 HTTP 状态行，例如: HTTP/1.1 200 OK
                if (strpos(strtolower($row), 'http/') === 0) {
                    $parsedHeaders['status'] = $row;
                } else if (!empty($row)) {
                    $parts = explode(':', $row, 2);
                    if (count($parts) === 2) {
                        // 将其他头信息存为 key => value 格式
                        $parsedHeaders[trim($parts[0])] = trim($parts[1]);
                    }
                }
            }
            $response = [$parsedHeaders,$body];
        endif;
        curl_close($ch);
        return $response;
      }
    
      private function cut($begin,$end,$str){
        $b = mb_strpos($str,$begin) + mb_strlen($begin);
        $e = mb_strpos($str,$end) - $b;
        return mb_substr($str,$b,$e);
    }

    private function compressImage($sourceimg, $quality = 56) {
        if (!function_exists('gd_info')) return 'error!! GD required';
        $image = imagecreatefromstring($sourceimg);
        ob_start();
        imagewebp($image, null, $quality);
        return ob_get_clean();
    }

    private function isJson($string = '', $assoc = true){
        if(is_string($string)){
            $data = json_decode($string, $assoc);
            if(($data && is_object($data)) || (is_array($data) && !empty($data))){
                return true;
            }
        }
        return false;
    }
}
?>