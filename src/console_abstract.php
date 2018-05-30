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
     * Callable Methods
     */
    protected const METHODS = [
        'help',
    ];

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

            $valid_methods = array_merge($class::METHODS, self::METHODS);
            if (!in_array($method, $valid_methods))
            {
                $instance->help();
                $instance->hr();
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
                }
                else
                {
                    $args[]= $_arg;
                }
            }

            $call_info = "$class->$method(" . implode(",", $args) . ")";
            $instance->log("Calling $call_info");
            $instance->hrl();

            call_user_func_array([$instance, $method], $args);

            $instance->hrl();
            $instance->log("$call_info complete");

        } catch (Exception $e) {
            $instance->error($e->getMessage());
        }
    }

    /**
     * Help - show help/usage
     */
    public function help()
    {
        $methods = array_merge(static::METHODS, self::METHODS);

        $this->hr();
        $this->output("USAGE:");
        $this->output("\t".static::SHORTNAME." <method> (argument1) (argument2) ... [options]\n");
        $this->output("METHODS (ARGUMENTS):");
        foreach($methods as $method)
        {
            $string = "\t" . $method . " ( ";
            $r = new ReflectionObject($this);
            $rm = $r->getMethod($method);
            foreach ($rm->getParameters() as $param)
            {
                $string.= $param->name . " ";
            }
            $string.=")";
            // $string = str_pad($string, 40, ".");
            $this->output($string);
        }
        $this->output("");
        $this->output("OPTIONS:");
        foreach ($this->getPublicProperties() as $property)
        {
            $this->output("\t--$property");
        }
        $this->output("");
        $this->output("Note: for true/false options, prefix no- to set to fales");
        $this->output("      for example: pssh export --no-sync");
        $this->hr();
    }

    /**
     * Exec - run bash command
     *  - run a command
     *  - return the output
     * @param $error - if true, throw error on bad return
     */
    public function exec($command, $error=false)
    {
        $this->log("exec: $command 2>&1");
        exec($command, $output, $return);
        $output = empty($output) ? "" : "\n\t" . implode("\n\t", $output);
        if ($return and $error)
        {
            $output = empty($output) ? $return : $output;
            $this->error($output);
        }
        $this->log($output);
        return $output;
    }

	/**
	 * Error output
	 */
	public function error($data, $code=500)
	{
        $this->hr('!');
		$this->output('ERROR: ', false);
		$this->output($data);
        $this->hr('!');
		if ($code)
		{
			exit($code);
		}
	}

	/**
	 * Warn output
	 */
	public function warn($data)
	{
        $this->hr('*');
		$this->output('WARNING: ', false);
		$this->output($data, true, false);
        $this->hr('*');
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
        else if (!is_string($data))
        {
            ob_start();
            var_dump($data);
            $data = ob_get_clean();
        }

        $stamp_lines = is_null($stamp_lines) ? $this->stamp_lines : $stamp_lines;
		if ($stamp_lines)
			echo $this->stamp() . ' ... ';

		echo $data . ($line_ending ? "\n" : "");
    }

    /**
     * Output break
     */
    public function br()
    {
        $this->output('');
    }

    /**
     * br, but only if logging is on
     */
    public function brl()
    {
        if (!$this->verbose) return;

        $this->br;
    }
    /**
     * Output horizonal line - divider
     */
    public function hr($c='=')
    {
        $string = str_pad("", 60, $c);
        $this->output($string);
    }
    /**
     * hr, but only if logging is on
     */
    public function hrl($c='=')
    {
        if (!$this->verbose) return;

        $this->hr($c);
    }


    /**
     * Pause during output for debugging/stepthrough
     */
    public function pause($message="[ ENTER TO STEP | 'FINISH' TO CONTINUE ]")
    {
        if (!$this->step) return;

        $this->hr();
        $this->output($message);
        $this->hr();

        $line = $this->input();

        if (strtolower(trim($line)) == 'finish')
        {
            $this->step = false;
        }
    }

    /**
     * Get selection from list - from CLI
     * @param (array) $list of items to pick from
     * @param (any) $message (none) to show - prompt
     * @param (int) $default (0) index if no input
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
     * Confirm yes/no
     * @param $message to show - yes/no question
     * @param $default (y) default if no input
     * @return (bool) true/false
     */
    public function confirm($message, $default='y')
    {
        $yn = $this->input($message, $default);

        // True if first letter of response is y or Y
        return strtolower(substr($yn,0,1)) == 'y';
    }

    /**
     * Get input from CLI
     * @param $message to show - prompt
     * @param $default if no input
     * @return input text or default
     */
    public function input($message=false, $default=null, $required=false, $single=false)
    {
        if ($message)
        {
            if (!is_null($default))
            {
                $message.= " ($default)";
            }
            $message.= ": ";
        }

        while (true)
        {
            $this->output($message, false);
            if ($single)
            {
                $line = strtolower( trim( `bash -c "read -n 1 -t 10 INPUT ; echo \\\$INPUT"` ) );
                $this->output('');
                // $line = fgetc($handle);
            }
            else
            {
                $handle = $this->getCliInputHandle();
                $line = fgets($handle);
            }
            $line = trim($line);

            // Entered input - return
            if (!empty($line)) return $line;

            // Input not required? Return default
            if (!$required) return $default;

            // otherwise, warn, loop and try again
            $this->warn("Input required - please try again");
        }


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
            $this->config_dir = $_SERVER['HOME'] . DS . '.' . static::SHORTNAME;
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
        $config_dir = $this->getConfigDir();
        $config_file = $this->getConfigFile();

        try
        {
            if (!is_dir($config_dir))
            {
                // $this->log("Creating directory - $config_dir");
                mkdir($config_dir, 0755);
            }

            if (is_file($config_file))
            {
                // $this->log("Loading config file - $config_file");
                $json = file_get_contents($config_file);
                $config = json_decode($json, true);
                if (empty($config))
                {
                    $this->error("Likely Syntax Error: $config_file");
                }
                foreach ($config as $key => $value)
                {
                    $this->configure($key, $value);
                }
            }
            else
            {
                // $this->log("Creating default config file - $config_file");
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
        $a = func_num_args();
        if ($a < 2) $this->error('prepArg requires value & default');

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
        if ($trim)
        {
            if (is_string($value))
            {
                $value = trim($value);
            }
            else if (is_array($value))
            {
                $value = array_map('trim', $value);
            }
        }

        return $value;
    }

    /**
     * Configure property - if public
     */
    public function configure($key, $value)
    {
        $key = str_replace('-', '_', $key);

        if (substr($key, 0, 3) == 'no_' and $value === true)
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
?>
