<?php
/**
 * PSSH Class File
 *
 * @package pssh
 * @author  chrisputnam9
 */

/**
 * PSSH Config Class
 *
 *  - Provides methods to manage SSH config data
 *  - Used by PSSH which provides the interface
 */
class PSSH_Config
{
    /**
     * Map of keys to preferred case
     *
     *  - Loaded from PSSH_CONFIG_KEYS - see end of file
     *
     * @var array
     */
    public static $CONFIG_KEYS = null;

    /**
     * The loaded SSH config data
     *
     * @var array
     */
    protected $data = null;

    /**
     * The team SSH Keys
     *
     *  - Loaded from SSH config data
     *
     * @var array
     */
    protected $team_keys = null;

    /**
     * The team SSH keys identifier
     *
     *  - Outputs in comment before and after team keys on servers
     *  - Loaded from SSH config data
     *  - Defaults to 'team keys' if not set in config data
     *
     * @var string
     */
    protected $team_keys_identifier = null;

    /**
     * The PHP Console tool class - instance of Console_Abstract
     *
     *  - Used to forward method calls via __call
     *
     * @var Console_Abstract
     */
    protected $main_tool = null;

    /**
     * A map of all aliases to their host key
     *
     * @var array
     */
    protected $alias_map = null;

    /**
     * All hosts from loaded SSH config data, keyed by hostname (IP/URL)
     *
     * @var array
     */
    protected $hosts_by_hostname = null;

    /**
     * Whether the configuration data is in a state where it's OK to be exported.
     *
     * @var boolean
     */
    protected $is_exportable = null;

    /**
     * Constructor - sets $main_tool property
     *
     * @param Console_Abstract $main_tool Instance of PHP Console tool class to which to forward method calls via __call.
     */
    public function __construct(Console_Abstract $main_tool)
    {
        $this->main_tool = $main_tool;
    }//end __construct()

    /*******************************************************************************************
     * Primary methods
     ******************************************************************************************/

    /**
     * Add a new SSH host to the loaded configuration
     *
     * @param string  $alias Alias for the host being added.
     *                        - passed by reference and may be updated to be unique.
     * @param array   $host  Host information to be added (hostname, port, user, etc).
     *                        - passed by reference and may be updated to prep override.
     * @param boolean $force Force add regardless of existing similar host
     *                        - will generate unique alias if needed to avoid overwrite.
     *
     * @return boolean true if successful, false if failed
     *          - If exact copy of host already is in this config, that counts as success
     *          - On failure, host and alias are updated to prep for override
     */
    public function add(string &$alias, array &$host, bool $force = false): bool
    {
        $hostname = empty($host['ssh']['hostname']) ? null : $host['ssh']['hostname'];
        $port = empty($host['ssh']['port']) ? null : $host['ssh']['port'];
        $user = empty($host['ssh']['user']) ? null : $host['ssh']['user'];

        $search = [
            'alias' => $alias,
            'hostname' => $hostname,
            'port' => $port,
            'user' => $user,
        ];

        // Look up host in target by hostname & user
        $existing = $this->find($search);

        $existing_alias = $existing['alias'];

        $existing_config_aliases = [];
        $existing_config_alias = [];
        $existing_config = [];
        if (!is_null($hostname) and !is_null($user)) {
            foreach ($existing['hostname'][$user] as $_alias => $_host) {
                $existing_config_aliases = array_keys($existing['hostname'][$user]);
                $existing_config_alias = array_shift($existing_config_aliases);
                $existing_config = array_shift($existing['hostname'][$user]);
            }
        }

        $override_host = [];

        if (!empty($existing_config) and !$force) {
            // Remove info from config that's identical to existing
            $override_host = $this->host_diff($host, $existing_config);

            // New alias for same config?
            if (
                empty($override_host['pssh']['alias']) and
                empty($existing_alias[$alias])
            ) {
                $override_host['pssh']['alias'] = $alias;
            }
        } else {
            // no config match, or forcing override
            $alias = $this->autoAlias($alias);

            $this->data['hosts'][$alias] = $host;

            return true;
        }

        if (empty($override_host)) {
            return true;
        }

        $alias = $existing_config_alias;
        $host = $override_host;
        return false;
    }//end add()

