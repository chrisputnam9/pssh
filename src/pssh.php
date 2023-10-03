<?php
/**
 * PSSH Class File
 *
 * @package pssh
 * @author  chrisputnam9
 */

/**
 * PSSH - PHP SSH Configuration Management Tool
 *
 *  - Defines the PSSH CLI tool
 *  - Provides the user interface
 *  - Uses PSSH_Config to interact with SSH config data
 */
class PSSH extends Console_Abstract
{
    /**
     * Current tool version
     *
     * @var string
     */
    public const VERSION = "2.5.5";

    /**
     * Tool shortname - used as name of configurationd directory.
     *
     * @var string
     */
    public const SHORTNAME = 'pssh';

    /**
     * Callable Methods / Sub-commands
     *  - Must be public methods defined on the class
     *
     * @var array
     */
    protected static $METHODS = [
        'add',
        'clean',
        'edit_host',
        'delete_host',
        'export',
        'import',
        'init_host',
        'list',
        'merge',
        'search',
        'sync',
    ];

    /**
     * Default method to run on command launch if none specified
     *
     *  - Must be one of the values specified in static $METHODS
     *
     * @var string
     */
    protected static $DEFAULT_METHOD = "list";

    /**
     * Config options that are hidden from help output
     * - Add config values here that would not typically be overridden by a flag
     * - Cleans up help output and avoids confusion
     *
     * @var array
     */
    protected static $HIDDEN_CONFIG_OPTIONS = [
        'json_config_paths',
        'json_import_path',
        'ssh_config_path',
        'sync',
    ];

    /**
     * Help info for $json_config_paths
     *
     * @var array
     *
     * @internal
     */
    protected $__json_config_paths = ["Main JSON config file paths", "string"];

    /**
     * Main JSON config file paths
     *
     *  - JSON format SSH host files to be sourced.
     *  - Default set in initConfig:
     *      [
     *          ~/.ssh/ssh_config_work.json
     *          ~/.ssh/ssh_config_personal.json
     *      ]
     *
     * @var array
     * @api
     */
    public $json_config_paths = [];

    /**
     * Help info for $json_import_path
     *
     * @var array
     *
     * @internal
     */
    protected $__json_import_path = ["Default JSON config import path", "string"];

    /**
     * Default JSON config import path
     *
     *  - The location to which existing SSH config is imported when the 'import' command is run.
     *  - Default set in initConfig - ~/.pssh/ssh_config_imported.json
     *
     * @var string
     * @api
     */
    public $json_import_path = null;

    /**
     * Help info for $ssh_config_path
     *
     * @var array
     *
     * @internal
     */
    protected $__ssh_config_path = ["Default SSH config path", "string"];

    /**
     * Default SSH config path
     *
     *  - Path to the user's SSH config file (typically ~/.ssh/config)
     *  - Default set in initConfig - ~/.ssh/config
     *
     * @var string
     * @api
     */
    public $ssh_config_path = null;

    /**
     * Help info for $cli_script
     *
     * @var array
     *
     * @internal
     */
    protected $__cli_script = ["CLI script to install on hosts during init", "string"];

    /**
     * CLI script to install on hosts during init
     *
     * @var string
     *
     * @api
     */
    public $cli_script = '';

    /**
     * Help info for $sync
     *
     * @var array
     *
     * @internal
     */
    protected $__sync = ["Git SSH URL to sync config data", "string"];

    /**
     * Git SSH URL to sync config data
     *
     * @var string
     * @api
     */
    public $sync = '';

    /**
     * The URL to check for updates
     *
     *  - PSSH will check the README file - typical setup
     *
     * @var string
     * @see PCon::update_version_url
     * @api
     */
    public $update_version_url = "https://raw.githubusercontent.com/chrisputnam9/pssh/master/README.md";

