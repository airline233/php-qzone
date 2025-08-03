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
            * 返回值: 0/1
            * apiaddr: NapCatApi地址 如http://172.16.0.1:5000
            * actk: NapCatApi的token
        */
        $rt = curl($apiaddr."/getcookies?access_token=$actk",'domain=qzone.qq.com');
        if(is_numeric($rt)) return 0;
        $rt = json_decode($rt, true);
        if($rt['status'] != 'ok') return 0;
        $this -> Cookies = $rt['data']['cookies'];
        $this -> skey = $this -> cut('skey=',';',$this -> Cookies);
        $this -> pskey = $this -> cut('p_skey=',';',$this -> Cookies);
        $this -> token = $rt['data']['bkn'];
        $this -> HostUin = json_decode(curl($apiaddr."/get_login_info?access_token=$actk"),1)['data']['user_id'];
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
                    e.g.:,lloc,sloc,type,height,width,,height,width
                    调用Upload函数会给出Richval
            注意：RichType和Richval必须同时存在或同时不存在
        */
        
    }

    public function upload ($File, $Type = 'base64') {
        /*
            * 上传图片
            * File: 图片文件路径/Base64编码字符串/URL
            * Type: 默认为base64，表示File为Base64编码字符串
                    url：表示File为图片URL
                    file：表示File为图片文件路径
            * 返回值: Richval字符串
            e.g.: ,lloc,sloc,type,height,width,,height,width
        */
        switch ($Type) {
            case 'file':
                $image = base64_encode(file_get_contents($File));
                break;
            case 'base64':
                $image = $File;
                break;
            case 'url':
                $image = curl($File);
                if (is_numeric($image)) return array('code' => 0,'msg' => 'Req error Httpcode:'.$image); // 返回HTTP状态码
                $image = base64_encode($image);
                break;
            default:
                return array('code' => 0,'msg' => 'Invalid Type');
        }

        $data = array(
            'filename' => 'filename',
            'uin' => $this -> HostUin,
            'skey' => $this -> skey,
            'zzpaneluin' => $this -> HostUin,
            //'zzpanelkey' => null,
            'p_uin' => $this -> HostUin,
            'p_skey' => $this -> pskey,
            //qzonetoken=
            'uploadtype' => 1,
            'albumtype' => 7,
            'exttype' => 0,
            'refer' => 'shuoshuo',
            'output_type' => 'jsonhtml',
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
        $Path = '/cgi_upload_image';
        $result = $this -> post($Path, $data);
        if (is_numeric($result)) return array('code' => 0,'msg' => 'Req error Httpcode:'.$result); // 返回HTTP状态码
        return $result; //初期先直接返回结果
    }

    private function post ($Path, $Type = 'user', $Params) { 
        /*
            * 本文件中所有QQ空间相关操作均为POST方式
            * Path: /cgi-bin之后的内容 以/开头
            * Type: 默认为user:发布说说、删除说说、发表评论
                    upload：上传图片
            * pPrams array形式
        */
        if ($Type == 'user') $url = 'https://user.qzone.qq.com/proxy/domain/taotao.qzone.qq.com/'.$Path.'?g_tk='.$this -> token;
        elseif ($Type == 'upload') $url = 'https://up.qzone.qq.com/cgi-bin/upload'.$Path.'?g_tk='.$this -> token;
        else return array('code' => 0,'msg' => 'Invalid Type');
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
        $cu['CURLOPT_URL'] = $url;
        $cu['CURLOPT_HEADER'] = false;
        $cu['CURLOPT_RETURNTRANSFER'] = true;
        $cu['CURLOPT_FOLLOWLOCATION'] = true;
        if($data):
          $cu['CURLOPT_POST'] = true;
          $cu['CURLOPT_POSTFIELDS'] = $data;
        endif;
        $cu['CURLOPT_HTTPHEADER'] = array("Cookie: ".$this -> Cookies);
        $cu['CURLOPT_SSL_VERIFYPEER'] = false;
        $cu['CURLOPT_SSL_VERIFYHOST'] = false;
        $cu['CURLOPT_USERAGENT'] = "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:140.0) Gecko/20100101 Firefox/140.0";
        $cu['CURLOPT_TIMEOUT'] = "10";
        curl_setopt_array($ch, $cu);
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, 'CURLINFO_HTTP_CODE');
        if ($httpCode >= 400) {
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
}
?>