    /**
     * Clean Up Data
     * - change hostnames to IP
     * - set up default alias
     * - set up default ssh key
     * - sort data by keys
     *
     * @return void
     */
    public function clean()
    {
        // Assume at the start of cleaning that config is exportable
        // - will be set to false if any issues are found
        $this->is_exportable = true;

        $init = $this->initData();

        if (!empty($this->data['ssh'])) {
            ksort($this->data['ssh']);
        }

        if (!empty($this->data['pssh'])) {
            ksort($this->data['pssh']);
        }

        $cleaned_hosts = [];
        $host_index = 0;
        foreach ($this->data['hosts'] as $old_key => $host) {
            // Make sure host is an array as expected
            if (!is_array($host)) {
                $this->is_exportable = false;
                $this->error("Host data with key '$old_key' is not an array - this is unexpected. Please edit the JSON configuration files (in ~/.pssh) manually to resolve this.");
            }
            $old_key = trim($old_key);

            // Set up default data structure
            if (empty($host['pssh'])) {
                $host['pssh'] = [];
            }
            if (empty($host['ssh'])) {
                $host['ssh'] = [];
            }

            // Set up aliases
            if (empty($host['pssh']['alias'])) {
                // Default alias to be same as key if not set
                $host['pssh']['alias'] = $old_key;
            }
            if (empty($host['pssh']['alias_additional'])) {
                // Default empty alias_additional just for easy editing
                $host['pssh']['alias_additional'] = [];
            }

            $this->cleanHostname($host, $old_key);
            $this->cleanPort($host, $old_key);
            $this->cleanUser($host, $old_key);
            $this->cleanAliases($host, $old_key);

            // Standardize Key to Align with Alias if possible
            $new_key = $old_key;
            $potential_new_key = $host['pssh']['alias'];
            if ($potential_new_key !== $old_key) {
                if (empty($this->data['hosts'][$potential_new_key]) && empty($cleaned_hosts[$potential_new_key])) {
                    $this->warn(
                        "Changing a host key from '$old_key' to '$potential_new_key' to align with primary alias.\n" .
                        "If this was not intended, consider adding a new alias via alias_additional instead."
                    );
                    $new_key = $potential_new_key;
                } else {
                    $this->is_exportable = false;
                    $this->warn(
                        "Unable to update host key from '$old_key' to '$potential_new_key' to align with primary alias.\n" .
                        "Key '$potential_new_key' is already in use by another host.\n" .
                        "The situation can be resolved by manually editing the JSON configuration files (in ~/.pssh)"
                    );
                }
            }
            $cleaned_hosts[$new_key] = $host;

            $host_index++;
        }//end foreach

        ksort($cleaned_hosts);

        if (count($this->data['hosts']) !== count($cleaned_hosts)) {
            $this->is_exportable = false;
            $this->error(
                "Aborting clean - something went wrong and the cleaned host count would differ from the original host count.\n" .
                "Aborting to avoid losing data - this is very likely indicative of a bug and should be reported.\n" .
                "Review JSON configuration files (in ~/.pssh) manually to isolate a potential cause."
            );
        }

        $this->data['hosts'] = $cleaned_hosts;

        // Refresh Alias Map
        $this->getAliasMap('fresh');
    }//end clean()

    /**
     * Find a matching host configuration by alias/hostname/user
     *
     * @param mixed $search Either an alias or an array of data to search for:
     *                          [
     *                              'alias' => $alias,
     *                              'hostname' => $hostname,
     *                              'user' => $user,
     *                          ]
     *                          NOTE: will only search user if IP is specified, since otherwise there could be a lot of matches.
     *
     * @return array The host(s) that were found, if any
     *                - indexed by what matched (alias or hostname => user)
     *                - each array will be empty if none found for that criteria
     *                  [
     *                      'alias' => [
     *                          '<alias>' => $host,
     *                          ...
     *                      ],
     *                      'hostname' => [
     *                          '<username>' => [
     *                              '<alias>' => $host,
     *                              ...
     *                          ],
     *                          ...
     *                      ],
     *                  ]
     */
    public function find($search): array
    {
        // The info to search by
        $alias = null;
        $hostname = null;
        $port = null;
        $user = null;

        $return = [
            'alias' => [],
            'hostname' => [],
        ];

        // String? Assume it's alias
        if (is_string($search)) {
            $alias = $search;
        }

        // Parse out key values
        if (is_array($search)) {
            $alias = empty($search['alias']) ? null : trim($search['alias']);
            $hostname = empty($search['hostname']) ? null : trim($search['hostname']);
            $port = empty($search['port']) ? null : trim($search['port']);
            $user = empty($search['user']) ? null : trim($search['user']);
        }

        if (!empty($alias)) {
            $return['alias'] = [$alias => $this->getHosts($alias)];
        }

        if (!empty($hostname)) {
            $hosts = $this->getHostsByHostname($hostname);

            if (!empty($user)) {
                $return['hostname'][$user] = [];
            }

            foreach ($hosts as $alias => $host) {
                $_user = $host['ssh']['user'];
                $_port = $host['ssh']['port'] ?? 22;
                if (
                    ( empty($port) or $port == $_port )
                    and ( empty($user) or $user == $_user )
                ) {
                    if (!isset($return['hostname'][$_user])) {
                        $return['hostname'][$_user] = [];
                    }

                    $return['hostname'][$_user][$alias] = $host;
                }
            }
        }//end if

        return $return;
    }//end find()