    /**
     * Help info for add method
     *
     * @var array
     *
     * @internal
     */
    protected $___add = [
        "Add new SSH host - interactive, or specify options",
        ["JSON file to add host to", "string"],
        ["Hostname - domain or IP", "string"],
        ["Username", "string"],
        ["Alias", "string"],
        ["Port", "integer"],
    ];

    /**
     * Add new SSH host - interactive, or specify options
     *
     * @param string $target   The target JSON config file in which to add the new host.
     *                         Will prompt if not passed.
     * @param string $hostname The IP or URL of the host.
     *                         Will prompt if not passed.
     * @param string $user     The SSH username.
     *                         Will prompt if not passed.
     * @param string $alias    The alias to use for the host.
     *                         Will prompt if not passed.
     *                         Defaults to same value as $user when prompting.
     *                          - with a number added to make it unique if necessary.
     * @param string $port     The SSH port.
     *                         Will prompt if not passed.
     *                         Defaults to 22 when prompting.
     *
     * @return void
     */
    public function add(string $target = null, string $hostname = null, string $user = null, string $alias = null, string $port = null)
    {

        // Sync before - to get latest data
        $this->sync();

        $config = new PSSH_Config($this);

        $this->hr();
        $this->output('ADDING SSH HOST');
        $this->hr();

        // Determine file to write to
        if (is_null($target)) {
            $target = $this->select($this->json_config_paths, 'Config File');
        }
        $config->readJson($target);

        // Hostname
        if (is_null($hostname)) {
            $hostname = $this->input('HostName (URL/IP)', null, true);
        } else {
            $this->output("HostName (URL/IP): $hostname");
        }
        $host = ['ssh' => ['hostname' => $hostname]];
        $config->cleanHostname($host, '[NEW HOST]');
        $clean_hostname = $host['ssh']['hostname'];

        if ($clean_hostname != $hostname) {
            $hostname = $clean_hostname;
            $this->output(" ($hostname)");
        }

        // User Name
        if (is_null($user)) {
            $existing = $config->find(['hostname' => $hostname]);
            $existing_users = array_keys($existing['hostname']);
            if (!empty($existing_users)) {
                $this->output(
                    'NOTE: existing users configured for this hostname: (' .
                    join(', ', $existing_users) . ')'
                );
            }
            $user = $this->input('User', null, true);
        } else {
            $this->output("User: $user");
        }

        // Host Alias
        do {
            if (is_null($alias)) {
                $default = $config->autoAlias($user);
                $input_alias = $this->input('Primary Alias', $default);
            } else {
                $input_alias = $alias;
                $this->output("Primary Alias: $alias");
            }

            $host_using_alias = $config->getHosts($input_alias);

            if (empty($host_using_alias)) {
                $alias = $input_alias;
            } else {
                $alias = null;
                $this->warn("An existing host is already using that alias. Please enter a new one.");
            }
        } while (empty($alias));

        // Port
        if (is_null($port)) {
            // for specified hostname
            $port = $this->input('Port', 22);
        } else {
            $this->output("Port: $port");
        }

        $this->hr();
        $this->output("- adding host to $target...");

        $host = [
            'ssh' => [
                'user' => $user,
                'hostname' => $hostname,
                'port' => $port,
            ],
        ];
        $success = $config->add($alias, $host);

        // Any error? For now, just quitting
        if (!$success) {
            $this->hr();
            $this->error("Unable to add host - likely conflict in $target");
        }

        // Backup, clean, save json
        $this->backup($target);
        $config->clean();
        $config->writeJson($target);

        // write out ssh config
        $this->export();

        $this->sync();

        // Initialize Host (prompt - key, cli)
        $this->init_host($alias, null, null, $target, null);

        $this->hr();
        $this->output('Done!');
    }//end add()

    /**
     * Help info for clean method
     *
     * @var array
     *
     * @internal
     */
    protected $___clean = [
        "Clean json config files",
        ["JSON file(s) to clean - defaults to json-config-paths", "string"],
    ];

