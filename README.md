# PSSH - PHP SSH Configuration Management Tool
This tool aids in managing your SSH configuration file (~/.ssh/config)

Primary features include:
 - Ability to sync and share SSH configuration among multiple users
 - Abiliity to merge multiple configurations - ie. personal and work
 - Interface to manage configurations - ie. search, edit, delete, and create new host configurations
 - Ability to initialize hosts - copy individual/team keys and optionally run a custom script install

# Latest Version

## Download Latest Version (2.5.0):
https://raw.githubusercontent.com/chrisputnam9/pssh/master/dist/pssh

## Latest Version Hash (md5):
42fa117fb685548c16f87d4117509b04

# Getting Started

Quick getting started information for the most common use case.

## Install PSSH
1. Make sure you have PHP, or [install it if not](http://php.net/manual/en/install.php)

2. Run this code in a download folder or temporary location:

        curl https://raw.githubusercontent.com/chrisputnam9/pssh/master/dist/pssh > pssh
        chmod +x pssh
        sudo ./pssh install

3. Test success by running in a new terminal session:

        pssh version

## Import Existing Config
1. Add git URL for your company's shared SSH config into ~/.pssh/config.hjson (sync:), then run:

        pssh sync

2. Import your current config to a new JSON file (~/.pssh/ssh\_config\_imported.json)

        pssh import

3. Merge your imported json into your synced work json file, putting overrides into a personal file
   ('ssh_config_personal.json' is automatically ignored by the default .gitignore, so you'll need to sync/back this up yourself as needed)

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

# Updating
The script will periodically check for updates automatically and inform you when an update is
available.

If an update is available, you can run the following to install the update:

    sudo pssh update

# USAGE:

    pssh <method> (argument1) (argument2) ... [options]

    -------------------------------------------------------------------------------------------------------------------------------------------------------
    | METHOD                        | INFO                                                                                                                |
    -------------------------------------------------------------------------------------------------------------------------------------------------------
    | add                           | Add new SSH host - interactive, or specify options                                                                  |
    | backup                        | Backup a file or files to the configured backup folder                                                              |
    | clean                         | Clean json config files                                                                                             |
    | clear                         | Clear the screen                                                                                                    |
    | delete_host                   | Delete host                                                                                                         |
    | edit_host                     | Edit host - modify config in your editor                                                                            |
    | eval_file                     | Evaluate a php script file, which will have access to all internal methods via '$this'                              |
    | exit                          | Exit the command prompt                                                                                             |
    | export                        | Export JSON config to SSH config file                                                                               |
    | help                          | Shows help/usage information.                                                                                       |
    | import                        | Import SSH config data into JSON                                                                                    |
    | init_host                     | Initialize host - interactive, or specify options                                                                   |
    | install                       | Install a packaged PHP console tool                                                                                 |
    | list                          | List all hosts                                                                                                      |
    | merge                         | Merge config from one JSON file into another                                                                        |
    | prompt                        | Show interactive prompt                                                                                             |
    | search                        | Search for host configuration                                                                                       |
    | sync                          | Sync config files based on 'sync' config/option value                                                               |
    | update                        | Update an installed PHP console tool                                                                                |
    | version                       | Output version information                                                                                          |
    -------------------------------------------------------------------------------------------------------------------------------------------------------
    To get more help for a specific method:  pssh help <method>

    -------------------------------------------------------------------------------------------------------------------------------------------------------
    | OPTION                        | TYPE                                       | INFO                                                                   |
    -------------------------------------------------------------------------------------------------------------------------------------------------------
    | ----WSC--                     | (boolean)                                  | HJSON Data for config file                                             |
    | --allow-root                  | (boolean)                                  | OK to run as root                                                      |
    | --backup-age-limit            | (string)                                   | Age limit of backups to keep- number of days                           |
    | --backup-dir                  | (string)                                   | Location to save backups                                               |
    | --cache-lifetime              | (boolean)                                  | Default time to cache data in seconds                                  |
    | --cli-script                  | (string)                                   | CLI script to install on hosts during init                             |
    | --install-path                | (string)                                   | Install path of this tool                                              |
    | --json-config-paths           | (string)                                   | Main JSON config file paths                                            |
    | --json-import-path            | (string)                                   | Default JSON config import path                                        |
    | --ssh-config-path             | (string)                                   | Default SSH config path                                                |
    | --ssl-check                   | (boolean)                                  | Whether to check SSL certificates with curl                            |
    | --stamp-lines                 | (boolean)                                  | Stamp output lines                                                     |
    | --step                        | (boolean)                                  | Enable stepping points                                                 |
    | --sync                        | (string)                                   | Git SSH URL to sync config data                                        |
    | --timezone                    | (string)                                   | Timezone - from http://php.net/manual/en/timezones.                    |
    | --update-auto                 | (int)                                      | How often to automatically check for an update (seconds, 0 to disable) |
    | --update-check-hash           | (binary)                                   | Whether to check hash of download when updating                        |
    | --update-last-check           | (string)                                   | Formatted timestap of last update check                                |
    | --update-version-url          | (string)                                   | URL to check for latest version number info                            |
    | --verbose                     | (boolean)                                  | Enable verbose output                                                  |
    -------------------------------------------------------------------------------------------------------------------------------------------------------
    Use no- to set boolean option to false - eg. --no-stamp-lines
    =======================================================================================================================================================

# Troubleshooting & Common Issues

## See General PCon Issues
[See general PCon issues which may apply](https://github.com/chrisputnam9/pcon#troubleshooting--common-issues)

## Error about /root/.ssh/config
During install, in some cases, ~/.pssh/config.hjson could have root's ssh config path set instead of yours.

Edit your ~/.pssh/config.hjson - search for 'root' and fix any incorrect paths to point to your actual files (typically /home/youruser/... eg. /home/youruser/.ssh/config).

# Miscellaneous
Many thanks to all who've helped with suggestions, testing, and motivation!

- [Theodore Slechta](https://github.com/theodoreslechta)
- Mark Johnson
- [Paul Cohen](https://github.com/pcohen12)
- Josh Quenga
- Everyone else who takes the time to use and test this!