    /**
     * Get a map of aliases to host keys
     *
     * @param boolean $fresh If true, will re-fetch the alias map instead of using cached version.
     *
     * @return array Array map with all configured aliases as keys and host config keys as values.
     */
    public function getAliasMap(bool $fresh = false): array
    {
        if ($fresh) {
            $this->alias_map = null;
        }

        if (is_null($this->alias_map)) {
            $this->alias_map = [];
            foreach ($this->getHosts() as $key => $host) {
                $aliases = array_merge(
                    [$host['pssh']['alias']],
                    $host['pssh']['alias_additional'] ?? []
                );
                foreach ($aliases as $alias) {
                    if (isset($this->alias_map[$alias])) {
                        $prior_key = $this->alias_map[$alias];
                        $this->is_exportable = false;
                        $this->warn(
                            "Duplicate alias - both host '$prior_key' and '$key' have the same alias specified ($alias).\n" .
                            "Host '$prior_key' will take precedence for now.\n" .
                            "Edit or delete hosts as needed to resolve this conflict."
                        );
                    } else {
                        $this->alias_map[$alias] = $key;
                    }
                }
            }
        }//end if
        return $this->alias_map;
    }//end getAliasMap()

    /**
     * Get hosts by alias, or all
     *
     * @param string $alias Specific host alias to retrieve - otherwise returns all hosts.
     *
     * @return array Array of all hosts found - or empty array if none found.
     */
    public function getHosts(string $alias = null): array
    {
        if (is_null($alias)) {
            return empty($this->data['hosts']) ? [] : $this->data['hosts'];
        }

        // If alias specified, get host with that alias, if any
        $key = $this->getHostKey($alias);
        if ($key && isset($this->data['hosts'][$key])) {
            return [$this->data['hosts'][$key]];
        }

        return [];
    }//end getHosts()

    /**
     * Delete hosts by alias
     *
     * @param string $alias Alias of the host to be deleted.
     *
     * @return boolean Whether the host existed prior to being removed.
     */
    public function deleteHost(string $alias): bool
    {
        if (!empty($alias) and isset($this->data['hosts'][$alias])) {
            unset($this->data['hosts'][$alias]);
            return true;
        }

        return false;
    }//end deleteHost()

    /**
     * Add (or update) a host with the given alias with the provided data
     *
     * @param string $alias The alias of the host to be added (or updated).
     * @param array  $data  The data to set for the host.
     *
     * @return void
     */
    public function setHost(string $alias, array $data)
    {
        // First, we have to get the actual unique *key* of the host with this alias
        $key = $this->getHostKey($alias);

        $this->data['hosts'][$key] = $data;
    }//end setHost()

    /**
     * Get the unique host key, given an alias
     *
     * @param string $alias The alias of the host to look for.
     *
     * @return mixed string The unique host key or false if none found.
     */
    public function getHostKey(string $alias)
    {
        $alias_map = $this->getAliasMap();
        return $alias_map[$alias] ?? false;
    }//end getHostKey()


    /**
     * Get hosts by hostname (domain or IP)
     *
     *  - If $hostname is specified, return a host that uses the given hostname - if any
     *  - Otherwise, return a full array map of hosts, keyed by hostname
     *
     * @param string $hostname An optional specific hostname to look for.
     *
     * @return array Either the full array map of keys to hosts,
     *                the specific host data found with given $hostname,
     *                or an empty array if none found.
     */
    public function getHostsByHostname(string $hostname = null): array
    {
        if (is_null($this->hosts_by_hostname)) {
            $this->hosts_by_hostname = [];
            foreach ($this->getHosts() as $key => $host) {
                if (empty($host['ssh']['hostname'])) {
                    continue;
                }

                $_hostname = $host['ssh']['hostname'];
                if (!isset($this->hosts_by_hostname[$_hostname])) {
                    $this->hosts_by_hostname[$_hostname] = [];
                }
                $this->hosts_by_hostname[$_hostname][$key] = $host;
            }
        }

        if (is_null($hostname)) {
            return $this->hosts_by_hostname;
        }

        return empty($this->hosts_by_hostname[$hostname]) ? [] : $this->hosts_by_hostname[$hostname];
    }//end getHostsByHostname()