    /**
     * Clean json config files
     *
     *  - Eg. remove duplicates, look up IPs, sort data
     *
     * @param array $paths The configuration paths to clean up.
     *                     Defaults to all known config paths.
     *
     * @return boolean Whether all configuration files are in exportable state.
     */
    public function clean(array $paths = null): bool
    {
        $paths = $this->prepArg($paths, $this->json_config_paths);

        $this->log("Cleaning config files:");
        $this->log($paths);

        $this->log("Backing up...");
        $this->backup($paths);

        $exportable = true;
        foreach ($paths as $path) {
            $this->log("Cleaning '$path'");
            $config = new PSSH_Config($this);
            $config->readJSON($path);
            $config->clean();
            $exportable = $exportable && $config->isExportable();
            $config->writeJSON($path);
        }

        // Return early if not exportable at this point
        // - no need for further warnings, will catch them next time
        if (!$exportable) {
            return false;
        }

        // Do a final clean & getAliasMap across all files
        // - primarily just to check for duplicate aliases
        $config = new PSSH_Config($this);
        $config->readJSON($paths);
        $config->clean();
        $config->getAliasMap();
        $exportable = $exportable && $config->isExportable();

        $this->output('Clean complete');

        return $exportable;
    }//end clean()

    /**
     * Help info for export method
     *
     * @var array
     *
     * @internal
     */
    protected $___export = [
        "Export JSON config to SSH config file",
        ["Source JSON files - defaults to json-config-paths", "string"],
        ["Target SSH config file - defaults to ssh-config-path", "string"],
    ];

    /**
     * Export JSON config to SSH config file
     *
     * @param array  $sources Source JSON config paths to pull from.
     *                        Defaults to use all known config paths.
     * @param string $target  Target SSH config file to export to.
     *                        Defaults to configured $ssh_config_path.
     *
     * @return void
     */
    public function export(array $sources = [], string $target = null)
    {
        $target = $this->prepArg($target, $this->ssh_config_path);
        $sources = $this->prepArg($sources, $this->json_config_paths);

        // Run an initial clean & write JSON for each source
        $exportable = $this->clean($sources);
        if (! $exportable) {
            $this->error(
                "Export aborted to prevent issues with SSH config.\n" .
                "Review errors and warnings (review again if needed with `pssh clean`) and then try again."
            );
        }

        // Backup target SSH config
        $this->backup($target);

        $config = new PSSH_Config($this);
        $config->readJSON($sources);
        $config->writeSSH($target);

        $this->output('Export complete');
    }//end export()

    /**
     * Help info for import method
     *
     * @var array
     *
     * @internal
     */
    protected $___import = [
        "Import SSH config data into JSON",
        ["Target JSON file - defaults to json-import-path", "string"],
        ["Source SSH config file - defaults to ssh-config-path"],
    ];

    /**
     * Import SSH config data into JSON
     *
     * @param string $target Target JSON file to which to save imported config data.
     *                       Defaults to configured $json_import_path.
     * @param string $source Source SSH config file from which to import data.
     *                       Defaults to configured $ssh_config_path.
     *
     * @return void
     */
    public function import(string $target = null, string $source = null)
    {
        // Defaults
        $target = $this->prepArg($target, $this->json_import_path);
        $source = $this->prepArg($source, $this->ssh_config_path);

        if (is_file($target)) {
            $this->backup($target);
        }

        $config = new PSSH_Config($this);
        $config->readSSH($source);
        $config->clean();
        $config->writeJSON($target);

        $this->output('Import complete - see json in ' . $target);
    }//end import()

    /**
     * Help info for delete_host method
     *
     * @var array
     *
     * @internal
     */
    protected $___delete_host = [
        "Delete a host",
        ["Alias of host to delete", "string", "required"],
        ["Specific JSON file(s) to delete from - defaults to delete from ALL files in json-config-paths", "string"],
    ];

