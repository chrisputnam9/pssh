<?php

/**
 * PSSH Console Interface
 */
class PSSH extends Console_Abstract
{
    const VERSION = "2.4.1";

    // Name of script and directory to store config
    const SHORTNAME = 'pssh';

    /**
     * Callable Methods
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

    protected static $HIDDEN_CONFIG_OPTIONS = [
        'json_config_paths',
        'json_import_path',
        'ssh_config_path',
        'sync',
    ];

    // Config Variables
    protected $__json_config_paths = ["Main JSON config file paths", "string"];
    public $json_config_paths = [];

    protected $__json_import_path = ["Default JSON config import path", "string"];
    public $json_import_path = null;

    protected $__ssh_config_path = ["Default SSH config path", "string"];
    public $ssh_config_path = null;

    protected $__cli_script = ["CLI script to install on hosts during init", "string"];
    public $cli_script = '';

    protected $__sync = ["Git SSH URL to sync config data", "string"];
    public $sync = '';

    public $update_version_url = "https://raw.githubusercontent.com/chrisputnam9/pssh/master/README.md";

    protected $___add = [
        "Add new SSH host - interactive, or specify options",
        ["JSON file to add host to", "string"],
        ["Hostname - domain or IP", "string"],
        ["Username", "string"],
        ["Alias", "string"],
        ["Port", "integer"],
    ];
    public function add($target = null, $hostname = null, $user = null, $alias = null, $port = null)
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
        $clean_hostname = $config->cleanHostname($hostname);
        if ($clean_hostname != $hostname) {
            $hostname = $clean_hostname;
            $this->output(" ($hostname)");
        }

        // User Name
        if (is_null($user)) {
            $existing = $config->find(['hostname' => $hostname]);
            $existing_users = array_keys($existing['hostname']);
            if (!empty($existing_users)) {
                $this->output("NOTE: existing users configured for this hostname: (" . join(", ", $existing_users) . ")");
            }
            $user = $this->input('User', null, true);
        } else {
            $this->output("User: $user");
        }

        // Host Alias
        if (is_null($alias)) {
            $default = $config->autoAlias($user);
            $alias = $this->input('Alias', $default);
        } else {
            $this->output("Alias: $alias");
        }

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
    }

    protected $___clean = [
        "Clean json config files",
        ["JSON file(s) to clean - defaults to json-config-paths", "string"],
    ];
    public function clean($paths = null)
    {
        $paths = $this->prepArg($paths, $this->json_config_paths);

        $this->log("Cleaning config files:");
        $this->log($paths);

        $this->log("Backing up...");
        $this->backup($paths);

        foreach ($paths as $path) {
            $this->log("Cleaning '$path'");
            $config = new PSSH_Config($this);
            $config->readJSON($path);
            $config->clean();
            $config->writeJSON($path);
        }

        $this->output('Clean complete');
    }

    protected $___export = [
        "Export JSON config to SSH config file",
        ["Source JSON files - defaults to json-config-paths", "string"],
        ["Target SSH config file - defaults to ssh-config-path", "string"],
    ];
    public function export($sources = [], $target = null)
    {
        $target = $this->prepArg($target, $this->ssh_config_path);
        $sources = $this->prepArg($sources, $this->json_config_paths);

        $this->backup($target);

        $config = new PSSH_Config($this);
        $config->readJSON($sources);
        $config->clean();
        $config->writeSSH($target);

        $this->output('Export complete');
    }

    protected $___import = [
        "Import SSH config data into JSON",
        ["Target JSON file - defaults to json-import-path", "string"],
        ["Source SSH config file - defaults to ssh-config-path"],
    ];
    public function import($target = null, $source = null)
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
    }

    protected $___delete_host = [
        "Delete host",
        ["Alias of host to delete", "string", "required"],
        ["Specific JSON file(s) to delete from - defaults to delete from ALL files in json-config-paths", "string"],
    ];
    public function delete_host($alias, $paths = null)
    {

        $paths = $this->prepArg($paths, $this->json_config_paths);

        // Sync before - to get latest data
        $this->sync();

        $host_json = false;

        foreach ($paths as $config_path) {
            $config = new PSSH_Config($this);
            $config->readJSON($config_path);

            if ($config->deleteHost($alias)) {
                // Backup, clean, save json
                $this->backup($config_path);
                $config->clean();
                $config->writeJson($config_path);
            }
        }

        // write out ssh config
        $this->export();

        $this->sync();

        $this->hr();
        $this->output('Done!');
        return true;
    }

    protected $___edit_host = [
        "Edit host - modify config in your editor",
        ["Alias of host", "string", "required"],
        ["Specific JSON file(s) to edit - defaults to first found in json-config-paths that contains the host alias", "string"],
    ];
    public function edit_host($alias, $paths = null)
    {
        $paths = $this->prepArg($paths, $this->json_config_paths);

        // Sync before - to get latest data
        $this->sync();

        $host_data = false;
        $host_json = false;

        foreach ($paths as $config_path) {
            $config = new PSSH_Config($this);
            $config->readJSON($config_path);
            $host_data = $config->getHosts($alias);
            if (!empty($host_data) and !empty($host_data[0])) {
                $host_data = $host_data[0];
                $host_json = json_encode($host_data, JSON_PRETTY_PRINT);
                break;
            }
        }

        if (empty($host_data) or empty($host_json)) {
            $this->error("Host '$alias' not found in config files or invalid data");
        }

        $original_json = $host_json;

        while (true) {
            $host_hjson = $this->json_encode($host_data);
            $host_hjson = $this->edit($host_hjson, $alias . ".hjson", "modify");
            $host_data = $this->json_decode($host_hjson, ['assoc' => true, 'keepWsc' => false]);
            if (empty($host_data)) {
                $this->warn("Invalid JSON - check your syntax");
                $continue = $this->confirm("Keep editing?");
                if (! $continue) {
                    return false;
                }
            } else {
                break;
            }
        }

        // Test for actual changes
        $host_json = json_encode($host_data, JSON_PRETTY_PRINT);
        if ($host_json == $original_json) {
            // No actual change, no need to update
            return false;
        }

        $config->setHost($alias, $host_data);

        // Backup, clean, save json
        $this->backup($config_path);
        $config->clean();

        $config->writeJson($config_path);

        // write out ssh config
        $this->export();

        $this->sync();

        $this->hr();
        $this->output('Done!');
        return true;
    }

    protected $___init_host = [
        "Initialize host - interactive, or specify options",
        ["Alias of host", "string", "required"],
        ["Copy your SSH key to the server", "boolean"],
        ["Copy all team SSH keys to the server - if set", "boolean"],
        ["JSON file for team key data", "string"],
        ["Set up server CLI using cli_script", "boolean"],
    ];
    public function init_host($alias, $copy_key = null, $copy_team_keys = null, $team_config = null, $cli = null)
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
                $this->exec("ssh $alias 'mkdir -p ~/.ssh && echo \"" . $config . "\" >> ~/.ssh/authorized_keys && chmod 700 ~/.ssh && chmod 600 ~/.ssh/authorized_keys'");
            } else {
                $this->exec("ssh-copy-id $alias");
            }
        }

        // Set up Custom CLI Tools?
        if (is_null($cli) and !empty($this->cli_script) and is_file($this->cli_script)) {
            $cli = $this->confirm('Set up server cli tools?');
        }
        if ($cli) {
            $this->exec("bash {$this->cli_script} $alias");
        }
    }

    protected $___merge = [
        "Merge config from one JSON file into another",
        ["JSON file to merge from", "string", "required"],
        ["JSON file to merge into", "string", "required"],
        ["JSON file to ouput conflicts/overrides", "string", "required"],
    ];
    public function merge($source_path, $target_path, $override_path)
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
    }

    protected $___search = [
        "Search for host configuration",
        ["Term(s) to search - separate with spaces", "string", "required"],
        ["JSON config path(s) to search - defaults to json-config-paths", "string"],
    ];
    public function search($terms, $paths = null)
    {
        $paths = $this->prepArg($paths, $this->json_config_paths);

        $this->log("Searching config files:");
        $this->log($paths);

        $config = new PSSH_Config($this);
        $config->readJSON($paths);

        $results = $config->search($terms);

        if (empty($results)) {
            $this->output("No results found");
        } else {
            $list = new Command_Visual_List(
                $this,
                $results,
                [
                    'template' => "{pssh:alias|%-'.30s} {ssh:user}{ssh:hostname|@%s}{ssh:port|:%s}",
                    'reload_function' => function ($reload_data) {
                        $config = new PSSH_Config($this);
                        $config->readJSON($reload_data['paths']);
                        return $config->search($reload_data['terms']);
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
                                $focused_key = $list_instance->getFocusedKey();
                                $this->init_host($focused_key);
                            },
                        ],
                        'delete_host' => [
                            'description' => 'Delete the focused host',
                            'keys' => 'd',
                            'callback' => function ($list_instance) {
                                $focused_key = $list_instance->getFocusedKey();
                                $host_alias = $focused_key;
                                if ($this->confirm("Are you sure you want to delete the config for '$host_alias'?", "n")) {
                                    $this->delete_host($host_alias);
                                }
                            },
                            'reload' => true,
                        ],
                        'edit_host' => [
                            'description' => 'Edit the focused host',
                            'keys' => 'e',
                            'callback' => function ($list_instance) {
                                $focused_key = $list_instance->getFocusedKey();
                                $this->edit_host($focused_key);
                            },
                            'reload' => true,
                        ],
                    ],
                ]
            );
            $list->run();
        }
    }

    protected $___list = [
        "List all hosts",
        ["JSON config path(s) to list - defaults to json-config-paths", "string"],
    ];
    public function list($paths = null)
    {
        $this->search($paths);
    }

    /**
     * Currently only supports private git repository
     */
    protected $___sync = "Sync config files based on 'sync' config/option value";
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
        }
    }

    /**
     * Init config defaults, then call parent
     */
    public function initConfig()
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

        parent::initConfig();
    }
}
PSSH::run($argv);
?>