    /**
     * Get team keys based on loaded config
     *
     * @return array The team key list.
     */
    public function getTeamKeys(): array
    {
        if (is_null($this->team_keys)) {
            $this->log("Reading in team keys...");
            $this->team_keys = array();
            if (!empty($this->data['pssh']) and !empty($this->data['pssh']['team_keys'])) {
                $raw = file_get_contents($this->data['pssh']['team_keys']);

                $data = json_decode($raw, true);
                if ($data) {
                    $this->team_keys = $data;
                }
            }
        }
        return $this->team_keys;
    }//end getTeamKeys()

    /**
     * Get Team Keys Identifier
     *
     * @return string The team key identifier string.
     */
    public function getTeamKeysIdentifier()
    {
        if (is_null($this->team_keys_identifier)) {
            $this->team_keys_identifier = 'team keys';
            $this->log("Reading in team keys...");
            $this->team_keys_identifier = array();
            if (!empty($this->data['pssh']) and !empty($this->data['pssh']['team_keys_identifier'])) {
                $this->team_keys_identifier = $this->data['pssh']['team_keys_identifier'];
            }
        }
        return $this->team_keys_identifier;
    }//end getTeamKeysIdentifier()


    /**
     * Recursively diff host info as though creating an override
     *  - Leave only data that differs from the data in the second host being compared
     *
     * @param array $host1 The primary host.
     * @param array $host2 The host whose data will be subtracted from $host1.
     *
     * @return array resulting data in $host1 that is not present or differs from data in $host2.
     */
    public function host_diff(array $host1, array $host2): array
    {
        foreach ($host1 as $key => $value1) {
            if (!empty($host2[$key])) {
                $value2 = $host2[$key];

                if (is_array($value1) and is_array($value2)) {
                    $host1[$key] = $this->host_diff($value1, $value2);
                    if (empty($host1[$key])) {
                        unset($host1[$key]);
                    }
                } else {
                    if ($value1 == $value2) {
                        unset($host1[$key]);
                    }
                }
            }
        }

        return $host1;
    }//end host_diff()

    /**
     * Merge the loaded host configurations into a separate set of host configurations (instance of PSSH_Config).
     *
     *  - When there are conflicts, they will be placed in a separate set of host configurations for manual review
     *
     * @param PSSH_Config $target   Configuration to merge into.
     * @param PSSH_Config $override Configuration where conflicts are placed for manual review.
     *
     * @return void
     */
    public function merge(PSSH_Config $target, PSSH_Config $override)
    {
        $init = $this->initData();

        foreach ($this->getHosts() as $alias => $host) {
            $success = $target->add($alias, $host);
            if (!$success) {
                // force it into override file
                $override->add($alias, $host, true);
            }
        }
    }//end merge()

    /**
     * Read from JSON path(s) - load host configurations into this instance.
     *
     * @param mixed $paths Path(s) to JSON files from which to load configuration.
     *
     * @throws Exception If there is an error reading the JSON file.
     *
     * @return void
     */
    public function readJSON($paths)
    {
        $init = $this->initData();

        // Clean out derivitive data
        $this->resetDerivedData();

        $paths = $this->prepArg($paths, []);

        $unmerged_data = [];

        // $this->log('Loading json files:');
        foreach ($paths as $path) {
            // $this->log(" - $path");
            if (!file_exists($path)) {
                // $this->log(" --- file doesn't exist, will be created");
                continue;
            }

            $json = file_get_contents($path);

            $this->log("Decoding data from $path...");
            try {
                $decoded = $this->json_decode($json, ['assoc' => true, 'keepWsc' => false]);
            } catch (Exception $e) {
                throw new Exception(
                    "Error while trying to read from $path:\n" . $e->getMessage()
                );
            }

            // $decoded = json_decode($json, true);
            if (empty($decoded)) {
                $this->is_exportable = false;
                $this->error("Unexpected empty data read from: $path");
            }

            $unmerged_data[] = $decoded;
        }//end foreach

        if (count($unmerged_data) == 1) {
            $this->data = $unmerged_data[0];
        } elseif (count($unmerged_data) > 1) {
            $this->data = $this->mergeConfig($unmerged_data);
        }
    }//end readJSON()

    /**
     * Merge loaded configuration data (from readJSON)
     *
     * @param array $unmerged_data Configuration data arrays to be merged.
     *
     * @return array Merged configuration data
     */
    public function mergeConfig(array $unmerged_data): array
    {
        $merged_data = [];
        foreach ($unmerged_data as $data) {
            foreach ($data as $key => $config) {
                // Merge colliding arrays
                if (isset($merged_data[$key]) && is_array($merged_data[$key])) {
                    // Lists (int-indexed arrays) -> merge normally & ensure uniqueness (eg. aliases)
                    if (isset($merged_data[$key][0])) {
                        $merged_data[$key] = array_unique(array_merge(
                            $merged_data[$key],
                            $config
                        ));
                        continue;
                    // Hashes - merge recursively
                    } else {
                        $merged_data[$key] = $this->mergeConfig([
                            $merged_data[$key],
                            $config
                        ]);
                        continue;
                    }
                }//end if

                // Set new data or overwrite non-array values
                $merged_data[$key] = $config;
            }//end foreach
        }//end foreach
        return $merged_data;
    }//end mergeConfig()

