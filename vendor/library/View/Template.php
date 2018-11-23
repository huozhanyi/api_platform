<?php

namespace Lib\View;
/**
 * 简单的视图模型，原生phtml，只支持layout
 * Created by PhpStorm.
 * User: huozhanyi
 * Date: 2018/6/28
 * Time: 15:29
 */
class Template extends AbstractView
{

    /**
     * 外层布局
     * @var null|\Lib\View\Layout
     */
    private $_layout = null;

    /**
     * 外层布局模型
     * @param null $tplPath 如果不为null则设置外层布局模板
     * @return \Lib\View\Layout
     */
    public function layout($tplPath = null)
    {
        if (!$this->_layout) {
            $this->_layout = new \Lib\View\Layout();
            $this->_layout->setBasePath($this->_basePath);
        }
        if ($tplPath !== null) {
            $this->_layout->setTemplate($tplPath);
        }
        return $this->_layout;
    }

    /**
     * 输出模板
     * @param string $tplPath 模板路径
     */
    public function display($tplPath = '')
    {
        $layout = $this->layout();
        $tplPath && $this->setTemplate($tplPath);
        $layout->setContent($this->getTemplateContent());
        $layout->display();
    }
}