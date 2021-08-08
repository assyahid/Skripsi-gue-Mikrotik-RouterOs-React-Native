<?php
/*****************************
 *
 * RouterOS PHP API class v1.6
 * Author: Denis Basta
 * Contributors:
 *    Nick Barnes
 *    Ben Menking (ben [at] infotechsc [dot] com)
 *    Jeremy Jefferson (http://jeremyj.com)
 *    Cristian Deluxe (djcristiandeluxe [at] gmail [dot] com)
 *    Mikhail Moskalev (mmv.rus [at] gmail [dot] com)
 *
 * http://www.mikrotik.com
 * http://wiki.mikrotik.com/wiki/API_PHP_class
 *
 ******************************/

class routeros_api {
    public $debug     = false; //  Show debug information
    public $connected = false; //  Connection state
    public $portdata  = 8728; //  Port to connect to (default 8729 for ssl)
    public $ssl       = false; //  Connect using SSL (must enable api-ssl in IP/Services)
    public $timeout   = 3; //  Connection attempt timeout and data read timeout
    public $attempts  = 3; //  Connection attempt count
    public $delay     = 3; //  Delay between connection attempts in seconds

    public $socket; //  Variable for storing socket resource
    public $error_no; //  Variable for storing connection error number, if any
    public $error_str; //  Variable for storing connection error text, if any

    /* Check, can be var used in foreach  */
    public function isIterable($var) {
        return $var !== null
            && (is_array($var)
            || $var instanceof Traversable
            || $var instanceof Iterator
            || $var instanceof IteratorAggregate
        );
    }

    /**
     * Print text for debug purposes
     *
     * @param string      $text       Text to print
     *
     * @return void
     */
    public function debug($text) {
        if ($this->debug) {
            echo $text . "\n";
        }
    }

    /**
     *
     *
     * @param string        $length
     *
     * @return void
     */
    public function encodeLength($length) {
        if ($length < 0x80) {
            $length = chr($length);
        } elseif ($length < 0x4000) {
            $length |= 0x8000;
            $length = chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length < 0x200000) {
            $length |= 0xC00000;
            $length = chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length < 0x10000000) {
            $length |= 0xE0000000;
            $length = chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length >= 0x10000000) {
            $length = chr(0xF0) . chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        }

        return $length;
    }

    /**
     * Login to RouterOS
     *
     * @param string      $ip         Hostname (IP or domain) of the RouterOS server
     * @param string      $login      The RouterOS username
     * @param string      $password   The RouterOS password
     *
     * @return boolean                If we are connected or not
     */
    public function connect($ip, $login, $password, $portdata) {
        if (empty($portdata)) {
            $portdata = 8728 or 8729;
        } else {
            $portdata = $portdata;
        }

        for ($ATTEMPT = 1; $ATTEMPT <= $this->attempts; $ATTEMPT++) {
            $this->connected = false;
            $PROTOCOL        = ($this->ssl ? 'ssl://' : '');
            $context         = stream_context_create(['ssl' => ['ciphers' => 'ADH:ALL', 'verify_peer' => false, 'verify_peer_name' => false]]);
            $this->debug('Connection attempt #' . $ATTEMPT . ' to ' . $PROTOCOL . $ip . ':' . $portdata . '...');
            $this->socket = @stream_socket_client($PROTOCOL . $ip . ':' . $portdata, $this->error_no, $this->error_str, $this->timeout, STREAM_CLIENT_CONNECT, $context);
            if ($this->socket) {
                socket_set_timeout($this->socket, $this->timeout);
                $this->write('/login');
                $RESPONSE = $this->read(false);
                if (isset($RESPONSE[0]) && $RESPONSE[0] == '!done') {
                    $MATCHES = [];
                    if (preg_match_all('/[^=]+/i', $RESPONSE[1], $MATCHES)) {
                        if ($MATCHES[0][0] == 'ret' && strlen($MATCHES[0][1]) == 32) {
                            $this->write('/login', false);
                            $this->write('=name=' . $login, false);
                            $this->write('=response=00' . md5(chr(0) . $password . pack('H*', $MATCHES[0][1])));
                            $RESPONSE = $this->read(false);
                            if (isset($RESPONSE[0]) && $RESPONSE[0] == '!done') {
                                $this->connected = true;
                                break;
                            }
                        }
                    }
                }
                fclose($this->socket);
            }
            sleep($this->delay);
        }

        if ($this->connected) {
            $this->debug('Connected...');
        } else {
            $this->debug('Error...');
        }
        return $this->connected;
    }

