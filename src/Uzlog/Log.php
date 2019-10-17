<?php

namespace Webzak\Uzlog;

/**
 * Log class
 *
 */
class Log
{
    const TIMER_FP = 4;
    const LOG_TYPE = 1;

    protected $start;
    protected $transport;
    protected $params = ['context' => 0, 'context_files' => false, 'default_size' => 280];


    /**
     * Constructor
     *
     * @param Transport $t
     */
    public function __construct(Transport $t)
    {
        $this->transport = $t;
        $this->start = $this->getMicroTime();
    }

    /**
     * send log message
     *
     * @param mixed $message
     * @param array $opt
     */
    public function send ($message, array $opt = [])
    {
        $fg = $this->get($opt, 'fg', 0);
        $bg = $this->get($opt, 'bg', 0);

        //transform non string types
        $transform = $this->get($opt, 'transform');
        if (is_array($message)) {
            $message = ($transform == 'json') ? json_encode($message) : print_r($message, 1);
        }

        //cut the large message
        $limit = $this->get($opt, 'limit');
        $limit = (!empty($limit) && !is_numeric($limit)) ? $this->getp($limit) : $limit;
        $limit = (empty($limit)) ? $this->getp('default_size') : $limit;
        $text = substr($message, 0, $limit);

        //add context if required
        $message = $this->make_context($opt) . $message;

        $message = sprintf('%8.'.self::TIMER_FP.'f %s', microtime(true) - $this->start, strval($message));
        $header = pack("C*", self::LOG_TYPE, $fg, $bg, 0);
        $this->transport->send($header . $message);
    }

    /**
     * Set parameter
     *
     * @param string $name
     * @param mixed $value
     */
    public function set($name, $value)
    {
        $this->params[$name] = $value;
    }


    protected function get(array $opt, $name, $default = '')
    {
        return (isset($opt[$name])) ? $opt[$name] : $default;
    }

    protected function getp($name, $default = '')
    {
        return (isset($this->params[$name])) ? $this->params[$name] : $defaut;
    }

    protected function getMicroTime()
    {
        list($sec, $msec) = explode(' ', microtime());
        return bcadd($sec, $msec, self::TIMER_FP);
    }

    protected function make_context(array $opt)
    {
        $ret = '';
        $level = intval($this->get($opt, 'context', $this->getp('context')));
        if ($level <= 0) {
            return $ret;
        }
        $level += 3;

        $context = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $level);
        for ($i = count($context) - 1; $i > 2; $i--) {
            $c = $context[$i];
            $ret .= (!empty($c['class'])) ? $c['class'] .'::': '' ;
            $ret .= (!empty($c['function'])) ? $c['function'] .'()' : '';
            if (self::getp('context_files')) {
                $ret .= (!empty($c['file'])) ? $c['file'] : '';
                $ret .= (!empty($c['line'])) ? ' '. $c['line'] : '';
            }
            $ret .="\n         ";
        }
        return $ret;
    }
}
