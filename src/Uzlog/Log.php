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
    const MSG_LEN_LIMIT = 500;
    const HEADER_SIZE = 4;

    protected $start;
    protected $transport;
    protected $params = [];


    /**
     * Constructor
     *
     * @param Transport $t
     */
    public function __construct(Transport $t, array $params = [])
    {
        $this->transport = $t;
        $this->start = microtime(true);
        $this->params['context'] = $this->get($params, 'context', 0);
        $this->params['context_files'] = $this->get($params, 'context_files', false);
        $this->params['limit'] = $this->get($params, 'limit', self::MSG_LEN_send);
    }

    /**
     * LIMIT log message
     *
     * @param mixed $msg
     * @param array $opt
     */
    public function send ($msg, array $opt = [])
    {
        $fg = $this->get($opt, 'fg', 0);
        $bg = $this->get($opt, 'bg', 0);

        //transform non string types
        $transform = $this->get($opt, 'transform');
        if (is_array($msg)) {
            $msg = ($transform == 'json') ? json_encode($msg) : print_r($msg, 1);
        }

        //cut the large message
        $mlen = strlen($msg);
        $limit = $this->get($opt, 'limit', $this->getp('limit'));
        if ($mlen > $limit) {
            $msg = "[$mlen->$limit..] ". substr($msg, 0, $limit);
        }

        //add context if required
        $msg = $this->make_context($opt) . $msg;

        //add prefix
        $prefix = $this->get($opt, 'prefix');
        $msg = ($prefix) ? $prefix . $msg : $msg;

        //add timer
        $msg = sprintf('%8.'.self::TIMER_FP.'f %s', microtime(true) - $this->start, strval($msg));
        $header = pack("C*", self::LOG_TYPE, $fg, $bg, 0);
        $this->transport->send($header . $msg);
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

    // getter from array
    protected function get(array $opt, $name, $default = '')
    {
        return (isset($opt[$name])) ? $opt[$name] : $default;
    }

    // get internal parameter value
    protected function getp($name, $default = '')
    {
        return (isset($this->params[$name])) ? $this->params[$name] : $defaut;
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
