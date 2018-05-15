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
	public const KEY_CASE_MAP = [
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
	];

    protected $data = null;

    protected $shell = null;

    protected $hosts_by_hostname = null;

    public function __construct($shell)
    {
        $this->shell = $shell;
    }

    /**
     * Get hosts by alias, or all
     * @param $alias - leave out to return all
     */
    public function getHosts($alias=null)
    {
        if (is_null($alias))
        {
            return empty($this->data['hosts']) ? [] : $this->data['hosts'];
        }

        return empty($this->data['hosts'][$alias]) ? [] : [$this->data['hosts'][$alias]];
    }

    /**
     * Get hosts by hostname, or full map
     * @param $hostname - leave out to return all
     */
    public function getHostsByHostname($hostname=null)
    {
        if (is_null($this->hosts_by_hostname))
        {
            $this->hosts_by_hostname = [];
            foreach ($this->getHosts() as $alias => $host)
            {
                if (empty($host['ssh']['hostname'])) continue;

                $_hostname = $host['ssh']['hostname'];
                if (!isset($this->hosts_by_hostname[$_hostname]))
                {
                    $this->hosts_by_hostname[$_hostname] = [];
                }
                $this->hosts_by_hostname[$_hostname][$alias]=$host;
            }
        }

        if (is_null($hostname))
        {
            return $this->hosts_by_hostname;
        }

        return empty($this->hosts_by_hostname[$hostname]) ? [] : $this->hosts_by_hostname[$hostname];
    }

    /**
     * Add host
     * @param $alias - alias for the host
     * @param $host - array of host info: ssh & pssh
     * @param $force - force add, generate unique host if needed
     * @return true if successful, false if failed
     * - If exact copy of host already is in this config, that counts as success
     * - On failure, host and alias are updated to prep for override
     */
    public function add(&$alias, &$host, $force=false)
    {
        $hostname = empty($host['ssh']['hostname']) ? null : $host['ssh']['hostname'];
        $user = empty($host['ssh']['user']) ? null : $host['ssh']['user'];

        $search = [
            'alias' => $alias,
            'hostname' => $hostname,
            'user' => $user,
        ];

        // Look up host in target by hostname & user
        $existing = $this->find($search);

        $existing_alias = $existing['alias'];

        $existing_config_aliases = [];
        $existing_config_alias = [];
        $existing_config = [];
        if (!is_null($hostname) and !is_null($user))
        {
            // should be only one, so let's just look at the first one
            $existing_config_aliases = array_keys($existing['hostname'][$user]);
            $existing_config_alias = array_shift($existing_config_aliases);
            $existing_config = array_shift($existing['hostname'][$user]);
        }

        $override_host = [];

        if (!empty($existing_config) and !$force)
        {
            // Remove info from config that's identical to existing
            $override_host = $this->host_diff($host, $existing_config);
            
            // New alias for same config?
            if (
                empty($override_host['pssh']['alias']) and
                empty($existing_alias[$alias])
            )
            {
                $override_host['pssh']['alias'] = $alias;
            }
            
        }
        else// no config match, or forcing override
        {

            $alias = $this->autoAlias($alias);

            $this->data['hosts'][$alias] = $host;

            return true;
        }

        if (empty($override_host))
        {
            return true;
        }

        $alias = $existing_config_alias;
        $host = $override_host;
        return false;
    }

    /**
     * Find existing host by alias/hostname/user
     * @param $host - alias or array of data:
     *      [
     *          'alias' => $alias,
     *          'hostname' => $hostname,
     *          'user' => $user,
     *      ]
     *      NOTE: will only search user if IP is specified
     * @return array of host(s) that were found
     *  - indexed by what they matched (alias or hostname=>user)
     *  - each array will be empty if none found for that criteria
     *      [
     *          'alias' => [
     *              '<alias>' => $host
     *          ]
     *          'hostname' => [
     *              '<username>' => [
     *                  '<alias>' => $host
     *              ]
     *          ]
     *      ]
     */
    public function find($search)
    {
        // The info to search by
        $alias=null;
        $hostname=null;
        $user=null;

        $return = [
            'alias' => [],
            'hostname' => [],
        ];

        // String? Assume it's alias
        if (is_string($search))
        {
            $alias = $search;
        }

        // Parse out key values
        if (is_array($search))
        {
            $alias = empty($search['alias']) ? null : trim($search['alias']);
            $hostname = empty($search['hostname']) ? null : trim($search['hostname']);
            $user = empty($search['user']) ? null : trim($search['user']);
        }

        if (!empty($alias))
        {
            $return['alias'] = [$alias => $this->getHosts($alias)];
        }

        if (!empty($hostname))
        {
            $hosts = $this->getHostsByHostname($hostname);

            if (!empty($user))
            {
                $return['hostname'][$user] = [];
            }

            foreach($hosts as $alias => $host)
            {
                $_user = $host['ssh']['user'];
                if (empty($user) or $user == $_user)
                {
                    if (!isset($return['hostname'][$_user]))
                    {
                        $return['hostname'][$_user] = [];
                    }

                    $return['hostname'][$_user][$alias]= $host;
                }
            }
        }

        return $return;

    }

    /**
     * Recursively diff host info as though creating an override  
     * @param $host1 - Primary host - return this minus second
     * @param $host2 - Subtract info identical info in host2 from host1
     */
    public function host_diff($host1, $host2, $p="")
    {
        foreach ($host1 as $key => $value1)
        {
            // $this->log($p.$key);
            if (isset($host2[$key]))
            {
                $value2 = $host2[$key];

                if (is_array($value1) and is_array($value2))
                {
                    // $this->log($p.'RECUR');
                    $host1[$key] = $this->host_diff($value1, $value2, $p."-");
                    if (empty($host1[$key]))
                    {
                        // $this->log($p.'REMOVE');
                        unset($host1[$key]);
                    }
                }
                else
                {
                    if ($value1 == $value2)
                    {
                        // $this->log($p.'REMOVE');
                        unset($host1[$key]);
                    }
                }
            }
        }

        return $host1;
    }

    /**
     * Merge hosts into target
     * @param (PSSH_Config) $target - target of merge
     * @param (PSSH_Config) $override - overrides go here when conflicts arise
     */
    public function merge($target, $override)
    {
        $init = $this->initData();

        foreach ($this->getHosts() as $alias => $host)
        {
            $success = $target->add($alias, $host);
            if (!$success)
            {
                // force it into override file
                $override->add($alias, $host, true);
            }
        }
    }

    /**
     * Read from JSON path(s)
     * @param $paths - string or array of strings for multiple paths to read from
     */
    public function readJSON($paths)
    {
        $init = $this->initData();

        $paths = $this->prepArg($paths, []);

        $unmerged_data = [];

        // $this->log('Loading json files:');
        foreach ($paths as $path)
        {
            // $this->log(" - $path");

            if (!file_exists($path))
            {
                // $this->log(" --- file doesn't exist, will be created");
                continue;
            }

            $json = file_get_contents($path);
            $decoded = json_decode($json, true);
            if (empty($decoded))
            {
                $this->error("Likely Syntax Error: $path");
            }

            $unmerged_data[] = $decoded;
        }

        if (count($unmerged_data) == 1)
        {
            $this->data = $unmerged_data[0];
        }
        elseif (count($unmerged_data) > 1)
        {
            $this->data = call_user_func_array('array_replace_recursive', $unmerged_data);
        }
    }

    /**
     * Write to JSON path
     * @param $path - string path to write to
     */
    public function writeJSON($path)
    {
		$json = json_encode($this->data, JSON_PRETTY_PRINT);
		file_put_contents($path, $json);
    }

    /**
     * Clean Up Data
     * - change hostnames to IP
     * - set up default alias
     * - set up default ssh key
     */
    public function clean()
    {
        $init = $this->initData();

        if (empty($this->data['ssh']['identityfile']))
        {
            $this->data['ssh']['identityfile'] = 'id_rsa';
        }

        ksort($this->data['ssh']);
        ksort($this->data['hosts']);

        foreach ($this->data['hosts'] as $alias => &$host)
        {
            $hostname = empty($host['ssh']['hostname']) ? false : $host['ssh']['hostname'];

            // Set up alias
            if (empty($host['pssh']['alias']))
            {
                $host['pssh']['alias'] = $alias;
            }

            if (!empty($hostname))
            {
                $host['ssh']['hostname'] = $this->cleanHostname($host['ssh']['hostname']);
            }
        }
    }

    /**
     * Read from SSH path
     * @param $path - string path to read from
     */
    public function readSSH($path)
    {
		$path_handle = fopen($path, 'r');
        $init = $this->initData();

		$original_keys = [];

		$l = 0;
		while ($line = fgets($path_handle))
		{
			$l++;
			$line = trim($line);

			// $this->log("$l: $line");

            // Skip Blank Lines
            if (empty($line))
            {
				// $this->log(' - blank - skipping');
				continue;
            }

			// Skip Comments
			if (strpos($line,'#') === 0)
			{
				// $this->log(' - comment - skipping');
				continue;
			}

			// Parse into key and value
			if (preg_match('/^(\S+)\s+(.*)$/', $line, $match))
			{
				$key = strtolower($match[1]);
				$value = trim($match[2]);

                if (!isset(self::KEY_CASE_MAP[$key]))
                {
                    $original_keys[$key]= $match[1];
                }


				// $this->log(" - Parsed as [$key => $value]");
				if ($key == 'host')
				{
					$host = $value;
					$this->data['hosts'][$host] = [
						'ssh' => [],
						'pssh' => [],
					];
				}
				else
				{
					if (empty($host))
					{
						// $this->log(" - Determined to be general config");
						$this->data['ssh'][$key] = $value;
					}
					else
					{
						// $this->log(" - Adding to hosts[$host][ssh]");
						$this->data['hosts'][$host]['ssh'][$key] = $value;
					}
				}
			}
			else
			{
				$this->error("Unexpected syntax - check $path line $l");
			}
		}

        // Warn about any new keys our mapping didn't have
        if (!empty($original_keys))
        {
            $original_keys = array_unique($original_keys);
            sort($original_keys);
            $this->warn('New Keys Present - make sure these get added to PSSH_Config::KEY_CASE_MAP');
            $this->output($original_keys);
        }

		fclose($path_handle);
    }

    /**
     * Write to SSH path
     * @param $path - string path to read to
     */
    public function writeSSH($path)
    {
		$path_handle = fopen($path, 'w');

        // $this->log("Outputting Comment");
        fwrite($path_handle, "# ---------------------------------------\n");
        fwrite($path_handle, "# Generated by PSSH - {$this->stamp()}\n");
        fwrite($path_handle, "#   - DO NOT EDIT THIS FILE, USE PSSH\n");
        fwrite($path_handle, "# ---------------------------------------\n");

        // $this->log("Outputting General Config");
        fwrite($path_handle, "\n");
        fwrite($path_handle, "# ---------------------------------------\n");
        fwrite($path_handle, "# General Config\n");
        fwrite($path_handle, "# ---------------------------------------\n");
        foreach ($this->data['ssh'] as $key => $value)
        {
            // $this->log(" - $key: $value");
            $Key = isset(self::KEY_CASE_MAP[$key]) ? self::KEY_CASE_MAP[$key] : ucwords($key);
            fwrite($path_handle, $Key . ' ' . $value . "\n");
        }

        // $this->log("Outputting Hosts Config");
        fwrite($path_handle, "\n");
        fwrite($path_handle, "# ---------------------------------------\n");
        fwrite($path_handle, "# HOSTS\n");
        fwrite($path_handle, "# ---------------------------------------\n");
        foreach ($this->getHosts() as $alias => $host_config)
        {
            if (!empty($host_config['pssh']['alias']))
            {
                $alias = $host_config['pssh']['alias'];
            }
            // $this->log(" - $alias");
            fwrite($path_handle, 'Host ' . $alias . "\n");
            foreach ($host_config['ssh'] as $key => $value)
            {
                // $this->log("    - $key: $value");
                $Key = isset(self::KEY_CASE_MAP[$key]) ? self::KEY_CASE_MAP[$key] : ucwords($key);
                fwrite($path_handle, '    ' . $Key . ' ' . $value . "\n");
            }
        }

        // $this->log("Outputting Vim Syntax Comment");
        fwrite($path_handle, "\n# vim: syntax=sshconfig");

		fclose($path_handle);
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
            $this->data = [
                "ssh" => [],
                "pssh" => [],
                "hosts" => [],
            ];
            return true;
        }

        return false;// no init was needed
    }

    /**
     * Pass through functions for shell
     */
    public function __call($method, $arguments)
    {
        $shell_call = [$this->shell, $method];
        if (is_callable($shell_call))
        {
            return call_user_func_array($shell_call, $arguments);
        }
    }

    /**
     * Helper Functions
     */

    public function autoAlias($alias)
    {
        // Add on 1/2/3... as needed to make sure alias is unique
        $i=0;
        $new_alias = $alias;
        while (isset($this->data['hosts'][$new_alias]))
        {
            $i++;
            $new_alias = $alias.$i;
        }

        return $new_alias;
    }

    public function cleanHostname($hostname)
    {
        // Canonicalize to IP Address
        if (!empty($hostname) and !filter_var($hostname, FILTER_VALIDATE_IP))
        {
            // $this->log("Looking up $hostname");
            $info = dns_get_record($hostname, DNS_A);
            if (
                empty($info)
                or empty($info[0]['ip'])
                or !filter_var($info[0]['ip'], FILTER_VALIDATE_IP)
            ){
                $this->warn("Failed lookup - $hostname");
                return $hostname;
            }

            $ip = $info[0]['ip'];

            if (filter_var($ip, FILTER_VALIDATE_IP))
            {
                $hostname = $ip;
            }
        }
        else
        {
            // $this->log("Hostname Approved ($hostname)");
        }

        return $hostname;
    }

}
?>
