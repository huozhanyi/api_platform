# 说明文档


## 框架相关
本框架以Composer为基础，实现主框架及各个组件的安装及自动加载，并严格遵循psr-4规范。

### 基础组件
* mikecao/flight（主框架） [文档](http://flightphp.com/)
* catfan/Medoo（数据库） [文档](http://medoo.in/)
* desarrolla2/cache（缓存） [文档](https://github.com/desarrolla2/Cache/)
* curl/curl（数据抓取） [文档](https://github.com/php-mod/curl/)
* predis/predis（redis） [文档](https://github.com/nrk/predis)

### 文件夹说明
* app *项目源码*
  * controller *控制器*
  * library *通用类*
  * model *数据模型*
  * view *视图*
* bootstrap *引导文件*（子项目独立配置/路由）
* config *项目配置*
* crond *后台脚本*
* public *对外文件*（网站入口/静态文件/上传文件）
* storage *数据存储*
  * cache *缓存文件*
  * log *日志文件*
* vendor *引入组件* （初始化后自动生成）


## 准备工作
1. 安装Composer：前往[官方网站](https://getcomposer.org/)下载对应系统版本
2. 初始化组件：在项目跟目录运行以下命令
```
composer install
```
3. 提取默认配置文件：从/config/default.tar中解压到当前目录
   * config.php *项目配置*
4. 配置Nginx：（根据系统修改相关路径）
```
server {
    listen 80;
    listen [::]:80;

    server_name www.51thb.com;
    index index.html index.php;
    error_log /var/log/nginx/51thb.error.log;
    access_log /var/log/nginx/51thb.access.log;
    root /var/www/html/51thb/public;

    location / {
        try_files $uri $uri/ /index.php;
    }

    #pass the PHP scripts to FastCGI server listening on /var/run/php5-fpm.sock
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/var/run/php5-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;

    }
}
```
5.  数据库: storage/wythb_16999_com.sql

## 维护人员
* 大林
