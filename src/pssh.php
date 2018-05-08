<?php
/**
 * PSSH Console Interface
 */
class PSSH extends Console_Abstract
{
    /**
     * Callable Methods
     */
    protected const METHODS = array(
        'add',
        'backup',
        'clean',
        'export',
        'import',
        'merge',
        'sync',
    );

    // Name of directory to store config
    protected const CONFIG_DIR = 'pssh';
    protected $config_dir = null;

    // Config defaults
    public $json_config_paths = array();
    public $json_import_path = null;
    public $ssh_config_path = null;

    public $sync = '';
    public $backup_dir = null;

    /**
     * Add new SSH host interactively
     */
    public function add ()
    {
        $this->log('add');

        $this->sync();

        $this->output('ADDING SSH HOST');

        // todo 

        $this->input('Enter HostName (URL/IP): ');

        $this->sync();
    }

    /**
     * Clean json config files
     *  - import, clean, re-export
     */
    public function clean ($path=null)
    {
        $this->log('clean');
        if (is_null($path))
        {
            $paths = $this->json_config_paths;
        }
        else
        {
            $paths = array($path);
        }

        $this->backup($paths);

        foreach ($paths as $path)
        {
            $this->log($path);
            $config = new PSSH_Config($this);
            $config->readJSON($path);
            $config->clean();
            $config->writeJSON($path);
        }
    }

    /**
     * Sync - if configured
     *  - for now, just supports private git repository
     */
    public function sync ()
    {
        $this->log('sync');
        if (empty($this->sync)) return;

        if (substr($this->sync, 0, 4) == 'git@')
        {
            // Temporarily switch to config_dir
            $original_dir = getcwd();
            chdir($this->config_dir);

            // Set up git if not already done
            if (!is_dir($this->config_dir . '/.git'))
            {
                $this->log('Running commands to initialize git');
                $this->exec("git init");
                $this->exec("git remote add sync {$this->sync}");
            }

            // Pull
            $this->log('Pulling from remote (sync)');
            $this->exec("git pull sync master");

            // Set up git ignore if not already there
            if (!is_file($this->config_dir . '/.gitignore'))
            {
                $this->log('Setting up default ignore file');
                $synced_config_json = empty($this->json_config_paths)
                    ? ''
                    : '!' . array_unshift($this->json_config_paths);
                $ignore = <<<GITGNORE
*
!.gitignore
{$synced_config_json}
GITGNORE;
                file_put_contents($this->config_dir . '/.gitignore', $ignore);
            }

            // Push
            $this->log('Committing and pushing to remote (sync)');
            $this->exec("git add . --all");
            $this->exec("git commit -m \"Automatic sync commit - {$this->stamp()}\"");
            $this->exec("git push sync master");

            // Switch back to original directory
            chdir($original_dir);
        }
    }

	/**
	 * Import SSH config data into JSON
	 * @param $target - target file to save JSON
	 * @param $source - source ssh config file
	 */
	public function import ($target=null, $source=null)
	{
		$this->log('import');

        // Defaults
        if (empty($target)) $target = $this->json_import_path;
		if (empty($source)) $source = $this->ssh_config_path;

        $this->backup($target);

        $config = new PSSH_Config($this);
        $config->readSSH($source);
        $config->clean();
        $config->writeJSON($target);

        $this->log('finished');
	}

	/**
	 * Export JSON config to SSH config file
     * @param $sources - source JSON files - to be merged in order
	 * @param $target - source ssh config file
	 */
	public function export ($sources=array(), $target=null)
	{
		$this->log('export');

		if (empty($target)) $target = $this->ssh_config_path;
        if (empty($sources))
        {
            $sources = $this->json_config_paths;
        }

        $this->backup($target);

        $config = new PSSH_Config($this);
        $config->readJSON($sources);
        $config->clean();
        $config->writeSSH($target);

        $this->log("Done!");
		fclose($target_handle);
    }

    /**
     * Backup a file or files to the pssh backup folder
     * @param $files - string path or array of string paths
     */
    public function backup($files)
    {
        $success = true;

        $this->log('backup');
        $this->log($files);

        if (is_string($files)) $files = array($files);

        if (!is_dir($this->backup_dir))
            mkdir($this->backup_dir, 0755, true);

        foreach ($files as $file)
        {
            $this->log("Backing up $file...");
            if (!is_file($file))
            {
                $this->log(" - Does not exist - skipping");
                continue;
            }

            $backup_file = $this->backup_dir . '/' . basename($file) . '-' . $this->stamp() . '.bak';
            $this->log(" - copying to $backup_file");

            // Back up target
            $success = ($success and copy($file, $backup_file));
        }
        
        if (!$success) $this->error('Unable to back up one or more files');
        
        return $success;
    }
}
PSSH::run($argv);
?>
