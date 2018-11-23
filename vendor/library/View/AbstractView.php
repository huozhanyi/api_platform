<?php

namespace Lib\View;
/**
 * 视图模型抽象类
 * Created by PhpStorm.
 * User: huozhanyi
 * Date: 2018/6/28
 * Time: 16:44
 */
abstract class AbstractView
{
    protected $_basePath = '';//模板基础目录

    /**
     * 是否必须设置模板
     * @var bool
     */
    protected $_isMustSetTemplate = true;

    /**
     * 模板变量
     * @var array
     */
    protected $_params = array();

    /**
     * 模板
     * @var string
     */
    protected $_template = '';

    /**
     * 设置模板变量
     * @param $key
     * @param null $value
     * @return $this
     */
    public function assign($key, $value = null)
    {
        $this->_params[$key] = $value;
        return $this;
    }

    /**
     * 设置模板
     * @param string $tplPath
     * @return $this
     */
    public function setTemplate($tplPath = '')
    {
        $this->_template = $tplPath;
        return $this;
    }

    /**
     * 获取模板内容
     * @return string
     * @throws Exception
     */
    public function getTemplateContent()
    {
        $content = '';
        $template = $this->_template;
        if ($this->_isMustSetTemplate && !$template)
            throw new \Exception('请设置模板');
        if ($template) {
            $content = $this->_getTemplateContent($this->_template, $this->_params);
        }
        return $content;
    }

    /**
     * 渲染模板
     * @param $template
     * @param array|null $params
     * @return string
     * @throws Exception
     */
    public function render($template, array $params = null)
    {
        return $this->_getTemplateContent($template, $params);
    }

    /**
     * @param string $template
     * @param array|null $params
     * @return string
     * @throws Exception
     */
    private function _getTemplateContent($template, array $params = null)
    {
        $template = $this->getTemplatePath($template);
        //检测原有的输出
        $__old_content = ob_get_contents();
        if (!empty($__old_content)) {
            ob_end_clean();
        }
        //处理当前模板的输出
        if ($params) {
            extract($params);
        }
        ob_start();
        require $template;
        $content = ob_get_contents();
        ob_end_clean();
        //恢复原来的上下文
        if (!empty($__old_content)) {
            ob_start();
            echo $__old_content;
        }
        return $content;
    }

    /**
     * @param $template
     * @return string
     * @throws Exception
     */
    public function getTemplatePath($template)
    {
        if (!$template)
            throw new \Exception("请设置模板");
        $template = $this->_basePath . '/' . $template . '.phtml';
        if (!file_exists($template))
            throw new \Exception('模板文件' . $template . '不存在');
        return $template;
    }

    /**
     * 设置模板基础目录
     * @param string $path
     * @return $this
     */
    public function setBasePath($path)
    {
        $this->_basePath = $path;
        return $this;
    }

    public function __set($name, $value)
    {
        $this->assign($name, $value);
    }

    public function __get($name)
    {
        return isset($this->_params[$name]) ? $this->_params[$name] : null;
    }
}