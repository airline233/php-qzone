<?php
class qzone {
    /*
        全局变量说明:
            HostUin 登录QQ空间的QQ号
            Cookies 登录QQ空间的Cookies 直接调用init()函数可直接设置（调用NapCatApi）
    */
    public $HostUin;
    public $Cookies;

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
    }
   
    public function getcookies ($apiaddr) {
        /*
            * 获取QQ空间Cookies
            * 返回值: Cookies字符串
            * apiaddr: NapCatApi地址 如http://172.16.0.1:5000
        */

    }

    private function post ($Path, $Params) { 
        /*
            * 本文件中所有QQ空间相关操作均为POST方式
            * Path: /cgi-bin之后的内容 以/开头
            * Type: 默认为user:发布说说、删除说说、发表评论
                    upload：上传图片
            * pPrams array形式
        */
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
}
?>