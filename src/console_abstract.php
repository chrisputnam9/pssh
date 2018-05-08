<?php
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
     * Stuff
     */
    protected $dt = null;
    protected $stamp = '';

    /**
     * Constructor - set up basics
     */
    public function __construct()
    {
        $this->dt = new DateTime();
        $this->stamp = $this->dt->format('Y-m-d_H.i.s');
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

            $args = array();
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
		$this->output($data);
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
    public function output($data, $line_ending=true)
    {
        if (is_object($data) or is_array($data))
        {
            $data = print_r($data, true);
        }
        else if (is_bool($data))
        {
            $data = $data ? "(Bool) True" : "(Bool) False";
        }

		if ($this->stamp_lines)
			echo date('Y-m-d H:i:s ... ');

		echo $data . ($line_ending ? "\n" : "");
    }

    /**
     * Pause during output for debugging/stepthrough
     */
    public function pause($message="[ ENTER TO STEP | 'FINISH' TO CONTINUE ]")
    {
        $this->log("----------------------------------------");

        if (!$this->step) return;

        $this->log($message);

        $line = $this->input();

        if (strtolower(trim($line)) == 'finish')
        {
            $this->step = false;
        }
    }

    /**
     * Get input from CLI
     */
    public function input($message=false)
    {
        if ($message)
        {
            $this->output($message, false);
        }
        $line = fgets($this->getCliInputHandle());
        return $line;
    }

    /**
     * Init/Load Config File
     */
    public function initConfig()
    {
        $config_dir = $_SERVER['HOME'] . '/.' . static::CONFIG_DIR;
        $this->config_dir = $config_dir;

        $this->log("initConfig");

        // Config defaults
        $this->json_config_paths = array(
            $config_dir . '/ssh_config_work.json',
            $config_dir . '/ssh_config_personal.json',
        );
        $this->json_import_path = $config_dir . '/ssh_config_imported.json';
        $this->ssh_config_path = $_SERVER['HOME'] . '/.ssh/config';
        $this->backup_dir = $config_dir . '/backups';

        try
        {
            if (!is_dir($config_dir))
            {
                $this->log("Creating directory - $config_dir");
                mkdir($config_dir, 0755);
            }

            if (is_file($config_dir . '/config.json'))
            {
                $this->log("Loading config file - $config_dir/config.json");
                $json = file_get_contents($config_dir . '/config.json');
                $config = json_decode($json, true);
                foreach ($config as $key => $value)
                {
                    $this->configure($key, $value);
                }
            }
            else
            {
                $this->log("Creating default config file - $config_dir/config.json");
                $config = array();
                foreach ($this->getPublicProperties() as $property)
                {
                    $config[$property] = $this->$property;
                }
            }

            // Rewrite config - pretty print
            ksort($config);
            $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            file_put_contents($config_dir . '/config.json', $json);
        }
        catch (Exception $e)
        {
            // Notify user
            $this->output('NOTICE: ' . $e->getMessage());
        }
    }

    /**
     * Configure property - if public
     */
    public function configure($key, $value)
    {
        $this->log("Configuring - $key:");
        $this->log($value);

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
            $this->_public_properties = array();
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