    /**
     * Delete a host
     *
     * @param string $alias Alias of host to delete.
     * @param array  $paths The JSON config path(s) from which to delete the host.
     *               Defaults to all known config paths.
     *
     * @return boolean Whether the host was deleted successfully from all paths.
     */
    public function delete_host(string $alias, array $paths = null): bool
    {

        $paths = $this->prepArg($paths, $this->json_config_paths);

        // Sync before - to get latest data
        $this->sync();

        $host_json = false;
        $any_success = false;

        foreach ($paths as $config_path) {
            $config = new PSSH_Config($this);
            $config->readJSON($config_path);

            if ($config->deleteHost($alias)) {
                // Backup, clean, save json
                $this->backup($config_path);
                $config->clean();
                $config->writeJson($config_path);
                $any_success = true;
            }
        }

        if ($any_success) {
            // write out ssh config
            $this->export();

            $this->sync();

            $this->hr();
            $this->output('Done!');
            return true;
        }

        $this->warn("No hosts found to delete with alias '$alias'");
        return false;
    }//end delete_host()

    /**
     * Help info for edit_host method
     *
     * @var array
     *
     * @internal
     */
    protected $___edit_host = [
        "Edit host - modify config in your editor",
        ["Alias of host", "string", "required"],
        [
            'Specific JSON file(s) to edit - defaults to first found in json-config-paths that contains the host alias',
            'string'
        ],
    ];

    /**
     * Edit host - modify config in your editor
     *
     * @param string $alias Alias of host to edit.
     * @param array  $paths The JSON config path(s) from which to edit the host.
     *               Defaults to all known config paths.
     *
     * @return boolean Whether the host was successfully edited.
     */
    public function edit_host(string $alias, array $paths = null): bool
    {
        $paths = $this->prepArg($paths, $this->json_config_paths);

        // Sync before - to get latest data
        $this->sync();

        $host_data = false;
        $host_json = false;

        $config_host_map = [];

        // Check each file for matching host data
        foreach ($paths as $config_path) {
            $config = new PSSH_Config($this);
            $config->readJSON($config_path);
            $key = $config->getHostKey($alias);
            $data = $config->getHosts($key);
            $select = "Host with key '$key' in '$config_path'";
            if ($key) {
                $config_host_map[$select] = [$key, $data, $config_path];
            }
        }

        // Error if not found in any file
        if (empty($config_host_map)) {
            $this->error("Host '$alias' not found in config files");
        }

        // Select which config if multiple options
        if (count($config_host_map) > 1) {
            $config_host_map[static::BACK_OPTION] = static::BACK_OPTION;
            $this->output("Host with key/alias '$alias' exists in multiple config files.");
            $selected = $this->select(array_keys($config_host_map), 'Select which to edit');
            if ($selected === static::BACK_OPTION) {
                return false;
            }
            list($key, $data, $config_path) = $config_host_map[$selected];
        } else {
            list($key, $data, $config_path) = array_pop($config_host_map);
        }

        // Load up the file we're going to be editing
        $config = new PSSH_Config($this);
        $config->readJSON($config_path);

        $host_data = $data[0];
        $host_json = json_encode($host_data, JSON_PRETTY_PRINT);
        $original_json = $host_json;

        while (true) {
            $host_hjson = $this->json_encode($host_data);
            $host_hjson = $this->edit($host_hjson, $alias . ".hjson", "modify");
            $host_data = $this->json_decode($host_hjson, ['assoc' => true, 'keepWsc' => false]);

            $warnings = [];
            if (empty($host_data)) {
                $warnings[] = "Invalid JSON - check your syntax";
            }

            // Check for key & alias collisions
            $aliases = $host_data['pssh']['alias_additional'] ?? [];
            $new_key = $host_data['pssh']['alias'];
            $aliases[] = $new_key;
            foreach ($aliases as $alias) {
                // See if any existing host using this alias - other than the one being edited
                $colliding_host_key = $config->getHostKey($alias);
                if ($colliding_host_key && $colliding_host_key !== $key) {
                    $warnings[] = "Alias '$alias' is already in use by host with key '$colliding_host_key'";
                }
            }

            if (!empty($warnings)) {
                $cancel = 'Cancel / Abort Editing';
                $option = $this->select([
                    'Edit Information / Try Again',
                    $cancel,
                ], "ERRORS WITH EDIT:\n - " . join("\n - ", $warnings));

                if ($option === $cancel) {
                    return false;
                }
            } else {
                // Move on and save new data
                break;
            }
        }//end while

        // Test for actual changes
        $host_json = json_encode($host_data, JSON_PRETTY_PRINT);
        if ($host_json == $original_json) {
            // No actual change, no need to update
            return false;
        }

        // Delete if changing key
        if ($new_key !== $key) {
            $config->deleteHost($key);
        }

        // Set new data
        $config->setHost($new_key, $host_data);

        // Clean up the data
        $config->clean();

        // Backup, then save
        $this->backup($config_path);
        $config->writeJson($config_path);

        // write out ssh config
        $this->export();

        // sync up, if configured
        $this->sync();

        $this->hr();
        $this->output('Done!');
        return true;
    }//end edit_host()

