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
    private $g_tk;

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
        $ckarr = array();
        foreach(explode(';', (string)$this -> Cookies) as $single) {
            $single = trim($single);
            if($single === '' || strpos($single, '=') === false) continue;
            $single_arr = explode('=', $single, 2);
            $key = trim($single_arr[0]);
            if($key === '') continue;
            $ckarr[$key] = $single_arr[1];
        }
        $this -> skey = $ckarr['skey'] ?? '';
        $this -> pskey = $ckarr['p_skey'] ?? '';
        
        $hash_val = 5381;
        $pskey_len = strlen($this -> pskey);
        for($i = 0; $i < $pskey_len; $i++) {
            $hash_val = (int)fmod(($hash_val * 33) + ord($this -> pskey[$i]), 2147483648);
        }
        $this -> g_tk = (string)$hash_val;

        $this -> HostUin = json_decode($this -> curl($apiaddr."/get_login_info?access_token=$actk"),1)['data']['user_id'];
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
            'feedversion' => '1',
            'ver' => '1', 
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

        if(strlen(base64_decode($image)) > 1024 * 1024 * 1)  //>1MB
            $image = $this -> compressImageBase64($image);

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
            'backUrls' => 'http://upbak.photo.qzone.qq.com%2Fcgi-bin%2Fupload%2Fcgi_upload_image%2Chttp%3A%2F%2F119.147.64.75%2Fcgi-bin%2Fupload%2Fcgi_upload_image&url=https%3A%2F%2Fup.qzone.qq.com%2Fcgi-bin%2Fupload%2Fcgi_upload_image%3Fg_tk%3D&g_tk='.$this -> g_tk,
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
            case 'Richval':
                $albumid = $arr['data']['albumid'];
                $lloc = $arr['data']['lloc'];
                $sloc = $lloc; //这俩似乎是一个东西
                $type = $arr['data']['type'];
                $height = $arr['data']['height'];
                $width = $arr['data']['width'];
                return ",$albumid,$lloc,$sloc,$type,$height,$width,,$height,$width";
            default:
                return 'Invalid rtType';
        }
    }

    public function upvideo ($File) {
        /*
            * 上传视频
            * File： 视频路径
            * 还未实现
        */
        require_once('getid3/getid3.php');
        $binary = file_get_contents($File);
        $len = strlen($binary);
        $num = ceil($len / 16384);
        $sha1 = sha1($binary);
        $getid3 = new getID3();
        $videotime = round($getid3 -> analyze($File)['playtime_seconds'] * 1000,2); //精确到0.01毫秒
        $time = time();
        $Params = "{\"control_req\":[{\"uin\":\"{$this -> HostUin}\",\"token\":{\"type\":4,\"data\":\"{$this -> pskey}\",\"appid\":5},\"appid\":\"video_qzone\",\"checksum\":\"{$sha1}\",\"check_type\":1,\"file_len\":{$len},\"env\":{\"refer\":\"qzone\",\"deviceInfo\":\"h5\"},\"model\":0,\"biz_req\":{\"sPicTitle\":\"upload.mp4\",\"sPicDesc\":\"\",\"sAlbumName\":\"\",\"sAlbumID\":\"\",\"iAlbumTypeID\":0,\"iBitmap\":0,\"iUploadType\":3,\"iUpPicType\":0,\"iBatchID\":0,\"sPicPath\":\"\",\"iPicWidth\":0,\"iPicHight\":0,\"iWaterType\":0,\"iDistinctUse\":0,\"sTitle\":\"upload\",\"sDesc\":\"\",\"iFlag\":0,\"iUploadTime\":{$time},\"iPlayTime\":{$videotime},\"sCoverUrl\":\"\",\"iIsNew\":111,\"iIsOriginalVideo\":0,\"iIsFormatF20\":0,\"extend_info\":{\"video_type\":\"3\",\"domainid\":\"5\"}},\"session\":\"\",\"asy_upload\":0,\"cmd\":\"FileUploadVideo\"}]}";
        $rt_arr = json_decode($this -> curl("https://h5.qzone.qq.com/webapp/json/sliceUpload/FileBatchControl/{$sha1}?g_tk={$this -> g_tk}",$Params),1);
        $session = $rt_arr['data']['session'];

        $rt_arr = [];
        for($i=0;$i<$num;$i++) {
            $offset = $i * 16384;
            if($i+1 < $num) $end= $i * 16384;
            else $end = $len;
            $url = "https://h5.qzone.qq.com/webapp/json/sliceUpload/FileUploadVideo?seq={$i}&retry=0&offset={$offset}&end={$end}&total=583937&type=json&g_tk={$this -> g_tk}";
            $base64 = base64_encode(substr($binary,$offset,16384));
            $Params = "{\"uin\":\"{$this -> HostUin}\",\"appid\":\"video_qzone\",\"session\":\"{$session}\",\"offset\":{$offset},\"data\":\"{$base64}\",\"checksum\":\"\",\"check_type\":1,\"retry\":0,\"seq\":0,\"end\":16384,\"cmd\":\"FileUploadVideo\",\"slice_size\":16384,\"biz_req\":{}}";
            $rt_arr[] = json_decode($this -> curl($url,$Params),1);
        }
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
            't1_source' => '1',
            'code_version' => '1',
            'format' => 'json',
            'qzreferrer=https%3A%2F%2Fuser.qzone.qq.com%2F'. $this -> HostUin
        );
        $result = $this -> post('/emotion_cgi_delete_v6',$postdata);
        if (is_numeric($result)) return array('code' => 0,'msg' => 'Req error Httpcode:'.$result); // 请求失败则返回HTTP状态码
        $arr = json_decode($result,1);
        if($arr['subcode'] == 0) return array('code' => 1); //成功时 subcode返回的是0，失败-200或其他
        return array('code' => 0, 'msg' => $result);
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
        $arr = json_decode($result,1);
        if($arr['subcode'] == 0) return array('code' => 1); //成功时 subcode返回的是0，失败-800或其他
        return array('code' => 0,'msg' => $arr['message'],'subcode' => $arr['subcode']);
    }

    public function updateRight ($Tid, $ugcRight, $allowUins = null) {
        /*
            * 修改已有说说的查看权限
            * Tid: 说说Tid
            * ugcRight: 目标权限
                    1为所有人可见 4为好友可见
                    16为部分好友可见（通过allow_uins传入qq号）
                    64为仅自己可见
                    128为部分好友不可见 qq号传入规则同16
            * allowUins: 权限限制时传入 多个qq用|分隔
            * uin: 目标空间QQ号，默认当前登录QQ
            返回：array code:0/1
        */
        if(!in_array($ugcRight, array(1, 4, 16, 64, 128), true))
            return array('code' => 0, 'msg' => 'Invalid ugcRight');

        $allowUins = trim((string)$allowUins);
        if($allowUins !== '') {
            $allowUinMap = array();
            foreach(explode('|', $allowUins) as $singleUin) {
                $singleUin = trim($singleUin);
                if($singleUin === '') continue;
                if(!preg_match('/^\d+$/', $singleUin))
                    return array('code' => 0, 'msg' => 'Invalid allowUins');
                $allowUinMap[$singleUin] = true;
            }
            $allowUins = implode('|', array_keys($allowUinMap));
        }

        if(in_array($ugcRight, array(16, 128), true) && empty($allowUins))
            return array('code' => 0, 'msg' => 'allowUins required when ugcRight is 16 or 128');
        if(!in_array($ugcRight, array(16, 128), true)) $allowUins = '';

        $detail = $this -> getEmotionDetail($Tid);
        if(isset($detail['stage'])) return $detail;
        $postdata = $this -> buildUpdatePayloadFromDetail($detail);
        if(isset($postdata['code']) && $postdata['code'] === 0) return $postdata;
        $postdata['ugc_right'] = $ugcRight;
        if(in_array($ugcRight, array(16, 128), true)) $postdata['allow_uins'] = $allowUins;
        $result = $this -> post('/emotion_cgi_update', $postdata);
        if (is_numeric($result)) return array('code' => 0,'msg' => 'Req error Httpcode:'.$result, 'stage' => 'emotion_cgi_update', 'httpCode' => (int)$result);

        $jsonBody = trim((string)$result);
        if(strpos($jsonBody, '_Callback(') !== false) {
            $jsonBody = $this -> cut("_Callback(", ");", $jsonBody);
        } elseif(strpos($jsonBody, 'frameElement.callback(') !== false) {
            $jsonBody = $this -> cut("frameElement.callback(", ");", $jsonBody);
        } elseif(strpos($jsonBody, 'Callback(') !== false) {
            $jsonBody = $this -> cut("Callback(", ");", $jsonBody);
        } elseif(strpos($jsonBody, 'frameElement.callback') !== false && strpos($jsonBody, '</script>') !== false) {
            $jsonBody = trim($this -> cut("frameElement.callback", "</script>", $jsonBody));
            if(strpos($jsonBody, '(') !== false && strpos($jsonBody, ')') !== false) {
                $jsonBody = $this -> cut("(", ")", $jsonBody);
            }
        }
        $arr = json_decode($jsonBody, 1);
        if(!is_array($arr)) return array('code' => 0, 'msg' => 'Invalid response', 'stage' => 'emotion_cgi_update');
        if(($arr['subcode'] ?? -1) == 0) return array('code' => 1, 'ugc_right' => $arr['ugc_right'] ?? $ugcRight);
        return array('code' => 0, 'msg' => $arr['message'] ?? $arr['msg'] ?? $result, 'subcode' => $arr['subcode'] ?? null);
    }

    public function setQzoneRight ($targetUin, $action) {
        /*
            * 设置QQ空间权限
            * targetUin: 目标QQ号
            * action: 目标状态
                    1为拉黑
                    2为解除
            返回：array code:0/1
        */
        $uin = $this -> HostUin;
        $postdata = array(
            'uin' => $uin,
            'act_uin' => $targetUin,
            'action' => $action,
            'fupdate' => 1,
            'qzreferrer=https%3A%2F%2Fuser.qzone.qq.com%2F'.$uin.'%2Fmain'
        );
        $http_result = $this -> post('/right/cgi_black_action_new', $postdata, 'userRight');
        if (is_numeric($http_result)) return array('code' => 0,'msg' => 'Req error Httpcode:'.$http_result); // 请求失败的话返回HTTP状态码
        $result = $this -> cut("frameElement.callback","</script>",$http_result);
        $arr = json_decode($result,1);
        if($arr['subcode'] == 0) return array('code' => 1); //成功时 subcode返回的是0，失败-100或其他
        return array('code' => 0,'msg' => $arr['message'] ?? $arr['msg'],'subcode' => $arr['subcode']);
    }

    private function post ($Path, $Params, $Type = 'user') {
        /*
            * 本文件中大部分QQ空间相关操作均为POST方式
            * Path: /cgi-bin之后的内容 以/开头
            * Type: 默认为user:发布说说、删除说说、发表评论、修改说说权限
                    userRight: 设置QQ空间权限
                    upload：上传图片
            * Params array形式
        */
        if ($Type == 'user') $url = 'https://user.qzone.qq.com/proxy/domain/taotao.qzone.qq.com/cgi-bin'.$Path.'?g_tk='.$this -> g_tk;
        elseif ($Type == 'userRight') $url = 'https://user.qzone.qq.com/proxy/domain/w.qzone.qq.com/cgi-bin'.$Path.'?g_tk='.$this -> g_tk;
        elseif ($Type == 'upload') $url = 'https://up.qzone.qq.com/cgi-bin'.$Path.'?g_tk='.$this -> g_tk;
        else return array('code' => 0,'msg' => 'Invalid Type');
        $postdata = '';
        if(is_array($Params)) $postdata = http_build_query($Params);
        //foreach ($Params as $key => $value) $postdata .= "$key=".urlencode($value)."&";
        else $postdata = $Params;
        $postdata = rtrim($postdata, '&');
        $result = $this -> curl($url, $postdata);
        return $result;
    }

    private function getEmotionDetail($Tid) {
        $uin = $this -> HostUin;
        $path = '/emotion_cgi_msgdetail_v6';
        $params = array(
            'tid' => $Tid,
            'uin' => $uin,
            't1_source' => 1,
            'not_trunc_con' => 1,
            'need_right' => 1,
            'not_adapt_outpic' => 1,
            'g_tk' => $this -> g_tk
        );
        $query = http_build_query($params);
        $url = 'https://h5.qzone.qq.com/proxy/domain/taotao.qq.com/cgi-bin' . $path . '?' . $query;
        $result = $this -> curl($url);
        if (is_numeric($result)) return array('code' => 0,'msg' => 'Req error Httpcode:'.$result, 'stage' => 'emotion_cgi_msgdetail_v6', 'httpCode' => (int)$result);
        $arr = json_decode($this -> cut("Callback(", ");", $result), true);
        if(!is_array($arr)) return array('code' => 0, 'msg' => 'Invalid detail response', 'stage' => 'emotion_cgi_msgdetail_v6');
        if(($arr['subcode'] ?? $arr['code'] ?? -1) != 0)
            return array('code' => 0, 'msg' => $arr['message'] ?? $arr['msg'] ?? 'Get detail failed', 'subcode' => $arr['subcode'] ?? null, 'stage' => 'emotion_cgi_msgdetail_v6');
        return $arr;
    }

    private function buildUpdatePayloadFromDetail($detail) {
        if(!is_array($detail) || !isset($detail['tid'])) return array('code' => 0, 'msg' => 'Detail missing tid');

        $pics = (isset($detail['pic']) && is_array($detail['pic'])) ? $detail['pic'] : array();
        $richtype = isset($detail['richtype']) ? (int)$detail['richtype'] : (empty($pics) ? 0 : 1);
        $picTemplate = (string)($detail['pic_template'] ?? '');
        $richvals = array();
        $picBoItems = array();

        foreach($pics as $pic) {
            $picId = (string)($pic['pic_id'] ?? '');
            if($picId === '') continue;

            $picIdParts = explode(',', $picId);
            $albumId = $picIdParts[1] ?? '';
            $lloc = $picIdParts[2] ?? '';
            if($albumId === '' || $lloc === '')
                return array('code' => 0, 'msg' => 'Unsupported pic detail');

            $sloc = $lloc;
            $picType = (string)($pic['pictype'] ?? $pic['type'] ?? 22);
            $height = (string)($pic['height'] ?? $pic['b_height'] ?? 0);
            $width = (string)($pic['width'] ?? $pic['b_width'] ?? 0);
            $richvals[] = ','.$albumId.','.$lloc.','.$sloc.','.$picType.','.$height.','.$width.',,0,0';

            $bo = '';
            foreach(array('smallurl', 'url1', 'url2', 'url3') as $urlKey) {
                if(empty($pic[$urlKey])) continue;
                if(preg_match('/(?:\\?|&)bo=([^&#]+)/', (string)$pic[$urlKey], $matches)) {
                    $bo = rawurldecode($matches[1]);
                    break;
                }
            }
            if($bo !== '') $picBoItems[] = $bo;
        }

        if($richtype === 1 && empty($richvals)) return array('code' => 0, 'msg' => 'Image post missing pic info');

        $content = (string)($detail['content'] ?? '');
        if(isset($detail['conlist']) && is_array($detail['conlist']) && !empty($detail['conlist'])) {
            $conParts = array();
            foreach($detail['conlist'] as $conItem) {
                if(isset($conItem['con'])) $conParts[] = (string)$conItem['con'];
            }
            if(!empty($conParts)) {
                $content = implode('', $conParts);
                if($content !== '' && strpos($content, "\n") !== 0) $content = "\n".$content;
            }
            $content = trim($content);
        }

        $subrichtype = $detail['t1_subtype'] ?? $detail['subrichtype'] ?? (empty($richvals) ? null : 1);
        $picBo = '';
        if(!empty($picBoItems)) {
            $boGroup = implode(',', $picBoItems);
            $picBo = $boGroup."\t".$boGroup;
        }

        $hostuin = (string)($detail['uin'] ?? $this -> HostUin);
        return array(
            'syn_tweet_verson' => 1,
            'tid' => (string)$detail['tid'],
            'paramstr' => 1,
            'pic_template' => $picTemplate,
            'richtype' => $richtype,
            'richval' => empty($richvals) ? (string)($detail['richval'] ?? '') : implode("\t", $richvals),
            'special_url' => (string)($detail['special_url'] ?? ''),
            'subrichtype' => $subrichtype,
            'pic_bo' => $picBo,
            'con' => $content,
            'feedversion' => (string)($detail['feedversion'] ?? 1),
            'ver' => (string)($detail['ver'] ?? 1),
            'ugc_right' => (int)($detail['ugc_right'] ?? 1),
            'to_sign' => (int)($detail['to_sign'] ?? 0),
            'ugcright_id' => (string)($detail['ugcright_id'] ?? $detail['tid']),
            'hostuin' => $hostuin,
            'code_version' => (string)($detail['code_version'] ?? 1),
            'format' => 'fs',
            'qzreferrer' => 'https://user.qzone.qq.com/'.$hostuin
        );
    }

    private function curl($url,$data=null) {
        /*
            * 发送curl请求
            * url: 请求的URL
            * data: POST数据
            * 返回值: 请求结果内容或HTTP状态码
        */
        $ch = curl_init();
        $cu[CURLOPT_URL] = $url;
        $cu[CURLOPT_HEADER] = false;
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
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode != 200) {
            return $httpCode;
        }
        curl_close($ch);
        return $content;
    }
    
    private function cut($begin,$end,$str){
        $b = mb_strpos($str,$begin) + mb_strlen($begin);
        $e = mb_strpos($str,$end) - $b;
        return mb_substr($str,$b,$e);
    }

    private function compressImageBase64($base64Input, $quality = 75) {
        // 1. 清理 Base64 头部，获取纯图像数据
        if (strpos($base64Input, ',') !== false) {
            $base64Input = substr($base64Input, strpos($base64Input, ',') + 1);
        }
        // 修复在 URL 传输中可能丢失的 '+'
        $base64Input = str_replace(' ', '+', $base64Input);
        $imageData = base64_decode($base64Input);

        if ($imageData === false) {
            // Base64 解码失败
            return null;
        }

        // 2. 从字符串创建图像资源 (GD 库会自动识别格式)
        $sourceImage = @imagecreatefromstring($imageData);
        if ($sourceImage === false) {
            // 无效的图像数据
            return null;
        }

        // 3. 获取原图尺寸
        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);

        // 4. 创建一个新的真彩色画布 (用于输出 JPEG)
        $outputImage = imagecreatetruecolor($width, $height);

        // 5. [关键] 处理透明度：为 PNG/GIF 创建白色背景
        // JPEG 不支持透明度，如果不填充背景，透明区域会变黑
        $white = imagecolorallocate($outputImage, 255, 255, 255);
        imagefill($outputImage, 0, 0, $white);

        // 6. 将原图（无论 PNG, JPEG, GIF...）复制到我们的白色背景画布上
        imagecopy($outputImage, $sourceImage, 0, 0, 0, 0, $width, $height);

        // 7. 使用输出缓冲捕获 imagejpeg 的输出
        ob_start();
        imagejpeg($outputImage, null, $quality);
        $compressedImageData = ob_get_contents();
        ob_end_clean();

        // 8. 释放内存
        imagedestroy($sourceImage);
        imagedestroy($outputImage);

        // 9. 编码为 Base64 并返回
        $base64Output = base64_encode($compressedImageData);
    
        return 'data:image/jpeg;base64,' . $base64Output;
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