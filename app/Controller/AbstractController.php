<?php

namespace Controller;
/**
 * Created by PhpStorm.
 * User: huozhanyi
 * Date: 2018/11/23
 * Time: 11:37
 */
abstract class AbstractController
{
    public function __construct()
    {
        $this->_init();
    }

    /**
     * 子类初始化可重写方法
     */
    protected function _init()
    {
    }

    /**
     * @var null|\Lib\View\Template
     */
    private $_viewModel = null;

    /**
     * 获取视图模型
     * @return \Lib\View\Template
     */
    protected function getViewModel()
    {
        if (!$this->_viewModel) {
            $this->_viewModel = new \Lib\View\Template();
        }
        return $this->_viewModel;
    }

    /**
     * @param string $msg 返回信息
     * @param array|null $data 返回数据
     * @param int $lazyTime 延时跳转
     */
    protected function success($msg = '', $data = null, $lazyTime = 1)
    {
        return $this->_returnResponse(0, $msg, $data, $lazyTime);
    }

    /**
     * @param string $msg 返回信息
     * @param array|null $data 返回数据
     * @param int $code 错误代码
     * @param int $lazyTime 延时跳转
     */
    protected function error($msg = '', $data = null, $code = -1, $lazyTime = 3)
    {
        if (!$code)
            $code = -1;
        return $this->_returnResponse($code, $msg, $data, $lazyTime);
    }

    /**
     * 页面响应
     * @param string $msg 返回信息
     * @param array|null $data 返回数据
     * @param int $code 错误代码
     * @param int $lazyTime 延时跳转
     */
    private function _returnResponse($code, $msg = '', $data = null, $lazyTime = 0)
    {
        if ($this->isAjax() || $this->_compelReturnJson) {//返回json
            $json = array();
            $json['code'] = $code;
            $json['msg'] = $msg;
            $json['data'] = $data ? $data : new \ArrayObject();
            header('Content-type:text/json');
            echo json_encode($json);
        } else {
            $redirectUrl = '';
            isset($data['redirectUrl']) && ($redirectUrl = $data['redirectUrl']);
            if ($lazyTime) {//提示页面
                $view = $this->getViewModel();
                $view->msg = $msg;
                $view->redirectUrl = $redirectUrl;
                $view->lazyTime = $lazyTime;
                $view->display($code ? 'error' : 'success');
            } else {//直接跳转
                if ($redirectUrl) {//url跳转
                    header('Location:' . $redirectUrl);
                } else { //$redirectUrl为空则返回上一页
                    header('Location:' . $_SERVER['HTTP_REFERER']);
                }
            }
        }
        return true;
    }

    /**
     * @var bool
     */
    private $_compelReturnJson = false;

    /**
     * 强制返回JSON
     * @param boolean $set
     * @return self
     */
    protected function compelReturnJson($set = true)
    {
        $this->_compelReturnJson = (boolean)$set;
        return $this;
    }

    /**
     * 是否ajax请求
     * @return bool
     */
    protected function isAjax()
    {
        return isset($_SERVER["HTTP_X_REQUESTED_WITH"]) && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) == "xmlhttprequest";
    }
}