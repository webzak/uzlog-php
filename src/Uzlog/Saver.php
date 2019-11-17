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
}
