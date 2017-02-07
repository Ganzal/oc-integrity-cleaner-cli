OC Integrity Cleaner
====================

Simple utility for removing<sup>[1]</sup> extra files from ownCloud instance.

Use it when you see message `There were problems with the code integrity check` and integrity report contains only extra files.

**[1]** Actually extra files only moves outside OC instance directory.



### Requirements

1. [onwCloud](https://owncloud.org/) instance with extra files listed in integrity report.
2. [php-cli](https://php.net) SAPI to run script.
3. [bash](http://www.gnu.org/software/bash/) interpreter.

Tested on **Ubuntu Linux 14.04 LTS** so **Linux**-based OS and **Debian/Ubuntu** distro are recommended.



### Installation

1. Copy PHP file to prefered location.
(eg. `/opt/oc-integrity-cleaner-cli/oc-integrity-cleaner-cli.php`)
2. *Optional.* *[root]* Create launcher script in PATH (for all users)

```shell
cat > /usr/bin/ocic <<EOL
#!/bin/bash
/usr/bin/env php -f /opt/oc-integrity-cleaner-cli/oc-integrity-cleaner-cli.php $@
EOL

chmod +x /usr/bin/ocic
```
*[any user]* or create alias
```shell
echo 'alias ocic="/usr/bin/env php -f /opt/oc-integrity-cleaner-cli/oc-integrity-cleaner-cli.php"' >> ~/.bash_aliases
. ~/.bash_aliases
```
That's all folks.



### Usage

1. Switch to OC instance root directory (eg. `cd /var/www/owncloud`)
2. Exec **oc-integrity-cleaner-cli.php** script.
```shell
php -f /path/to/oc-integrity-cleaner-cli.php
# or just
ocic
```
3. Enter your OC instance admin credentials and watch for result.
 * Extra files will moved to backup directory located at `~/oc-integrity-cleaner-backup`
4. *[Web]* Login to your OC instance and go to admin area.
5. *[Web]* Rescan integrity.
5. Repeat steps 2, 3 and 5 while report contains extra files.



## License

MIT License: see the [LICENSE](LICENSE) file.

*eof*
