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
	protected const KEY_CASE_MAP = array(
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

    protected $data = array(
        "ssh" => array(),
        "pssh" => array(),
        "hosts" => array(),
    );

    protected $shell = null;

    public function __construct($shell)
    {
        $this->shell = $shell;
    }

    /**
     * Read from JSON path
     */
    public function readJSON($path)
    {
        $this->log("readJSON:$path");
		$path_handle = fopen($path, 'r');

		fclose($path_handle);
        $this->log("Done!");
        $this->pause();
    }

    /**
     * Write to JSON path
     */
    public function writeJSON($path)
    {
        $this->log("writeJSON:$path");
		$path_handle = fopen($path, 'w');

		$json = json_encode($this->data, JSON_PRETTY_PRINT);
		fwrite($path_handle, $json);
        fwrite($path_handle, "\n# vim: syntax=json");

		fclose($path_handle);
        $this->log("Done!");
        $this->pause();
    }

    /**
     * Clean Up Data
     */
    public function clean()
    {
        $this->log("clean");
        ksort($this->data['ssh']);
        ksort($this->data['hosts']);

        foreach ($this->data['hosts'] as $alias => &$host)
        {
            $hostname = $host['ssh']['hostname'];

            // Canonicalize to IP Address
            if (!filter_var($hostname, FILTER_VALIDATE_IP))
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

		fclose($path_handle);
        $this->log("Done!");
        $this->pause();
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