    /**
     * Read from SSH config file - load host configurations into this instance.
     *
     * @param string $path Path to the SSH config file from which to load configuration.
     *
     * @return void
     */
    public function readSSH(string $path)
    {
        $path_handle = fopen($path, 'r');
        $init = $this->initData();

        // Clean out derivitive data
        $this->resetDerivedData();

        $original_keys = [];

        $l = 0;
        while ($line = fgets($path_handle)) {
            $l++;
            $line = trim($line);

            // $this->log("$l: $line");
            // Skip Blank Lines
            if (empty($line)) {
                // $this->log(' - blank - skipping');
                continue;
            }

            // Skip Comments
            if (strpos($line, '#') === 0) {
                // $this->log(' - comment - skipping');
                continue;
            }

            // Parse into key and value
            if (preg_match('/^(\S+)\s+(.*)$/', $line, $match)) {
                $key = strtolower($match[1]);
                $value = trim($match[2]);

                if (!isset(self::$CONFIG_KEYS[$key])) {
                    $original_keys[$key] = $match[1];
                }

                // $this->log(" - Parsed as [$key => $value]");
                if ($key == 'host') {
                    $host = $value;
                    $this->data['hosts'][$host] = [
                        'ssh' => [],
                        'pssh' => [],
                    ];
                } else {
                    if (empty($host)) {
                        // $this->log(" - Determined to be general config");
                        $this->data['ssh'][$key] = $value;
                    } else {
                        // $this->log(" - Adding to hosts[$host][ssh]");
                        $this->data['hosts'][$host]['ssh'][$key] = $value;
                    }
                }
            } else {
                $this->error("Unexpected syntax - check $path line $l");
            }//end if
        }//end while

        // Warn about any unknown keys our mapping didn't have
        if (!empty($original_keys)) {
            $original_keys = array_unique($original_keys);
            sort($original_keys);
            $this->warn(
                'Unknown Config Key(s) Present' .
                ' - if these are valid, the PSSH code should be updated to know about them.' .
                "\nThe following keys were not recognized:\n"
            );
            $this->output($original_keys);
        }

        fclose($path_handle);
    }//end readSSH()

    /**
     * Search - search for host config by:
     *  - host alias
     *  - domain or IP
     *  - username
     *
     * @param string $termstring Search string - can be multiple terms separated by spaces.
     *
     * @return array Hosts found that match the search, keyed by alias.
     */
    public function search(string $termstring): array
    {
        $termstring = strtolower(trim($termstring));

        // No search - return all hosts
        if (empty($termstring)) {
            return $this->data['hosts'];
        }

        $terms = explode(" ", $termstring);
        $terms = array_map('trim', $terms);
        $ips = [];
        foreach ($terms as $term) {
            $host = ['ssh' => ['hostname' => $term]];
            $this->cleanHostname($host, '[SEARCH]', 'uncertain');
            $ip = $host['ssh']['hostname'];

            if ($ip != $term) {
                $ips[] = $ip;
            }
        }

        $terms = array_merge($terms, $ips);

        $terms_pattern = "(" . implode("|", $terms) . ")";

        // Patterns for search, keyed by levity
        $patterns = [
            4 => "\b$termstring\b",
            3 => "$termstring",
            2 => "\b$terms_pattern\b",
            1 => "$terms_pattern",
        ];

        $h = 0;
        $results = [];

        foreach ($this->data['hosts'] as $alias => $host) {
            // Higher levity will float higher in results
            // 0 = no match - falls out of the list
            $levity = 0;

            // Targets for search, keyed by levity
            // - below expect less than 10 targets
            $targets = [
                4 => @$host['pssh']['alias'],
                3 => @$host['ssh']['hostname'],
                2 => @$host['ssh']['user'],
                1 => $alias,
            ];

            if (empty($host['pssh']['alias'])) {
                $host['pssh']['alias'] = $alias;
            }

            foreach ($targets as $t => $target) {
                foreach ($patterns as $p => $pattern) {
                    if (!empty($target) and preg_match_all("`" . $pattern . "`i", $target, $matches)) {
                        $levity += ( ($p + 1) * 10)  + ($t + 1);
                        // quit as soon as we have a match
                        continue;
                    }
                }
            }

            if ($levity > 0) {
                $this->log("$alias: $levity");

                // Multiply to make sure host index doesn't matter much beyond ensuring uniqueness
                // - we are making the bold assumption that there are less than 1 billion host entries
                $levity = ($levity * 1000000000) + $h;

                $results[$levity] = [$alias, $host];
            }

            $h++;
        }//end foreach

        krsort($results);

        // Re-key by alias
        $return = [];
        foreach ($results as $result) {
            $alias = $result[0];
            $host = $result[1];
            $return[$alias] = $host;
        }

        return $return;
    }//end search()