    /**
     * Help info for init_host method
     *
     * @var array
     *
     * @internal
     */
    protected $___init_host = [
        "Initialize host - interactive, or specify options",
        ["Alias of host", "string", "required"],
        ["Copy your SSH key to the server", "boolean"],
        ["Copy all team SSH keys to the server - if set", "boolean"],
        ["JSON file for team key data", "string"],
        ["Set up server CLI using cli_script", "boolean"],
    ];

    /**
     * Initialize host - interactive, or specify options
     *
     * @param string  $alias          The host alias to initialize.
     *                                Will prompt if not passed.
     * @param mixed   $copy_key       Whether to copy the individual's SSH key to the host.
     *                                Will prompt if not passed.
     * @param mixed   $copy_team_keys Whether to copy the team's SSH keys to the host.
     *                                Will prompt if not passed and if $copy_key is true-ish.
     *                                Defaults to false when prompting.
     * @param string  $team_config    The config the team from which to pull SSH keys (if copying to host).
     *                                Will prompt if not passed and if $copy_team_keys is true-ish.
     * @param boolean $cli            Whether to run custom CLI setup script on server (if configured - PSSH::$cli_script)
     *                                Will prompt if not passed and if $cli_script is configured.
     *
     * @return void
     */
    public function init_host(string $alias, $copy_key = null, $copy_team_keys = null, string $team_config = null, bool $cli = null)
    {

        // Copy Key?
        if (is_null($copy_key)) {
            $copy_key = $this->confirm('Copy Key?');
        }
        if ($copy_key or $copy_team_keys) {
            if (is_null($copy_team_keys)) {
                $copy_team_keys = $this->confirm('Copy all team keys?', 'n');
            }

            if ($copy_team_keys) {
                $config = new PSSH_Config($this);
                if (is_null($team_config)) {
                    $team_config = $this->select($this->json_config_paths, 'Config for team keys');
                }
                $config->readJson($team_config);

                $identifier = $config->getTeamKeysIdentifier();
                $team_keys = $config->getTeamKeys();
                if (empty($team_keys)) {
                    $this->warn('No team key config found, copying your key instead');
                }
            }

            $this->output('Enter ssh password for this host if prompted');

            if ($copy_team_keys) {
                $config = <<<____KEYS____
# ----------------------------------
# BEGIN - {$identifier}

____KEYS____;
                foreach ($team_keys as $team => $users) {
                    foreach ($users as $name => $user) {
                        foreach ($user['keys'] as $key) {
                            $config .= $key['key'] . "\n";
                        }
                    }
                }

                $config .= <<<____KEYS____
# END - {$identifier}
# ----------------------------------
____KEYS____;

                $this->exec(
                    "ssh $alias 'mkdir -p ~/.ssh && echo \"" . $config . "\"" .
                    " >> ~/.ssh/authorized_keys && chmod 700 ~/.ssh && chmod 600 ~/.ssh/authorized_keys'"
                );
            } else {
                $this->exec("ssh-copy-id $alias");
            }//end if
        }//end if

        // Set up Custom CLI Tools?
        if (is_null($cli) and !empty($this->cli_script) and is_file($this->cli_script)) {
            $cli = $this->confirm('Set up server cli tools?');
        }
        if ($cli) {
            $this->exec("bash {$this->cli_script} $alias");
        }
    }//end init_host()

