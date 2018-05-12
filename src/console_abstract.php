<?php
/**
 * Constants
 */
define('DS', DIRECTORY_SEPARATOR);

/**
 * Console Abstract
 * Reusable abstract for creating PHP shell utilities
 */
class Console_Abstract
{
	/**
	 * Config defaults
	 */
	public $verbose = false;
	public $stamp_lines = false;
	public $step = false;

    /**
     * Config paths
     */
    protected $config_dir = null;
    protected $config_file = null;

    /**
     * Stuff
     */
    protected $dt = null;
    protected $run_stamp = '';

    /**
     * Constructor - set up basics
     */
    public function __construct()
    {
        $this->run_stamp = $this->stamp();
    }

    /**
     * Run - parse args and run method specified
     */
    public static function run($argv)
    {
        $class = get_called_class();

        $script = array_shift($argv);
        $method = array_shift($argv);

        $instance = new $class();

        try
        {
            $instance->initConfig();

            if (!in_array($method, $class::METHODS))
            {
                $instance->error("Invalid method - $method");
            }

            $args = [];
            foreach ($argv as $_arg)
            {
                if (strpos($_arg, '--') === 0)
                {
                    $arg = substr($_arg,2);
                    $arg_split = explode("=",$arg,2);

                    if (!isset($arg_split[1]))
                    {
                        $arg_split[1] = true;
                    }

                    $instance->configure($arg_split[0], $arg_split[1]);

                    $instance->pause();
                }
                else
                {
                    $args[]= $_arg;
                }
            }

            call_user_func_array(array($instance, $method), $args);

        } catch (Exception $e) {
            $instance->error($e->getMessage());
        }
    }

    /**
     * Exec - run bash command
     *  - run a command
     *  - return the output
     * @param $error - if true, throw error on bad return
     */
    public function exec($command, $error=false)
    {
        $this->log("exec: $command");
        exec($command, $output, $return);
        $output = empty($output) ? "" : "\n\t" . implode("\n\t", $output);
        if ($return and $error)
        {
            $output = empty($output) ? $return : $output;
            $this->error($output);
        }
        if (!empty($output)) $this->log($output);
        return $output;
    }

	/**
	 * Error output
	 */
	public function error($data, $code=500)
	{
		$this->output('ERROR: ', false);
		$this->output($data);
		if ($code)
		{
			exit($code);
		}
	}

	/**
	 * Warning output
	 */
	public function warning($data)
	{
		$this->output('WARNING: ', false);
		$this->output($data, true, false);
	}

    /**
     * Logging output - only when verbose=true
     */
    public function log($data)
    {
        if (!$this->verbose) return;
        
        $this->output($data);
    }

    /**
     * Output data
     */
    public function output($data, $line_ending=true, $stamp_lines=null)
    {
        if (is_object($data) or is_array($data))
        {
            $data = print_r($data, true);
        }
        else if (is_bool($data))
        {
            $data = $data ? "(Bool) True" : "(Bool) False";
        }

        $stamp_lines = is_null($stamp_lines) ? $this->stamp_lines : $stamp_lines;
		if ($stamp_lines)
			echo $this->stamp() . ' ... ';

		echo $data . ($line_ending ? "\n" : "");
    }

    /**
     * Output horizonal line - divider
     */
    public function hr()
    {
        $this->output("==================================================");
    }

    /**
     * Pause during output for debugging/stepthrough
     */
    public function pause($message="[ ENTER TO STEP | 'FINISH' TO CONTINUE ]")
    {
        if (!$this->step) return;

        $this->hr();

        $this->log($message);

        $line = $this->input();

        if (strtolower(trim($line)) == 'finish')
        {
            $this->step = false;
        }
    }

    /**
     * Get selection from list - from CLI
     * @param $list of items to pick from
     * @param $message to show - prompt
     * @param $default index 
     */
    public function select($list, $message=false,$default=0)
    {
        $list = array_values($list);
        foreach ($list as $i => $item)
        {
            $this->output("$i. $item");
        }

        $max = count($list)-1;
        $s=-1;
        $first = true;
        while ($s < 0 or $s > $max)
        {
            if (!$first)
            {
                $this->warn("Invalid selection $s");
            }
            $s = (int) $this->input($message, $default);
            $first = false;
        }

        return $list[$s];
    }

