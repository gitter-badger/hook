<?php namespace Hook\Http;

class Request {

    /**
     * server
     * @param name
     * @return string
     */
    public static function server($name) {
        return Router::getInstance()->environment->offsetGet($name);
    }

    /**
     * header
     * @param name
     * @param default
     * @return string
     */
    public static function header($name, $default = null) {
        return Router::getInstance()->request->headers->get($name, $default);
    }

    /**
     * ip
     * @return string
     */
    public static function ip() {
        return Router::getInstance()->request->getIp();
    }

    /**
     * path
     * @return string
     */
    public static function path() {
        return Router::getInstance()->request->getResourceUri();
    }

    /**
     * method
     * @return string
     */
    public static function method() {
        return Router::getInstance()->request->getMethod();
    }

    /**
     * redirect
     # @param string
     */
    public static function redirect($path) {
        return Router::getInstance()->redirect($path);
    }

    public static function __callStatic($method, $args = array()) {
        $request = Router::getInstance()->request;
        return call_user_func_array(array($request, $method), $args);
    }

}