    /**
     * Write out currently loaded configuration to JSON at the specified file path.
     *
     * @param string $path The path to which to write the JSON version of the current loaded configuration.
     *                      - If the path ends in .hjson, contents will be written as HJSON.
     *
     * @return void
     */
    public function writeJSON(string $path)
    {
        if (preg_match("/.hjson$/", $path)) {
            $json = $this->json_encode($this->data);
        } else {
            $json = json_encode($this->data, JSON_PRETTY_PRINT);
        }
        file_put_contents($path, $json);
    }//end writeJSON()

    /**
     * Write the currently loaded configuration to SSH config format at the specified file path.
     *
     * @param string $path The path to which to write the SSH config version of the current loaded configuration.
     *
     * @return void
     */
    public function writeSSH(string $path)
    {
        $this->clean();
        $alias_map = $this->getAliasMap();

        if (! $this->isExportable()) {
            $this->error(
                "Export aborted to prevent issues with SSH config.\n" .
                "Review errors and warnings (review again if needed with `pssh clean`) and then try again."
            );
        }

        $host_map = $this->getHosts();

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
        if (!empty($this->data['ssh'])) {
            foreach ($this->data['ssh'] as $key => $value) {
                // $this->log(" - $key: $value");
                $Key = isset(self::$CONFIG_KEYS[$key]) ? self::$CONFIG_KEYS[$key] : ucwords($key);
                fwrite($path_handle, $Key . ' ' . $value . "\n");
            }
        }

        // $this->log("Outputting Hosts Config");
        fwrite($path_handle, "\n");
        fwrite($path_handle, "# ---------------------------------------\n");
        fwrite($path_handle, "# HOSTS\n");
        fwrite($path_handle, "# ---------------------------------------\n");
        foreach ($alias_map as $alias => $key) {
            $host_config = $host_map[$key];
            $host_output = $this->writeSSHHost($alias, $host_config);
            fwrite($path_handle, $host_output);
        }

        // $this->log("Outputting Vim Syntax Comment");
        fwrite($path_handle, "\n# vim: syntax=sshconfig");

        fclose($path_handle);
    }//end writeSSH()

    /**
     * Test if the configuration is exportable and show a warning if not.
     *
     * @return boolean Wheter configuration data is exportable.
     */
    public function isExportable(): bool
    {
        if (is_null($this->is_exportable)) {
            $this->error("Implementation error - run clean(), to evaluate exportability before running isExportable()");
        }

        if (! $this->is_exportable) {
            $this->warn("This configuration is not in a valid exportable state. See prior output for details.");
        }
        return (bool) $this->is_exportable;
    }//end isExportable()

    /**
     * Convert the specified host data to SSH config format
     *
     * @param string $alias The alias of the host to be written.
     * @param array  $host  The host data to be written.
     *
     * @return string SSH config formatted version of the host data.
     **/
    public function writeSSHHost(string $alias, array $host): string
    {
        $output = "";
        $output .= 'Host ' . $alias . "\n";
        foreach ($host['ssh'] as $key => $value) {
            $Key = isset(self::$CONFIG_KEYS[$key]) ? self::$CONFIG_KEYS[$key] : ucwords($key);
            $output .= '    ' . $Key . ' ' . $value . "\n";
        }
        return $output;
    }//end writeSSHHost()

    /****************************************************************************************************
     * Secondary/Helper Methods
     ****************************************************************************************************/

    /**
     * Initialize the configuration-holding $data property
     *
     * @return boolean Whether $data actually needed to be initialized.
     *                  - Ie. true if $data was null - not yet initialized, false if it already was.
     */
    public function initData(): bool
    {
        if (is_null($this->data)) {
            $this->data = [
                "ssh" => [],
                "pssh" => [],
                "hosts" => [],
            ];
            return true;
        }

        // no init was needed
        return false;
    }//end initData()

    /**
     * Reset derived data - run when loading from files to make sure data is derived
     *  properly from all combined data sources
     *
     * @return void
     */
    private function resetDerivedData()
    {
        $this->alias_map = null;
        $this->hosts_by_hostname = null;
        $this->team_keys = null;
        $this->team_keys_identifier = null;
    }//end resetDerivedData()


