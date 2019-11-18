<?php

namespace Webzak\Uzlog;

/**
 * Saver class
 *
 */
class Saver
{
    const FILE_TYPE = 2;
    const HEADER_SIZE = 2;

    protected $rotated = [];

    /**
     * Constructor
     *
     * @param Transport $t
     */
    public function __construct(Transport $t)
    {
        $this->transport = $t;
    }

    /**
     * save data remotely
     *
     * @param string $fname
     * @param string|array $data
     */
    public function send($fname, $data, array $opt = [])
    {
        if (!is_string($fname)) {
            throw new Exception("File name must be string!");
        }
        $fname = $this->handleRotated($fname);
        if (!is_string($data) && !is_array($data)) {
            throw new Exception("Data are not string or array!");
        }
        if (is_array($data)) {
            $data = json_encode($data, (empty($opt['raw'])) ? JSON_PRETTY_PRINT : 0);
        }
        $append = (empty($opt['append'])) ? 0 : 1;
        $header = pack("C*", self::FILE_TYPE, $append);
        $this->transport->send($header . $fname . "\0" . $data);
    }

    protected function handleRotated($fname)
    {
        $n = substr_count($fname, '?');
        if ($n === 0) {
            return $fname;
        }
        $mask = str_repeat('?', $n);
        if (strpos($fname, $mask) === false) {
            throw new Exception("The rotation mask symbol '?' must occur withing single group!");
        }
        $this->rotated[$fname] = (isset($this->rotated[$fname])) ? $this->rotated[$fname] + 1 : 0;
        $subst = sprintf("%0{$n}d", $this->rotated[$fname]);
        return str_replace($mask, $subst, $fname);
    }
}
