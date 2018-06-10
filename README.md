# Getting Started

Quick getting started information for the most common use case.

## Install PSSH
1. Make sure you have PHP, or [install it if not](http://php.net/manual/en/install.php

2. Clone the pssh repository to the location of your choice

        cd /opt
        git clone https://github.com/chrisputnam9/pssh.git

3. Move/symlink into path, add pssh directory into path, or set up alias

        ln -s /opt/pssh/pssh /usr/local/bin/pssh

4. Re-pull as needed to update

        cd /opt/pssh
        git pull origin master

## Import Existing Config
1. Import your current config to a new JSON file (~/.pssh/ssh\_config\_imported.json)

        pssh import

2. Add git URL for your company's shared SSH config into ~/.pssh/config.json (sync:), then run:

        pssh sync

3. Merge your imported json into your synced work json file, putting overrides into a personal file
   (this is automatically ignored by the default .gitignore, so you'll need to sync/back this up yourself as needed)

        pssh merge ~/.pssh/ssh_config_imported.json ~/.pssh/ssh_config_work.json ~/.pssh/ssh_config_personal.json

4. Review the personal file - conflicts will be placed here, some may require manual adjustments.

5. Diff the work file to make sure you don't sync something you wanted to keep private!  Move host
   entries to personal file as needed.

        cd ~/.pssh
        git difftool

6. Export new JSON files to ssh config and test

        pssh export
        ssh oneofyourhosts

7. Sync again when ready

        pssh sync

# USAGE:

    pssh <method> (argument1) (argument2) ... [options]

    ----------------------------------------------------------------------------------------------------
    | METHOD                   | INFO                                                                  |
    ----------------------------------------------------------------------------------------------------
    | add                      | Add new SSH host - interactive, or specify options                    |
    | backup                   | Backup a file or files to the pssh backup folder                      |
    | clean                    | Clean json config files                                               |
    | export                   | Export JSON config to SSH config file                                 |
    | import                   | Import SSH config data into JSON                                      |
    | init_host                | Initialize host - interactive, or specify options                     |
    | merge                    | Merge config from one JSON file into another                          |
    | search                   | Search for host configuration                                         |
    | sync                     | Sync config files based on 'sync' config/option value                 |
    | help                     | Shows help/usage information.                                         |
    ----------------------------------------------------------------------------------------------------
    To get more help for a specific method:  pssh help <method>

    ----------------------------------------------------------------------------------------------------
    | OPTION                   | TYPE         | INFO                                                   |
    ----------------------------------------------------------------------------------------------------
    | --json-config-paths      | (string)     | Main JSON config file paths                            |
    | --json-import-path       | (string)     | Default JSON config import path                        |
    | --ssh-config-path        | (string)     | Default SSH config path                                |
    | --cli-script             | (string)     | CLI script to install on hosts during init             |
    | --sync                   | (string)     | Git SSH URL to sync config data                        |
    | --backup-dir             | (string)     | Default backup directory                               |
    | --stamp-lines            | (boolean)    | Stamp output lines                                     |
    | --step                   | (boolean)    | Enable stepping points                                 |
    | --verbose                | (boolean)    | Enable verbose output                                  |
    ----------------------------------------------------------------------------------------------------
    Use no- to set boolean option to false - eg. --no-stamp-lines

# Config File
Options can be set in config. Options in config will be overridden by those passed by flags.

**Sample:**

    {
        "backup_dir": "/home/user/.pssh/backups",
        "cli_script": "/home/user/.pssh/ssh_cli.sh",
        "json_config_paths": [
            "/home/user/.pssh/ssh_config_work.json",
            "/home/user/.pssh/ssh_config_personal.json"
        ],
        "json_import_path": "/home/user/.pssh/ssh_config_imported.json",
        "ssh_config_path": "/home/user/.ssh/config",
        "stamp_lines": false,
        "step": false,
        "sync": "git@...",
        "verbose": false
    }

# Miscellaneous
Many thanks to all who've helped with suggestions, testing, and motivation!

- [Theodore Slechta](https://github.com/theodoreslechta)
- Mark Johnson
- Paul Cohen
- Everyone who takes the time to use and test this!
