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
        $skey = $this -> cut('skey=',';',$this -> Cookies);
        $this -> pskey = $this -> cut('p_skey=',';',$this -> Cookies);
        $this -> token = $rt['data']['bkn'];
        $this -> HostUin = json_decode($this -> curl($apiaddr."/get_login_info?access_token=$actk"),1)['data']['user_id'];
        return 1;
    }

    public function publish ($Content, $RichType = null, $Richval = null) {
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
            'ugc_right' => 1, //权限 1为所有人可见 4为好友可见 64为仅自己可见
            'to_sign' => 0,
            'hostuin' => $this -> HostUin, 
            'code_version' => 1,
            'format' => 'json', //居然可以让它直接返回json！
            'qzreferrer' => "https%3A%2F%2Fuser.qzone.qq.com%2F{$this -> HostUin}%2Fmain"
        );
        $result = $this -> post('/emotion_cgi_publish_v6', $data);
        /*$result = '(' . $this -> cut("frameElement.callback(","</script>",$result);
        $arr = json_decode($this -> cut("(",");",$result),1);*/
        $arr = json_decode($result,1);
        if($arr['subcode'] != 0) return $arr;
        return $arr['t1_tid'];
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
            * 本文件中所有QQ空间相关操作均为POST方式
            * Path: /cgi-bin之后的内容 以/开头
            * Type: 默认为user:发布说说、删除说说、发表评论
                    upload：上传图片
            * Params array形式
        */
        if ($Type == 'user') $url = 'https://user.qzone.qq.com/proxy/domain/taotao.qzone.qq.com/cgi-bin'.$Path.'?g_tk='.$this -> token;
        elseif ($Type == 'upload') $url = 'https://up.qzone.qq.com/cgi-bin'.$Path.'?g_tk='.$this -> token;
        else return array('code' => 0,'msg' => 'Invalid Type');
        $postdata = '';
        foreach ($Params as $key => $value) $postdata .= "$key=".urlencode($value)."&";
        $postdata = rtrim($postdata, '&');
        $result = $this -> curl($url, $postdata);
        return $result;
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

    private function compressImage($sourceimg, $quality = 56) {
        if (!function_exists('gd_info')) return 'error!! GD required';
        $image = imagecreatefromstring($sourceimg);
        ob_start();
        imagewebp($image, null, $quality);
        return ob_get_clean();
    }
}
?>