    /**
     * Help info for merge method
     *
     * @var array
     *
     * @internal
     */
    protected $___merge = [
        "Merge config from one JSON file into another",
        ["JSON file to merge from", "string", "required"],
        ["JSON file to merge into", "string", "required"],
        ["JSON file to ouput conflicts/overrides", "string", "required"],
    ];

    /**
     * Merge config from one JSON file into another
     *
     * @param string $source_path   JSON file to merge from.
     * @param string $target_path   JSON file to merge into.
     * @param string $override_path JSON file to ouput conflicts/overrides.
     *
     * @return void
     */
    public function merge(string $source_path, string $target_path, string $override_path)
    {
        $this->backup($target_path);
        $this->backup($override_path);

        $this->output("Merging config...");
        $source = new PSSH_Config($this);
        $source->readJSON($source_path);

        $target = new PSSH_Config($this);
        $target->readJSON($target_path);

        $override = new PSSH_Config($this);
        $override->readJSON($override_path);

        $source->merge($target, $override);

        $target->clean();
        $target->writeJSON($target_path);

        $override->clean();
        $override->writeJSON($override_path);

        $this->output("Merge complete");
    }//end merge()

    /**
     * Help info for search method
     *
     * @var array
     *
     * @internal
     */
    protected $___search = [
        "Search for host configuration",
        ["Term(s) to search - separate with spaces", "string", "required"],
        ["JSON config path(s) to search - separate with commas - defaults to json-config-paths", "string"],
    ];

    /**
     * Search for host configuration:
     *
     * @param string $terms Term(s) to search - separate with spaces.
     * @param mixed  $paths JSON config path(s) to search - comma-separate if multiple.
     *                      Defaults to all known config paths.
     *
     * @return void
     */
    public function search(string $terms, $paths = null)
    {
        $paths = $this->prepArg($paths, $this->json_config_paths);

        $this->log("Searching config files:");
        $this->log($paths);

        $config = new PSSH_Config($this);
        $config->readJSON($paths);

        $results = $config->search($terms);

        foreach ($results as $key => $host) {
            $results[$key]['_alias_display'] = array_unique(array_merge(
                [$host['pssh']['alias']],
                $host['pssh']['alias_additional'] ?? []
            ));
        }
        sort($results);

        if (empty($results)) {
            $this->output("No results found");
        } else {
            $list = new Command_Visual_List(
                $this,
                $results,
                [
                    'template' => "{_alias_display|%-'.50s} {ssh:user}{ssh:hostname|@%s}{ssh:port|:%s}",
                    'reload_function' => function ($reload_data) {
                        $config = new PSSH_Config($this);
                        $config->readJSON($reload_data['paths']);
                        $results = $config->search($reload_data['terms']);
                        foreach ($results as $key => $host) {
                            $results[$key]['_alias_display'] = array_unique(array_merge(
                                [$host['pssh']['alias']],
                                $host['pssh']['alias_additional'] ?? []
                            ));
                        }
                        sort($results);
                        return $results;
                    },
                    'reload_data' => [
                        'paths' => $paths,
                        'terms' => $terms,
                    ],
                    'commands' => [
                        'init_host' => [
                            'description' => 'Initialize the focused host',
                            'keys' => 'i',
                            'callback' => function ($list_instance) {
                                $host_data = $list_instance->getFocusedValue();
                                $alias = $host_data['pssh']['alias'];
                                $this->init_host($alias);
                            },
                        ],
                        'delete_host' => [
                            'description' => 'Delete the focused host',
                            'keys' => 'd',
                            'callback' => function ($list_instance) {
                                $host_data = $list_instance->getFocusedValue();
                                $alias = $host_data['pssh']['alias'];
                                if (
                                    $this->confirm("Are you sure you want to delete the config for '$alias'?", "n")
                                ) {
                                    $this->delete_host($alias);
                                }
                            },
                            'reload' => true,
                        ],
                        'edit_host' => [
                            'description' => 'Edit the focused host',
                            'keys' => 'e',
                            'callback' => function ($list_instance) {
                                $host_data = $list_instance->getFocusedValue();
                                $alias = $host_data['pssh']['alias'];
                                $this->edit_host($alias);
                            },
                            'reload' => true,
                        ],
                    ],
                ]
            );
            $list->run();
        }//end if
    }//end search()

