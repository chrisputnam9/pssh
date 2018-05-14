# Getting Started

Quick getting started information for the most common use case.

## Install PSSH
1. Make sure you have PHP, or install it if not
2. Clone the pssh repository to the location of your choice

        cd /opt
        git clone https://github.com/chrisputnam9/pssh.git

3. Move/symlink into path, add pssh directory into path, or set up alias

        ln -s /opt/pssh/pssh /usr/local/bin/pssh

4. Re-pull as needed to update

        cd /opt/pssh
        git pull origin master

## Import Existing Config
1. Import your current config to a new JSON file (~/.pssh/ssh_config_imported.json)

        pssh import

2. Add git URL for your company's shared SSH config into ~/.pssh/config.json (sync:), then run:

        pssh sync

3. Merge your imported json into your synced work json file, putting overrides into a personal file
   (this is automatically ignored by the default .gitignore, so you'll need to sync/back this up yourself as needed)

        pssh merge ~/.pssh/ssh_config_imported.json ~/.pssh/ssh_config_work.json ~/.pssh/ssh_config_personal.json

4. Review the personal file - conflicts will be placed here, some may require manual adjustments.

5. Diff the work file to make sure you don't sync something you wanted to keep private!  Move host
   entries to personal file as needed.

        git difftool

4. Sync again when ready

        pssh sync

# Config File Details

Coming Soon

# Usage

Coming Soon