    /**
     * Disconnect from RouterOS
     *
     * @return void
     */
    public function disconnect() {
        // let's make sure this socket is still valid.  it may have been closed by something else
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->connected = false;
        $this->debug('Disconnected...');
    }

    /**
     * Parse response from Router OS
     *
     * @param array       $response   Response data
     *
     * @return array                  Array with parsed data
     */
    public function parseResponse($response) {
        if (is_array($response)) {
            $PARSED      = [];
            $CURRENT     = null;
            $singlevalue = null;
            foreach ($response as $x) {
                if (in_array($x, ['!fatal', '!re', '!trap'])) {
                    if ($x == '!re') {
                        $CURRENT = &$PARSED[];
                    } else {
                        $CURRENT = &$PARSED[$x][];
                    }
                } elseif ($x != '!done') {
                    $MATCHES = [];
                    if (preg_match_all('/[^=]+/i', $x, $MATCHES)) {
                        if ($MATCHES[0][0] == 'ret') {
                            $singlevalue = $MATCHES[0][1];
                        }
                        $CURRENT[$MATCHES[0][0]] = (isset($MATCHES[0][1]) ? $MATCHES[0][1] : '');
                    }
                }
            }

            if (empty($PARSED) && !is_null($singlevalue)) {
                $PARSED = $singlevalue;
            }

            return $PARSED;
        } else {
            return [];
        }
    }

    /**
     * Parse response from Router OS
     *
     * @param array       $response   Response data
     *
     * @return array                  Array with parsed data
     */
    public function parseResponse4Smarty($response) {
        if (is_array($response)) {
            $PARSED      = [];
            $CURRENT     = null;
            $singlevalue = null;
            foreach ($response as $x) {
                if (in_array($x, ['!fatal', '!re', '!trap'])) {
                    if ($x == '!re') {
                        $CURRENT = &$PARSED[];
                    } else {
                        $CURRENT = &$PARSED[$x][];
                    }
                } elseif ($x != '!done') {
                    $MATCHES = [];
                    if (preg_match_all('/[^=]+/i', $x, $MATCHES)) {
                        if ($MATCHES[0][0] == 'ret') {
                            $singlevalue = $MATCHES[0][1];
                        }
                        $CURRENT[$MATCHES[0][0]] = (isset($MATCHES[0][1]) ? $MATCHES[0][1] : '');
                    }
                }
            }
            foreach ($PARSED as $key => $value) {
                $PARSED[$key] = $this->arrayChangeKeyName($value);
            }
            return $PARSED;
            if (empty($PARSED) && !is_null($singlevalue)) {
                $PARSED = $singlevalue;
            }
        } else {
            return [];
        }
    }

    /**
     * Change "-" and "/" from array key to "_"
     *
     * @param array       $array      Input array
     *
     * @return array                  Array with changed key names
     */
    public function arrayChangeKeyName(&$array) {
        if (is_array($array)) {
            foreach ($array as $k => $v) {
                $tmp = str_replace("-", "_", $k);
                $tmp = str_replace("/", "_", $tmp);
                if ($tmp) {
                    $array_new[$tmp] = $v;
                } else {
                    $array_new[$k] = $v;
                }
            }
            return $array_new;
        } else {
            return $array;
        }
    }