    /**
     * Help info for list method
     *
     * @var array
     *
     * @internal
     */
    protected $___list = [
        "List all hosts",
        ["JSON config path(s) to list - defaults to json-config-paths", "string"],
    ];

    /**
     * List all hosts
     *
     * @param mixed $paths JSON config path(s) to list - comma-separate if multiple.
     *              Defaults to all known config paths.
     *
     * @return void
     */
    public function list($paths = null)
    {
        $this->search("", $paths);
    }//end list()

    /**
     * Help info for sync method
     *
     * @var array
     *
     * @internal
     */
    protected $___sync = "Sync config files based on 'sync' config/option value";

    /**
     * Sync config files based on 'sync' config/option value
     *
     * @return void
     */
    public function sync()
    {
        if (empty($this->sync)) {
            return;
        }

        $this->output('Syncing...');

        if (substr($this->sync, 0, 4) == 'git@') {
            // Temporarily switch to config_dir
            $original_dir = getcwd();
            chdir($this->config_dir);

            // Set up git if not already done
            if (!is_dir($this->config_dir . DS . '.git')) {
                // $this->log('Running commands to initialize git');
                $this->exec("git init");
                $this->exec("git remote add sync {$this->sync}");
            }

            // Pull
            // $this->log('Pulling from remote (sync)');
            $this->exec("git pull sync master");

            // Set up git ignore if not already there
            if (!is_file($this->config_dir . DS . '.gitignore')) {
                // $this->log('Setting up default ignore file');
                $synced_config_json = empty($this->json_config_paths)
                    ? ''
                    : '!' . str_replace($this->config_dir, "", array_shift($this->json_config_paths));
                $ignore = <<<GITGNORE
*
!.gitignore
!ssh_cli.sh
{$synced_config_json}
GITGNORE;
                file_put_contents($this->config_dir . DS . '.gitignore', $ignore);
            }

            // Push
            // $this->log('Committing and pushing to remote (sync)');
            $this->exec("git add . --all");
            $this->exec("git commit -m \"Automatic sync commit - {$this->stamp()}\"");
            $this->exec("git push sync master");

            // Switch back to original directory
            chdir($original_dir);
        }//end if
    }//end sync()

    /**
     * Init config defaults, then call parent
     *
     * @return boolean
     */
    public function initConfig(): bool
    {
        $config_dir = $this->getConfigDir();

        // Config defaults
        $this->json_config_paths = [
            $config_dir . DS . 'ssh_config_work.json',
            $config_dir . DS . 'ssh_config_personal.json',
        ];
        $this->json_import_path = $config_dir . DS . 'ssh_config_imported.json';

        $this->ssh_config_path = $_SERVER['HOME'] . DS . '.ssh' . DS . 'config';

        $cli_script = $config_dir . DS . 'ssh_cli.sh';
        if (is_file($cli_script)) {
            $this->cli_script = $cli_script;
        }

        return parent::initConfig();
    }//end initConfig()
}//end class

PSSH::run($argv);

// Note: leave the end tag for packaging
?>