    /**
     * Get input from CLI
     * @param $message to show - prompt
     */
    public function input($message=false, $default=null)
    {
        if ($message)
        {
            if (!is_null($default))
            {
                $message.= " ($default)";
            }
            $message.= ": ";
            $this->output($message, false);
        }
        $line = fgets($this->getCliInputHandle());
        $line = trim($line);
        return empty($line) ? $default : $line;
    }

    /**
     * Get timestamp
     */
    public function stamp()
    {
        return date('Y-m-d_H.i.s');
    }

    /**
     * Get Config Dir
     */
    public function getConfigDir()
    {
        if (is_null($this->config_dir))
        {
            $this->config_dir = $_SERVER['HOME'] . DS . '.' . static::CONFIG_DIR;
        }

        return $this->config_dir;
    }

    /**
     * Get Config File
     */
    public function getConfigFile()
    {
        if (is_null($this->config_file))
        {
            $config_dir = $this->getConfigDir();
            $this->config_file = $config_dir . DS . 'config.json';
        }

        return $this->config_file;
    }

    /**
     * Init/Load Config File
     */
    public function initConfig()
    {
        $this->log("Console_Abstract::initConfig");

        $config_dir = $this->getConfigDir();
        $config_file = $this->getConfigFile();

        try
        {
            if (!is_dir($config_dir))
            {
                $this->log("Creating directory - $config_dir");
                mkdir($config_dir, 0755);
            }

            if (is_file($config_file))
            {
                $this->log("Loading config file - $config_file");
                $json = file_get_contents($config_file);
                $config = json_decode($json, true);
                foreach ($config as $key => $value)
                {
                    $this->configure($key, $value);
                }
            }
            else
            {
                $this->log("Creating default config file - $config_file");
                $config = [];
                foreach ($this->getPublicProperties() as $property)
                {
                    $config[$property] = $this->$property;
                }
            }

            // Rewrite config - pretty print
            ksort($config);
            $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            file_put_contents($config_file, $json);
        }
        catch (Exception $e)
        {
            // Notify user
            $this->output('NOTICE: ' . $e->getMessage());
        }
    }

    /**
     * Prepare shell argument for use
     * @param $value to prep
     * @param $default to return if $value is empty
     * @param $force_array (?) - whether to split and/or wrap to force it to be an array.
     * Note: defaults to false, or true if $default is an array
     * @param $trim (true) - whether to trim whitespace from value(s)
     */
    public function prepArg($value, $default, $force_array=null, $trim=true)
    {
        if (is_null($force_array))
        {
            $force_array = is_array($default);
        }

        // Default?
        if (empty($value))
        {
            $value = $default;
        }

        // Change to array if needed
        if (is_string($value) and $force_array)
        {
            $value = explode(",", $value);
        }

        // Trim
        if (is_string($value))
        {
            $value = trim($value);
        }
        else if (is_array($value))
        {
            $value = array_map('trim', $value);
        }

        return $value;
    }

    /**
     * Configure property - if public
     */
    public function configure($key, $value)
    {
        $this->log("Configuring - $key:");
        $this->log($value);

        if (substr($key, 0, 3) == 'no-' and $value === true)
        {
            $key = substr($key, 3);
            $value = false;
        }

        $public_properties = $this->getPublicProperties();
        if (in_array($key, $public_properties))
        {
            $this->{$key} = $value;
        }
        else
        {
            $this->output("NOTICE: invalid config key - $key");
        }
    }

    // Manage Properties
    protected $_public_properties = null;
    public function getPublicProperties()
    {
        if (is_null($this->_public_properties))
        {
            $this->_public_properties = [];
            $reflection = new ReflectionObject($this);
            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $prop)
            {
                $this->_public_properties[]= $prop->getName();
            }
        }

        return $this->_public_properties;

    }

    // Manage CLI Input Handle
    protected $_cli_input_handle = null;
    protected function getCliInputHandle()
    {
        if (is_null($this->_cli_input_handle))
        {
            $this->_cli_input_handle = fopen ("php://stdin","r");
        }

        return $this->_cli_input_handle;
    }
    protected function close_cli_input_handle()
    {
        if (!is_null($this->_cli_input_handle))
        {
            fclose($this->_cli_input_handle);
        }
    }
}

/**
 * todo
 * - Automatic install method
 * - Automatic version check and update
 * - no-input flag for scripting
 * - Dynamic help method based on comments
 * - Automatic config file documentation
 * - Pull Console Abstract to it's own repository
 * - Generic config sync from pssh
 * - Generic config backup from pssh
 */

?>