    /**
     * Make sure the provided alias is unique, or add 1/2/3, etc as needed to ensure uniqueness
     *
     * @param string $alias The alias to be made unique.
     *
     * @return string The unique version of the alias, with a number appended to ensure uniqueness.
     */
    public function autoAlias(string $alias): string
    {
        $i = 0;
        $new_alias = $alias;
        while (isset($this->data['hosts'][$new_alias])) {
            $i++;
            $new_alias = $alias . $i;
        }

        return $new_alias;
    }//end autoAlias()

    /**
     * Clean a hostname - attempt to standardize as IP Address, looking up via DNS if needed.
     *
     * @param array  $host      The host config to clean - will be modified in place (passed by reference).
     * @param string $host_key  The key for the host config - used for logging.
     * @param mixed  $uncertain Whether we're uncertain the hostname is intended to be a hostname (as opposed to a generic search term).
     *              - If certain, we'll warn if we can't look it up.
     *              - If uncertain, we'll validate it as a URL before looking up.
     *
     * @return void
     */
    public function cleanHostname(array &$host, string $host_key = '[UNKNOWN]', $uncertain = false)
    {
        if (! $this->hostDataIsCleanable($host, 'port')) {
            return;
        }

        // Make sure lookup isn't disabled by pssh config
        $pssh = $host['pssh'] ?? [];
        $lookup = strtolower($pssh['lookup'] ?? 'yes') === 'yes';

        $hostname = trim($host['ssh']['hostname']);

        $certain = ! $uncertain;

        $valid_ip = filter_var($hostname, FILTER_VALIDATE_IP);
        $valid_url = filter_var('http://' . $hostname, FILTER_VALIDATE_URL);

        // Try and canonicalize to IP Address
        if (
            // Not empty
            !empty($hostname)

            // Not specifically instructed against lookup
            and $lookup

            // It's not already an IP
            and !$valid_ip

            // Either we're certain it's a hostname
            // or it looks like a URL
            and (! $certain or $valid_url)
        ) {
            $info = @dns_get_record($hostname, DNS_A);
            if (
                empty($info)
                or empty($info[0]['ip'])
                or !filter_var($info[0]['ip'], FILTER_VALIDATE_IP)
            ) {
                if ($certain) {
                    $this->is_exportable = false;
                    $this->warn("Failed lookup - $hostname for host $host_key.  Set pssh[lookup] to 'no' if this is normal for this host.");
                }
            } else {
                $ip = $info[0]['ip'];

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $hostname = $ip;
                }
            }
        }//end if

        if (empty($hostname)) {
            $this->is_exportable = false;
            $this->warn("Empty hostname for host $host_key. This will not do. Set it to a valid hostname.");
        }

