# php-qzone
PHP版本QQ空间的相关操作类

看demo.php，懒得写文档了

这是一个用于操作QQ空间的PHP类库，支持说说的发布、删除、评论，以及图片和视频的上传。

## 主要功能

- 自动获取QQ空间Cookies（默认使用NapCatApi获取cookies）
- 发布说说（支持带(多)图、设置复杂的查看权限、定时发布）
- 删除说说
- 评论说说（支持带图评论）
- 上传图片（支持本地文件、Base64、URL）

## 快速开始

1. 克隆本项目到本地
2. 安装依赖（PHP7.4及以上 需要cURL、GD扩展 推荐使用php8.0）
3. 参考 [demo.php](demo.php) 示例：

```php
<?php
require 'qzone.class.php';
$apiaddr = 'http://127.0.0.1:5000'; // NapCatApi 地址
$token = 'your_token_here'; // NapCatApi token
$instance = new qzone($apiaddr, $token);

// 上传图片并发布带图说说
$imgfile = base64_encode(file_get_contents('test.jpg'));
$richval = $instance->upload($imgfile);
$tid = $instance->publish('测试说说', 1, $richval);

// 带图评论
$richval_url = $instance->upload($imgfile, 'base64', 'url');
print_r($instance->comment($tid, '测试带图评论', 1, $richval_url));

// 普通评论
print_r($instance->comment($tid, '测试普通评论'));

// 删除说说
print_r($instance->delete($tid));
?>
```

## 主要API说明

**请注意 传入参数格式以qzone.class.php中的注释为准，这个readme是ai写的 不完善**

- `publish($Content, $RichType = null, $Richval = null, $setTime = null, $ugcRight = 1, $allowUins = null)`  
  发布说说。

- `upload($File, $Type = 'file/base64/url', $rtType = 'Richval')`  
  上传图片，支持本地文件、Base64、URL。

- `upvideo($File)`  
  上传视频（实验性）。

- `delete($Tid)`  
  删除说说。

- `comment($Tid, $Content, $RichType = null, $Richval = null)`  
  评论说说 支持带图。

## 注意事项

- 本项目仅供学习和交流使用，请勿用于非法用途。

## License

MIT License
