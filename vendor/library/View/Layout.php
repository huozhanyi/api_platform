<?php
namespace Lib\View;
/**
 * 模板外层布局
 * Created by PhpStorm.
 * User: huozhanyi
 * Date: 2018/6/28
 * Time: 16:29
 */
class Layout extends AbstractView
{
    protected $_isMustSetTemplate = false;

    /**
     * 设置内容
     * @param string $txt
     * @return $this
     */
    public function setContent($txt = '')
    {
        $this->content = $txt;
        return $this;
    }

    /**
     * 输出模板
     * @throws Exception
     */
    public function display()
    {
        $content = $this->getTemplateContent();
        if (!$content)
            $content = $this->content;
        echo $content;
    }
}