    /**
     * Read data from Router OS
     *
     * @param boolean     $parse      Parse the data? default: true
     *
     * @return array                  Array with parsed or unparsed data
     */
    public function read($parse = true) {
        $RESPONSE     = [];
        $receiveddone = false;
        while (true) {
            // Read the first byte of input which gives us some or all of the length
            // of the remaining reply.
            $BYTE   = ord(fread($this->socket, 1));
            $LENGTH = 0;
            // If the first bit is set then we need to remove the first four bits, shift left 8
            // and then read another byte in.
            // We repeat this for the second and third bits.
            // If the fourth bit is set, we need to remove anything left in the first byte
            // and then read in yet another byte.
            if ($BYTE & 128) {
                if (($BYTE & 192) == 128) {
                    $LENGTH = (($BYTE & 63) << 8) + ord(fread($this->socket, 1));
                } else {
                    if (($BYTE & 224) == 192) {
                        $LENGTH = (($BYTE & 31) << 8) + ord(fread($this->socket, 1));
                        $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                    } else {
                        if (($BYTE & 240) == 224) {
                            $LENGTH = (($BYTE & 15) << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                        } else {
                            $LENGTH = ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                        }
                    }
                }
            } else {
                $LENGTH = $BYTE;
            }

            $_ = "";

            // If we have got more characters to read, read them in.
            if ($LENGTH > 0) {
                $_      = "";
                $retlen = 0;
                while ($retlen < $LENGTH) {
                    $toread = $LENGTH - $retlen;
                    $_ .= fread($this->socket, $toread);
                    $retlen = strlen($_);
                }
                $RESPONSE[] = $_;
                $this->debug('>>> [' . $retlen . '/' . $LENGTH . '] bytes read.');
            }

            // If we get a !done, make a note of it.
            if ($_ == "!done") {
                $receiveddone = true;
            }

            $STATUS = socket_get_status($this->socket);
            if ($LENGTH > 0) {
                $this->debug('>>> [' . $LENGTH . ', ' . $STATUS['unread_bytes'] . ']' . $_);
            }

            if ((!$this->connected && !$STATUS['unread_bytes']) || ($this->connected && !$STATUS['unread_bytes'] && $receiveddone)) {
                break;
            }
        }

        if ($parse) {
            $RESPONSE = $this->parseResponse($RESPONSE);
        }

        return $RESPONSE;
    }

    /**
     * Write (send) data to Router OS
     *
     * @param string      $command    A string with the command to send
     * @param mixed       $param2     If we set an integer, the command will send this data as a "tag"
     *                                If we set it to boolean true, the funcion will send the comand and finish
     *                                If we set it to boolean false, the funcion will send the comand and wait for next command
     *                                Default: true
     *
     * @return boolean                Return false if no command especified
     */
    public function write($command, $param2 = true) {
        if ($command) {
            $data = explode("\n", $command);
            foreach ($data as $com) {
                $com = trim($com);
                fwrite($this->socket, $this->encodeLength(strlen($com)) . $com);
                $this->debug('<<< [' . strlen($com) . '] ' . $com);
            }

            if (gettype($param2) == 'integer') {
                fwrite($this->socket, $this->encodeLength(strlen('.tag=' . $param2)) . '.tag=' . $param2 . chr(0));
                $this->debug('<<< [' . strlen('.tag=' . $param2) . '] .tag=' . $param2);
            } elseif (gettype($param2) == 'boolean') {
                fwrite($this->socket, ($param2 ? chr(0) : ''));
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * Write (send) data to Router OS
     *
     * @param string      $com        A string with the command to send
     * @param array       $arr        An array with arguments or queries
     *
     * @return array                  Array with parsed
     */
    public function comm($com, $arr = []) {
        $count = count($arr);
        $this->write($com, !$arr);
        $i = 0;
        if ($this->isIterable($arr)) {
            foreach ($arr as $k => $v) {
                switch ($k[0]) {
                    case "?":
                        $el = "$k=$v";
                        break;
                    case "~":
                        $el = "$k~$v";
                        break;
                    default:
                        $el = "=$k=$v";
                        break;
                }

                $last = ($i++ == $count - 1);
                $this->write($el, $last);
            }
        }

        return $this->read();
    }

    /**
     * Standard destructor
     *
     * @return void
     */
    public function __destruct() {
        $this->disconnect();
    }
}

// encrypt decript

function encrypt($string, $key = 128) {
    $result = '';
    for ($i = 0, $k = strlen($string); $i < $k; $i++) {
        $char    = substr($string, $i, 1);
        $keychar = substr($key, ($i % strlen($key)) - 1, 1);
        $char    = chr(ord($char) + ord($keychar));
        $result .= $char;
    }
    return base64_encode($result);
}
function decrypt($string, $key = 128) {
    $result = '';
    $string = base64_decode($string);
    for ($i = 0, $k = strlen($string); $i < $k; $i++) {
        $char    = substr($string, $i, 1);
        $keychar = substr($key, ($i % strlen($key)) - 1, 1);
        $char    = chr(ord($char) - ord($keychar));
        $result .= $char;
    }
    return $result;
}

// Reformat date time MikroTik
// by Laksamadi Guko

function formatInterval($dtm) {
    $val_convert = $dtm;
    $new_format  = str_replace("s", "", str_replace("m", "m ", str_replace("h", "h ", str_replace("d", "d ", str_replace("w", "w ", $val_convert)))));
    return $new_format;
}

function formatDTM($dtm) {
    if (substr($dtm, 1, 1) == "d" || substr($dtm, 2, 1) == "d") {
        $day = explode("d", $dtm)[0] . "d";
        $day = str_replace("d", "d ", str_replace("w", "w ", $day));
        $dtm = explode("d", $dtm)[1];
    } elseif (substr($dtm, 1, 1) == "w" && substr($dtm, 3, 1) == "d" || substr($dtm, 2, 1) == "w" && substr($dtm, 4, 1) == "d") {
        $day = explode("d", $dtm)[0] . "d";
        $day = str_replace("d", "d ", str_replace("w", "w ", $day));
        $dtm = explode("d", $dtm)[1];
    } elseif (substr($dtm, 1, 1) == "w" || substr($dtm, 2, 1) == "w") {
        $day = explode("w", $dtm)[0] . "w";
        $day = str_replace("d", "d ", str_replace("w", "w ", $day));
        $dtm = explode("w", $dtm)[1];
    }

// secs
    if (strlen($dtm) == "2" && substr($dtm, -1) == "s") {
        $format = $day . " 00:00:0" . substr($dtm, 0, -1);
    } elseif (strlen($dtm) == "3" && substr($dtm, -1) == "s") {
        $format = $day . " 00:00:" . substr($dtm, 0, -1);
//minutes
    } elseif (strlen($dtm) == "2" && substr($dtm, -1) == "m") {
        $format = $day . " 00:0" . substr($dtm, 0, -1) . ":00";
    } elseif (strlen($dtm) == "3" && substr($dtm, -1) == "m") {
        $format = $day . " 00:" . substr($dtm, 0, -1) . ":00";
//hours
    } elseif (strlen($dtm) == "2" && substr($dtm, -1) == "h") {
        $format = $day . " 0" . substr($dtm, 0, -1) . ":00:00";
    } elseif (strlen($dtm) == "3" && substr($dtm, -1) == "h") {
        $format = $day . " " . substr($dtm, 0, -1) . ":00:00";

//minutes -secs
    } elseif (strlen($dtm) == "4" && substr($dtm, -1) == "s" && substr($dtm, 1, -2) == "m") {
        $format = $day . " " . "00:0" . substr($dtm, 0, 1) . ":0" . substr($dtm, 2, -1);
    } elseif (strlen($dtm) == "5" && substr($dtm, -1) == "s" && substr($dtm, 1, -3) == "m") {
        $format = $day . " " . "00:0" . substr($dtm, 0, 1) . ":" . substr($dtm, 2, -1);
    } elseif (strlen($dtm) == "5" && substr($dtm, -1) == "s" && substr($dtm, 2, -2) == "m") {
        $format = $day . " " . "00:" . substr($dtm, 0, 2) . ":0" . substr($dtm, 3, -1);
    } elseif (strlen($dtm) == "6" && substr($dtm, -1) == "s" && substr($dtm, 2, -3) == "m") {
        $format = $day . " " . "00:" . substr($dtm, 0, 2) . ":" . substr($dtm, 3, -1);

//hours -secs
    } elseif (strlen($dtm) == "4" && substr($dtm, -1) == "s" && substr($dtm, 1, -2) == "h") {
        $format = $day . " 0" . substr($dtm, 0, 1) . ":00:0" . substr($dtm, 2, -1);
    } elseif (strlen($dtm) == "5" && substr($dtm, -1) == "s" && substr($dtm, 1, -3) == "h") {
        $format = $day . " 0" . substr($dtm, 0, 1) . ":00:" . substr($dtm, 2, -1);
    } elseif (strlen($dtm) == "5" && substr($dtm, -1) == "s" && substr($dtm, 2, -2) == "h") {
        $format = $day . " " . substr($dtm, 0, 2) . ":00:0" . substr($dtm, 3, -1);
    } elseif (strlen($dtm) == "6" && substr($dtm, -1) == "s" && substr($dtm, 2, -3) == "h") {
        $format = $day . " " . substr($dtm, 0, 2) . ":00:" . substr($dtm, 3, -1);

//hours -secs
    } elseif (strlen($dtm) == "4" && substr($dtm, -1) == "m" && substr($dtm, 1, -2) == "h") {
        $format = $day . " 0" . substr($dtm, 0, 1) . ":0" . substr($dtm, 2, -1) . ":00";
    } elseif (strlen($dtm) == "5" && substr($dtm, -1) == "m" && substr($dtm, 1, -3) == "h") {
        $format = $day . " 0" . substr($dtm, 0, 1) . ":" . substr($dtm, 2, -1) . ":00";
    } elseif (strlen($dtm) == "5" && substr($dtm, -1) == "m" && substr($dtm, 2, -2) == "h") {
        $format = $day . " " . substr($dtm, 0, 2) . ":0" . substr($dtm, 3, -1) . ":00";
    } elseif (strlen($dtm) == "6" && substr($dtm, -1) == "m" && substr($dtm, 2, -3) == "h") {
        $format = $day . " " . substr($dtm, 0, 2) . ":" . substr($dtm, 3, -1) . ":00";

//hours minutes secs
    } elseif (strlen($dtm) == "6" && substr($dtm, -1) == "s" && substr($dtm, 3, -2) == "m" && substr($dtm, 1, -4) == "h") {
        $format = $day . " 0" . substr($dtm, 0, 1) . ":0" . substr($dtm, 2, -3) . ":0" . substr($dtm, 4, -1);
    } elseif (strlen($dtm) == "7" && substr($dtm, -1) == "s" && substr($dtm, 3, -3) == "m" && substr($dtm, 1, -5) == "h") {
        $format = $day . " 0" . substr($dtm, 0, 1) . ":0" . substr($dtm, 2, -4) . ":" . substr($dtm, 4, -1);
    } elseif (strlen($dtm) == "7" && substr($dtm, -1) == "s" && substr($dtm, 4, -2) == "m" && substr($dtm, 1, -5) == "h") {
        $format = $day . " 0" . substr($dtm, 0, 1) . ":" . substr($dtm, 2, -3) . ":0" . substr($dtm, 5, -1);
    } elseif (strlen($dtm) == "8" && substr($dtm, -1) == "s" && substr($dtm, 4, -3) == "m" && substr($dtm, 1, -6) == "h") {
        $format = $day . " 0" . substr($dtm, 0, 1) . ":" . substr($dtm, 2, -4) . ":" . substr($dtm, 5, -1);
    } elseif (strlen($dtm) == "7" && substr($dtm, -1) == "s" && substr($dtm, 4, -2) == "m" && substr($dtm, 2, -4) == "h") {
        $format = $day . " " . substr($dtm, 0, 2) . ":0" . substr($dtm, 3, -3) . ":0" . substr($dtm, 5, -1);
    } elseif (strlen($dtm) == "8" && substr($dtm, -1) == "s" && substr($dtm, 4, -3) == "m" && substr($dtm, 2, -5) == "h") {
        $format = $day . " " . substr($dtm, 0, 2) . ":0" . substr($dtm, 3, -4) . ":" . substr($dtm, 5, -1);
    } elseif (strlen($dtm) == "8" && substr($dtm, -1) == "s" && substr($dtm, 5, -2) == "m" && substr($dtm, 2, -5) == "h") {
        $format = $day . " " . substr($dtm, 0, 2) . ":" . substr($dtm, 3, -3) . ":0" . substr($dtm, 6, -1);
    } elseif (strlen($dtm) == "9" && substr($dtm, -1) == "s" && substr($dtm, 5, -3) == "m" && substr($dtm, 2, -6) == "h") {
        $format = $day . " " . substr($dtm, 0, 2) . ":" . substr($dtm, 3, -4) . ":" . substr($dtm, 6, -1);
    } else {
        $format = $dtm;
    }
    return $format;
}

//=====================================================START====================//

/*
 *  Base Code   : BangAchil
 *  Email       : kesumaerlangga@gmail.com
 *  Telegram    : @bangachil
 *
 *  Name        : Mikrotik bot telegram - php
 *  Function    : Mikortik api
 *  Manufacture : November 2018
 *  Last Edited : 26 Desember 2019
 *
 *  Please do not change this code
 *  All damage caused by editing we will not be responsible please think carefully,
 *
 */

//=====================================================START SCRIPT====================//

function toBytes($i) {
    $bytes = ($i * 1000000);
    return $bytes;
}
function Crusername($length) {
    $chars = "1234567890abcdefghijkmnopqrstuvwxyz";
    $i     = 1;
    $user  = "";
    while ($i <= $length) {
        $user .= $chars{mt_rand(0, strlen($chars))};
        $i++;
    }
    return $user;
}
function Crpas($length) {
    $chars = "1234567890abcdefghijkmnopqrstuvwxyz";
    $i     = 1;
    $pass  = "";
    while ($i <= $length) {
        $pass .= $chars{mt_rand(0, strlen($chars))};
        $i++;
    }
    return $pass;
    
    
};
//Update by BangAchil 2 april 2019

function msubstr($str, $start = 0, $length, $charset = "utf-8", $suffix = true){
	if (function_exists("mb_substr")) {
		$i_str_len = mb_strlen($str);
		$s_sub_str = mb_substr($str, $start, $length, $charset);
		if ($length >= $i_str_len) {
			return $s_sub_str;
		} 
		return $s_sub_str . '';
	} elseif (function_exists('iconv_substr')) {
		return iconv_substr($str, $start, $length, $charset);
	} 
	$re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
	$re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
	$re['gbk'] = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
	$re['big5'] = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
	preg_match_all($re[$charset], $str, $match);
	$slice = join("", array_slice($match[0], $start, $length));
	if ($suffix) return $slice . "…";
	return $slice;
}

function make_string($len = 4, $type='checkCode'){
	$str = '';
	switch ($type) {
		case 1:
			$chars = str_repeat('123456789', 3);
			break;
		case 2:
			$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
			break;
		case 3:
			$chars = 'abcdefghijklmnopqrstuvwxyz';
			break;
		case 4:
			$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
			break;
		case 5:
			$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz123456789';
			break;
		case 6:
			$chars = 'abcdefghijklmnopqrstuvwxyz123456789';
			break;
		case 7:
			$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789';
			break;	
		default: 
			$chars = '';
			break;
	}
	if ($len > 10) { 
		$chars = $type == 1 ? str_repeat($chars, $len) : str_repeat($chars, 5);
	} 
	if ($type != 4) {
		$chars = str_shuffle($chars);
		$str = substr($chars, 0, $len);
	} else {
	
		for($i = 0; $i < $len; $i ++) {
			$str .= msubstr($chars, floor(mt_rand(0, mb_strlen($chars, 'utf-8') - 1)), 1);
		} 
	} 
	return $str;
} 

function show_string($type){
	switch ($type) {
		case 1:
			$chars = ' <option value="1">1234</option>';
			break;
		case 2:
			$chars = '<option value="2">ABCDE</option>';
			break;
		case 3:
			$chars = ' <option value="3">abcd</option>';
			break;
		case 4:
			$chars = '<option value="4">ABCDabcd</option>';
			break;
		case 5:
			$chars = ' <option value="5">ABCDabcd1234</option>';
			break;
		case 6:
			$chars = '<option value="6">abcd1234</option>';
			break;
		case 7:
			$chars = ' <option value="7">ABCD1234</option>';
			break;
		default: 
			$chars = ' <option value=""></option>';
			break;
	}
	return $chars;
}








//=====================================================END SCRIPT====================//

?>
