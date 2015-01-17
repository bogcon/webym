<?php
/**
 * Logger utility class.
 * 
 * @author      Bogdan Constantinescu <bog_con@yahoo.com>
 * @link        GitHub  https://github.com/bogcon/webym
 * @license     BSD 3-Clause (http://opensource.org/licenses/BSD-3-Clause); see LICENSE.txt
 */
namespace BogCon\WebYm;

class Logger
{
    /**
     * @var     \BogCon\WebYm\Logger     Self instance of the class.
     */
    private static $logger = null;

    /**
     * @var     string                  Filename where logs will be written.
     */
    protected $debugFile = '';

    /**
     * @var     resource                File pointer resource.
     */
    protected $f = null;

    /**
     * @var     const int               Different log levels.
     */
    const EMERG  = 0;
    const ALERT  = 1;
    const CRIT   = 2;
    const ERR    = 3;
    const WARN   = 4;
    const NOTICE = 5;
    const INFO   = 6;
    const DEBUG  = 7;
    
    
    
    /**
     * Private constructor; initializes class members.
     */
    private function __construct()
    {
        $strDebugFile = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'webym.log';
        $this->f = fopen($strDebugFile, 'a');
        $this->debugFile = realpath($strDebugFile);
    }
    
    
    
    /**
     * Destructor; frees resources, memory, closes connections, etc....
     */
    public function __destruct()
    {
        if (is_resource($this->f)) {
            fclose($this->f);
        }
    }
    
    
    
    /**
     * Retrieve unique instance of the class; implements Singleton pattern.
     * @return \BogCon\WebYm\Logger
     */
    public static function getInstance()
    {
        if (is_null(self::$logger)) {
            self::$logger = new self();
        }
        return self::$logger;
    }
    
    
    
    /**
     * Log a message.
     * @param   string    $strMsg            The message to log.
     * @param   int       $intLogLevel       The message severity.
     */
    public static function log($strMsg, $intLogLevel = self::DEBUG)
    {
        self::getInstance()->write($strMsg, $intLogLevel);
    }
    
    
    
    /**
     * Write a message.
     * @param   string    $strMsg            The message to log.
     * @param   int       $intLogLevel       The message severity.
     */
    private function write($strMsg, $intLogLevel = self::DEBUG)
    {
        if (is_resource($this->f)) {
            $strToWrite = '[' . date('Y-m-d H:i:s') . '][';
            switch ($intLogLevel) {
                case self::DEBUG:
                    $strToWrite .= 'DEBUG';
                    break;
                case self::EMERG:
                    $strToWrite .= 'EMERG';
                    break;
                case self::CRIT:
                    $strToWrite .= 'CRIT';
                    break;
                case self::ERR:
                    $strToWrite .= 'ERR';
                    break;
                case self::WARN:
                    $strToWrite .= 'WARN';
                    break;
                case self::NOTICE:
                    $strToWrite .= 'NOTTICE';
                    break;
                case self::INFO:
                    $strToWrite .= 'INFO';
                    break;
                default:
                    $strToWrite .= 'DEBUG';
            }
            $strToWrite .= '] ' . $strMsg . PHP_EOL;
            @fwrite($this->f, $strToWrite);
        }
    }
}