        $host['ssh']['hostname'] = $hostname;
    }//end cleanHostname()

    /**
     * Clean port for a host config - make sure it's a valid port number; throw an error if unable to validate.
     *
     * @param array  $host     The host config to clean - will be modified in place (passed by reference).
     * @param string $host_key The key for the host config - used for logging.
     *
     * @return void
     */
    private function cleanPort(array &$host, string $host_key)
    {
        if (! $this->hostDataIsCleanable($host, 'port')) {
            return;
        }

        // The cleaning
        $port = $host['ssh']['port'];
        $clean_port = (int) $port;

        // Default port
        if (empty($clean_port)) {
            $clean_port = 22;
        }

        if ($clean_port < 1 or $clean_port > 65535) {
            $this->is_exportable = false;
            $this->warn("Invalid port number: $port for host $host_key - set pssh[clean_port] to 'no' to disable this check for a given host.");
        }
        $clean_port = (string) $clean_port;

        if ($clean_port === $port) {
            return;
        }

        $host['ssh']['port'] = $clean_port;

        $port_type = gettype($port);
        $clean_port_type = gettype($clean_port);
        $this->warn("Port '$port' ($port_type) is being cleaned to '$clean_port' ($clean_port_type) for host $host_key - set pssh[clean_port] to 'no' to disable this for a given host.");
    }//end cleanPort()


    /**
     * Clean username for a host config
     *
     * @param array  $host     The host config to clean - will be modified in place (passed by reference).
     * @param string $host_key The key for the host config - used for logging.
     *
     * @return void
     */
    private function cleanUser(array &$host, string $host_key)
    {
        if (! $this->hostDataIsCleanable($host, 'user')) {
            return;
        }

        // The cleaning
        $user = $host['ssh']['user'];
        $clean_user = trim($user);

        if (empty($clean_user)) {
            $this->is_exportable = false;
            $this->warn("Empty user value for host $host_key. This will not do. Set it to a valid user or remove the user key.");
        }

        if ($clean_user === $user) {
            return;
        }

        $host['ssh']['user'] = $clean_user;

        $user_type = gettype($user);
        $clean_user_type = gettype($clean_user);
        $this->warn("Port '$user' ($user_type) is being cleaned to '$clean_user' ($clean_user_type) for host $host_key - set pssh[clean_user] to 'no' to disable this for a given host.");
    }//end cleanUser()


    /**
     * Clean the aliases for host config
     *
     * @param array  $host     The host config to clean - will be modified in place (passed by reference).
     * @param string $host_key The key for the host config - used for logging.
     *
     * @return void
     */
    private function cleanAliases(array &$host, string $host_key)
    {
        // Special case - no need to clean
        if ($host_key === '*') {
            return;
        }

        $host['pssh']['alias'] = $this->_cleanAliases([$host['pssh']['alias']], 'alias', $host, $host_key)[0];
        $host['pssh']['alias_additional'] = $this->_cleanAliases($host['pssh']['alias_additional'], 'alias_additional', $host, $host_key);
    }//end cleanAliases()


    /**
     * Clean a set of aliases - used by cleanAliases()
     *
     * @param array  $aliases     The aliases to clean.
     * @param string $aliases_key The key for the aliases - to check config.
     * @param array  $host        The host config to clean - will be modified in place (passed by reference).
     * @param string $host_key    The key for the host config - used for logging.
     *
     * @return array The cleaned aliases.
     */
    private function _cleanAliases(array $aliases, string $aliases_key, array &$host, string $host_key)
    {
        if (! $this->hostDataIsCleanable($host, $aliases_key)) {
            return $aliases;
        }

        $clean_aliases = [];
        foreach ($aliases as $alias) {
            $clean_aliases[] = $this->_cleanAlias($alias, $host_key, $aliases_key);
        }

        return $clean_aliases;
    }//end _cleanAliases()

    /**
     * Clean a particular alias - used by _cleanAliases
     *
     * @param string $alias       The alias to clean.
     * @param string $host_key    The key for the host config - used for logging.
     * @param string $aliases_key The key for the aliases - used for logging.
     *
     * @return string The clean aliase.
     */
    private function _cleanAlias(string $alias, string $host_key, string $aliases_key): string
    {
        $clean_alias = preg_replace('/[^.a-zA-Z0-9_-]+/', '_', trim($alias));

        if (empty($clean_alias)) {
            $this->is_exportable = false;
            $this->warn("Empty alias value for host $host_key. This will not do. Set it to a valid alias or remove the empty alias entry.");
        }

        if ($clean_alias !== $alias) {
            $alias_type = gettype($alias);
            $clean_alias_type = gettype($clean_alias);
            $this->warn("Alias '$alias' ($alias_type) is being cleaned to '$clean_alias' ($clean_alias_type) for host $host_key - set pssh[clean_$aliases_key] to 'no' to disable this for a given host.");
        }

        return $clean_alias;
    }//end _cleanAlias()

    /**
     * Determine if a given value on a host is cleanable
     *
     * @param array  $host The host config to check.
     * @param string $key  The ssh data key to be checked.
     *
     * @return boolean Whether the value is cleanable. False if it doesn't exist or config is set to not clean it.
     */
    private function hostDataIsCleanable(array $host, string $key): bool
    {
        // Can't clean what doesn't exist:
        if (strpos($key, 'alias') === 0) {
            if (empty($host['pssh']) || !isset($host['pssh'][$key])) {
                return false;
            }
        } else {
            if (empty($host['ssh']) || !isset($host['ssh'][$key])) {
                return false;
            }
        }

        // Config set NOT to clean
        if (! empty($host['pssh']) && strtolower($host['pssh']['clean_user'] ?? 'yes') === 'no') {
            return false;
        }

        // Clean it up!
        return true;
    }//end hostDataIsCleanable()

    /**
     * Magic handling for subcommands to call main command methods
     *
     *  - Primarly used as an organization tool
     *  - Allows us to keep some methods in console_abstract and still have them available in other places
     *  - FWIW, not super happy with this approach, but it works for now
     *
     * @param string $method    The method that is being called.
     * @param array  $arguments The arguments being passed to the method.
     *
     * @throws Exception If the method can't be found on the "main_tool" instance.
     * @return mixed If able to call the method on the "main_tool" (instance of Console_Abstract) then, return the value from calling that method.
     */
    public function __call(string $method, array $arguments)
    {
        $callable = [$this->main_tool, $method];
        if (is_callable($callable)) {
            $this->main_tool->log("Attempting to call $method on Console_Abstract instance");
            return call_user_func_array($callable, $arguments);
        }

        throw new Exception("Invalid class method '$method'");
    }//end __call()
}//end class

PSSH_Config::$CONFIG_KEYS = $PSSH_CONFIG_KEYS;

// Note: leave the end tag for packaging
?>
