<?php
/**
 * PSSH Console Interface
 */
class PSSH extends Console_Abstract
{
    public const VERSION = 1;

    /**
     * Callable Methods
     */
    protected static $METHODS = [
        'add',
        'backup',
        'clean',
        'export',
        'import',
        'init_host',
        'merge',
        'search',
        'sync',
    ];

    // Name of script and directory to store config
    protected const SHORTNAME = 'pssh';

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

    protected $__backup_dir = ["Default backup directory", "string"];
    public $backup_dir = null;

    protected $___add = [
        "Add new SSH host - interactive, or specify options",
        ["JSON file to add host to", "string"],
        ["Hostname - domain or IP", "string"],
        ["Username", "string"],
        ["Alias", "string"],
        ["Port", "integer"],
    ];
	public function add($target=null, $hostname=null, $user=null, $alias=null, $port=null)
    {

        // Sync before - to get latest data
        $this->sync();

        $config = new PSSH_Config($this);

        $this->hr();
        $this->output('ADDING SSH HOST');
        $this->hr();

        // Determine file to write to
        if (is_null($target))
        {
            $target = $this->select($this->json_config_paths, 'Config File');
        }
        $config->readJson($target);

        // Hostname
        if (is_null($hostname))
        {
            $hostname = $this->input('HostName (URL/IP)', null, true);
        }
        else
        {
            $this->output("HostName (URL/IP): $hostname");
        }
        $clean_hostname = $config->cleanHostname($hostname);
        if ($clean_hostname != $hostname)
        {
            $hostname = $clean_hostname;
            $this->output(" ($hostname)");
        }

        // User Name
        if (is_null($user))
        {
            $existing = $config->find(['hostname'=>$hostname]);
            $existing_users = array_keys($existing['hostname']);
            if (!empty($existing_users))
            {
                $this->output("NOTE: existing users configured for this hostname: (" . join(", ", $existing_users) . ")");
            }
            $user = $this->input('User', null, true);
        }
        else
        {
            $this->output("User: $user");
        }

        // Host Alias
        if (is_null($alias))
        {
            $default = $config->autoAlias($user);
            $alias = $this->input('Alias', $default);
        }
        else
        {
            $this->output("Alias: $alias");
        }

        // Port
        if (is_null($port))
        {
            // for specified hostname
            $port = $this->input('Port', 22);
        }
        else
        {
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
        if (!$success)
        {
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
        $this->init_host($alias);

        $this->hr();
        $this->output('Done!');
    }

    protected $___backup = [
        "Backup a file or files to the pssh backup folder",
        ["Paths to back up", "string", "required"],
    ];
    public function backup($files)
    {
        $success = true;

        $files = $this->prepArg($files, []);

        if (!is_dir($this->backup_dir))
            mkdir($this->backup_dir, 0755, true);

        foreach ($files as $file)
        {
            // $this->log("Backing up $file...");
            if (!is_file($file))
            {
                // $this->log(" - Does not exist - skipping");
                continue;
            }

            $backup_file = $this->backup_dir . DS . basename($file) . '-' . $this->stamp() . '.bak';
            // $this->log(" - copying to $backup_file");

            // Back up target
            $success = ($success and copy($file, $backup_file));
        }
        
        if (!$success) $this->error('Unable to back up one or more files');
        
        return $success;
    }

    protected $___clean = [
        "Clean json config files",
        ["JSON file(s) to clean - defaults to json-config-paths", "string"],
    ];
    public function clean($paths=null)
    {
        $paths = $this->prepArg($paths, $this->json_config_paths);

        $this->log("Cleaning config files:");
        $this->log($paths);

        $this->log("Backing up...");
        $this->backup($paths);

        foreach ($paths as $path)
        {
            $this->log("Cleaning '$path'");
            $config = new PSSH_Config($this);
            $config->readJSON($path);
            $config->clean();
            $config->writeJSON($path);
        }
    }

    protected $___export = [
        "Export JSON config to SSH config file",
        ["Source JSON files - defaults to json-config-paths", "string"],
        ["Target SSH config file - defaults to ssh-config-path", "string"],
    ];
	public function export($sources=[], $target=null)
	{
        $target = $this->prepArg($target, $this->ssh_config_path);
        $sources = $this->prepArg($sources, $this->json_config_paths);

        $this->backup($target);

        $config = new PSSH_Config($this);
        $config->readJSON($sources);
        $config->clean();
        $config->writeSSH($target);
    }

    protected $___import = [
        "Import SSH config data into JSON",
        ["Target JSON file - defaults to json-import-path", "string"],
        ["Source SSH config file - defaults to ssh-config-path"],
    ];
	public function import($target=null, $source=null)
	{
        // Defaults
        $target = $this->prepArg($target, $this->json_import_path);
        $source = $this->prepArg($source, $this->ssh_config_path);

        $this->backup($target);

        $config = new PSSH_Config($this);
        $config->readSSH($source);
        $config->clean();
        $config->writeJSON($target);
	}

    protected $___init_host = [
        "Initialize host - interactive, or specify options",
        ["Alias of host", "string", "required"],
        ["Whether to copy your ssh key to the server", "boolean"],
        ["Whether to set up server CLI using cli_script", "boolean"],
    ];
    public function init_host($alias, $key=null, $cli=null) {

        // Copy Key?
        if (is_null($key))
        {
            $key = $this->confirm('Copy Key?');
        }
        if ($key)
        {
            $this->output('Enter ssh password for this host if prompted');
            $this->exec("ssh-copy-id $alias");
        }

        // Set up Custom CLI Tools?
        if (is_null($cli) and !empty($this->cli_script) and is_file($this->cli_script))
        {
            $cli = $this->confirm('Set up server cli tools?');
        }
        if ($cli)
        {
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
    }

    protected $___search = [
        "Search for host configuration",
        ["Term(s) to search - separate with spaces", "string", "required"],
        ["JSON config path(s) to search - defaults to json-config-paths", "string"],
    ];
    public function search($terms, $paths=null)
    {
        $paths = $this->prepArg($paths, $this->json_config_paths);

        $this->log("Searching config files:");
        $this->log($paths);

        $config = new PSSH_Config($this);
        $config->readJSON($paths);

        $results = $config->search($terms);

        if (empty($results))
        {
            $this->output("No results found");
        }
        else
        {
            $count = count($results);
            $this->hr();
            $this->output("Found $count total matches: ");
            $this->hr();

            foreach ($results as $r => $result)
            {
                if ($r > 0 and $r%5 == 0)
                {
                    $this->hr();
                    $key = $this->input("[ c - SHOW MORE | q - QUIT ]", 'c', false, true);
                    if ($key == 'q')
                    {
                        break;
                    }
                    $this->hr();
                }
                $this->output($result, false);
            }
            $this->hr();
        }
    }

    /**
     * 
     *  - currently supports private git repository
     */
    protected $___sync = "Sync config files based on 'sync' config/option value";
    public function sync()
    {
        if (empty($this->sync)) return;

        $this->output('Syncing...');

        if (substr($this->sync, 0, 4) == 'git@')
        {
            // Temporarily switch to config_dir
            $original_dir = getcwd();
            chdir($this->config_dir);

            // Set up git if not already done
            if (!is_dir($this->config_dir . DS . '.git'))
            {
                // $this->log('Running commands to initialize git');
                $this->exec("git init");
                $this->exec("git remote add sync {$this->sync}");
            }

            // Pull
            // $this->log('Pulling from remote (sync)');
            $this->exec("git pull sync master");

            // Set up git ignore if not already there
            if (!is_file($this->config_dir . DS . '.gitignore'))
            {
                // $this->log('Setting up default ignore file');
                $synced_config_json = empty($this->json_config_paths)
                    ? ''
                    : '!' . array_unshift($this->json_config_paths);
                $ignore = <<<GITGNORE
*
!.gitignore
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
        if (is_file($cli_script))
        {
            $this->cli_script = $cli_script;
        }

        $this->backup_dir = $config_dir . DS . 'backups';

        parent::initConfig();
    }
}
PSSH::run($argv);
?>
