<?php
/**
 * PSSH Config Object
 *  - manage config data
 *  - read/write ssh/json
 */
class PSSH_Config
{
	/**
	 * Map of keys to preferred case
	 */
	public const KEY_CASE_MAP = array(
		'hostname' => 'HostName',
		'identitiesonly' => 'IdentitiesOnly',
		'identityfile' => 'IdentityFile',
		'kexalgorithms' => 'KexAlgorithms',
		'loglevel' => 'LogLevel',
		'passwordauthentication' => 'PasswordAuthentication',
		'port' => 'Port',
		'stricthostkeychecking' => 'StrictHostKeyChecking',
		'user' => 'User',
		'userknownhostsfile' => 'UserKnownHostsFile',
	);

    protected $data = null;
    protected $shell = null;

    public function __construct($shell)
    {
        $this->shell = $shell;
    }

    /**
     * Read from JSON path(s)
     * @param $path - string or array of strings for multiple paths
     */
    public function readJSON($path)
    {
        $this->log("readJSON:");
        $this->log($path);

        $init = $this->initData();
        $paths = is_array($path) ? $path : array($path);
        $unmerged_data = array();

        $this->log('Loading json files:');
        foreach ($paths as $path)
        {
            $this->log(" - $path");
            $json = file_get_contents($path);
            $decoded = json_decode($json, true);
            if (empty($decoded))
            {
                $this->error("Likely Syntax Error: $path");
            }
            $unmerged_data[] = $decoded;
        }

        $this->log($unmerged_data);

        if (count($unmerged_data) == 1)
        {
            $this->data = $unmerged_data[0];
        }
        else
        {
            $this->data = call_user_func_array('array_merge_recursive', $unmerged_data);
        }

        $this->log("Done!");
        $this->pause();
    }

    /**
     * Write to JSON path
     */
    public function writeJSON($path)
    {
        $this->log("writeJSON:$path");

		$json = json_encode($this->data, JSON_PRETTY_PRINT);
		file_put_contents($path, $json);

        $this->log("Done!");
        $this->pause();
    }

    /**
     * Clean Up Data
     */
    public function clean()
    {
        $this->log("clean");

        $init = $this->initData();

        ksort($this->data['ssh']);
        ksort($this->data['hosts']);

        foreach ($this->data['hosts'] as $alias => &$host)
        {
            $hostname = empty($host['ssh']['hostname']) ? false : $host['ssh']['hostname'];

            // Canonicalize to IP Address
            if (!empty($hostname) and !filter_var($hostname, FILTER_VALIDATE_IP))
            {
                $this->log("Looking up $hostname");
                $info = dns_get_record($hostname, DNS_A);
                $this->log($info);
                if (
                    empty($info)
                    or empty($info[0]['ip'])
                    or !filter_var($info[0]['ip'], FILTER_VALIDATE_IP)
                ){
                    $this->warning("Failed lookup - $hostname");
                    continue;
                }

                $ip = $info[0]['ip'];

                if (filter_var($ip, FILTER_VALIDATE_IP))
                {
                    $this->log("$hostname => $ip");
                    $host['ssh']['hostname'] = $ip;
                }
            }
            else
            {
                $this->log("Hostname Approved ($hostname)");
            }
            $this->pause();
        }

        $this->log("Done!");
        $this->pause();
    }

    /**
     * Read from SSH path
     */
    public function readSSH($path)
    {
        $this->log("readSSH:$path");
		$path_handle = fopen($path, 'r');
        $init = $this->initData();

		$original_keys = array();

		$l = 0;
		while ($line = fgets($path_handle))
		{
			$l++;
			$line = trim($line);

			$this->log("$l: $line");

            // Skip Blank Lines
            if (empty($line))
            {
				$this->log(' - blank - skipping');
				continue;
            }

			// Skip Comments
			if (strpos($line,'#') === 0)
			{
				$this->log(' - comment - skipping');
				continue;
			}

			// Parse into key and value
			if (preg_match('/^(\S+)\s+(.*)$/', $line, $match))
			{
				$original_keys[]= $match[1];
				$key = strtolower($match[1]);
				$value = trim($match[2]);

				$this->log(" - Parsed as [$key => $value]");
				if ($key == 'host')
				{
					$host = $value;
					$this->data['hosts'][$host] = array(
						'ssh' => array(),
						'pssh' => array(),
					);
				}
				else
				{
					if (empty($host))
					{
						$this->log(" - Determined to be general config");
						$this->data['ssh'][$key] = $value;
					}
					else
					{
						$this->log(" - Adding to hosts[$host][ssh]");
						$this->data['hosts'][$host]['ssh'][$key] = $value;
					}
				}
			}
			else
			{
				$this->error("Unexpected syntax - check $path line $l");
			}

			$this->pause();
		}

		$original_keys = array_unique($original_keys);
		sort($original_keys);
		$this->log('Keys Present:');
		$this->log($original_keys);


		fclose($path_handle);
        $this->log("Done!");
        $this->pause();
    }

    /**
     * Write to SSH path
     */
    public function writeSSH($path)
    {
        $this->log("writeSSH:$path");
		$path_handle = fopen($path, 'w');

        $this->log("Outputting Comment");
        fwrite($path_handle, "# ---------------------------------------\n");
        fwrite($path_handle, "# Generated by PSSH - $this->stamp()\n");
        fwrite($path_handle, "#   - DO NOT EDIT THIS FILE, USE PSSH\n");
        fwrite($path_handle, "# ---------------------------------------\n");

        $this->log("Outputting General Config");
        fwrite($path_handle, "\n");
        fwrite($path_handle, "# ---------------------------------------\n");
        fwrite($path_handle, "# General Config\n");
        fwrite($path_handle, "# ---------------------------------------\n");
        foreach ($data['ssh'] as $key => $value)
        {
            $this->log(" - $key: $value");
            $Key = isset(PSSH_Config::KEY_CASE_MAP[$key]) ? PSSH_Config::KEY_CASE_MAP[$key] : ucwords($key);
            fwrite($path_handle, $Key . ' ' . $value . "\n");
        }

        $this->log("Outputting Hosts Config");
        fwrite($path_handle, "\n");
        fwrite($path_handle, "# ---------------------------------------\n");
        fwrite($path_handle, "# HOSTS\n");
        fwrite($path_handle, "# ---------------------------------------\n");
        foreach ($data['hosts'] as $host => $host_config)
        {
            $this->log(" - $host");
            fwrite($path_handle, 'Host ' . $host . "\n");
            foreach ($host_config['ssh'] as $key => $value)
            {
                $this->log("    - $key: $value");
                $Key = isset(PSSH_Config::KEY_CASE_MAP[$key]) ? PSSH_Config::KEY_CASE_MAP[$key] : ucwords($key);
                fwrite($path_handle, '    ' . $Key . ' ' . $value . "\n");
            }
        }

        $this->log("Outputting Vim Syntax Comment");
        fwrite($path_handle, "\n# vim: syntax=sshconfig");

		fclose($path_handle);
        $this->log("Done!");
        $this->pause();
    }

    /**
     * Initialize data
     *  - return true if it was empty (null)
     *  - return false if it was not
     */
    public function initData()
    {
        if (is_null($this->data))
        {
            $this->data = array(
                "ssh" => array(),
                "pssh" => array(),
                "hosts" => array(),
            );
            return true;
        }

        return false;// no init was needed
    }

    /**
     * Pass through functions for shell
     */
    public function __call($method, $arguments)
    {
        $shell_call = array($this->shell, $method);
        if (is_callable($shell_call))
        {
            call_user_func_array($shell_call, $arguments);
        }
    }

}
?>
