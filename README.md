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

# Usage

    USAGE:
        pssh <method> (argument1) (argument2) ... [options]

    METHODS (ARGUMENTS):
        add ( target hostname user alias port )
        backup ( files )
        clean ( paths )
        export ( sources target )
        import ( target source )
        init_host ( alias key cli )
        merge ( source_path target_path override_path )
        sync ( )
        help ( )

    OPTIONS:
        --json_config_paths
        --json_import_path
        --ssh_config_path
        --cli_script
        --sync
        --backup_dir
        --verbose
        --stamp_lines
        --step

    Note: for true/false options, prefix no- to set to fales. For example:

        pssh export --no-sync

# Config File
Options can be set in config. Options in config will be overridden by those passed by flags.

**Sample:**

    {
        "backup_dir": "~/.pssh/backups",
        "cli_script": "~/.pssh/ssh_cli.sh",
        "json_config_paths": [
            "~/.pssh/ssh_config_work.json",
            "~/.pssh/ssh_config_personal.json"
        ],
        "json_import_path": "~/.pssh/ssh_config_imported.json",
        "ssh_config_path": "~/.ssh/config",
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
