**To install emoncms on a Raspberry Pi see new installation script approach:<br>https://github.com/openenergymonitor/EmonScripts**

---

## Archived: Install Emoncms on Raspberry Pi (Raspbian Jessie)

This guide will install the current full version of emoncms onto a Raspberry Pi running the Raspbian Jessie operating system.

**Highly Recommended: A pre-built Raspberry Pi SD card image (based on Raspbian Jessie lite) is available with Emoncms pre-installed & optimised for low-write. [SD card image download & change log repository](https://github.com/openenergymonitor/emonpi/wiki/emonSD-pre-built-SD-card-Download-&-Change-Log). Full image build guide/notes are available [here](https://github.com/openenergymonitor/emonpi/blob/master/docs/SD-card-build.md).**

An alternative (older) installation guide is [avaliable for Raspbian Wheezy](install_Wheezy.md) - they are different, so ensure that you use the correct guide!  

Due to the number of writes that the full version of emoncms makes, the lifespan of an SD card will almost certainly be shortened, and it is therefore recommended that you eventually [move the operating system partition (root) to an USB HDD](USB_HDD.md) or to lower the write frequency to the SD card by enabling the [low-write mode.](Low-write-mode.md)  
Before installing emoncms, it is essential you have a working version of Raspbian Jessie installed on your Raspberry Pi. If not, head over to [raspberrypi.org](https://www.raspberrypi.org/documentation/installation/installing-images/README.md) and follow their installation guide.

### Preparation

Start by updating the system repositories and packages:

    sudo apt-get update && sudo apt-get upgrade

Install the dependencies:

    sudo apt-get install -y apache2 mysql-server mysql-client php5 libapache2-mod-php5 php5-mysql php5-curl php-pear php5-dev php5-mcrypt php5-common php5-redis git-core redis-server build-essential ufw ntp

During the installation, you will be prompted to select a password for the 'MYSQL "root" user', and to confirm it by entering it a second time. Make a note of the password - you will need it later

**March 2016: The version of php5-redis included in the Raspbian Jessie sources (2.2.5-1) caused Apache to crash (segmentation errrors in Apache error log). Installing the latest stable version (2.2.7) of php5-redis from github fixed the issue. This step probably won't be required in the future when the updated version of php5-redis makes it's way into the sources.**

**February 2017: phpredis v3.1.1 has been released supporting both php5 and php7. It can be installed direct from PECL and is called simply: redis. This guide will be updated to reflect this change once tested in due course.**

To check the version in the sources: `sudo apt-cache show php5-redis`

To fix:
```
git clone --branch 2.2.7 https://github.com/phpredis/phpredis
cd phpredis
(check the version we are about to install:)
???cat php_redis.h | grep VERSION
phpize
./configure 
sudo make 
sudo make install
```

Install the pecl dependencies (serial, redis and swift mailer):

    sudo pear channel-discover pear.swiftmailer.org
    sudo pecl install channel://pecl.php.net/dio-0.0.6 redis swift/swift

Add the pecl modules to php5 config:

    sudo sh -c 'echo "extension=dio.so" > /etc/php5/apache2/conf.d/20-dio.ini'
    sudo sh -c 'echo "extension=dio.so" > /etc/php5/cli/conf.d/20-dio.ini'
    sudo sh -c 'echo "extension=redis.so" > /etc/php5/apache2/conf.d/20-redis.ini'
    sudo sh -c 'echo "extension=redis.so" > /etc/php5/cli/conf.d/20-redis.ini'

Issue the command:

    sudo a2enmod rewrite

For `<Directory />` and `<Directory /var/www/>` change `AllowOverride None` to `AllowOverride All`. This should be on lines 155 and 166 of `/etc/apache2/apache2.conf`

    sudo nano /etc/apache2/apache2.conf

Save & exit, then restart Apache:

    sudo /etc/init.d/apache2 restart

### Install the emoncms application via git

Git is a source code management and revision control system but at this stage we use it to just download and update the emoncms application.

First, set the permissions for the www directory:

    sudo chown $USER /var/www

Cd into the www directory and git clone emoncms:

    cd /var/www && git clone -b stable https://github.com/emoncms/emoncms.git

### Create a MYSQL database

    mysql -u root -p

When prompted, enter the 'MYSQL "root" user' password you were prompted for earlier in this procedure.
Create the emoncms database using utf8 character decoding:

    CREATE DATABASE emoncms DEFAULT CHARACTER SET utf8;

Add an emoncms database user and set that user's permissions.
In the command below, we're creating the database 'user' named 'emoncms', and you should create a new secure password of your choice for that user.
Make a note of both the database 'username' ('emoncms') & the 'new_secure_password'. They will be inserted into the settings.php file in a later step:

    CREATE USER 'emoncms'@'localhost' IDENTIFIED BY 'new_secure_password';
    GRANT ALL ON emoncms.* TO 'emoncms'@'localhost';
    flush privileges;

Exit mysql:

    exit

### Create data repositories for emoncms feed engines:

    sudo mkdir /var/lib/{phpfina,phptimeseries}

and set their permissions

    sudo chown www-data:root /var/lib/{phpfina,phptimeseries}

### Configure emoncms database settings

Make a copy of default.settings.php and call it settings.php:

    cd /var/www/emoncms && cp default.settings.php settings.php

Open settings.php in an editor:

    nano settings.php

Update your settings to use your Database 'user' & 'password', which will enable emoncms to access the database:

    $server   = "localhost";
    $database = "emoncms";
    $username = "emoncms";
    $password = "new_secure_password";
    
That's also the opportunity to activate redis support if needed :

	//2 #### Redis
	$redis_enabled = true;

Save and exit.  
Create a symlink to reference emoncms within the web root folder:

    cd /var/www/html && sudo ln -s /var/www/emoncms


Set write permissions for the emoncms logfile:

`sudo touch /var/log/emoncms/emoncms.log` followed by  
`sudo chmod 666 /var/log/emoncms/emoncms.log`

### In an internet browser, load emoncms:

[http://localhost/emoncms](http://localhost/emoncms)

- Create an account on Emoncms, the first account to be created will by default be the admin account. 

If you want Emoncms to redirect from web root i.e load Emoncms with `http://localhost` add reference in `index.php` and remove the default apache `index.html` test page:

	sudo su
	echo "<?php header('Location: ../emoncms'); ?>" > /var/www/html/index.php
	rm /var/www/html/index.html
	exit

The first time you run emoncms it will automatically set up the database and you will be taken to the register/login screen.
Create an account by entering your email and password and clicking register.  

Once you are logged in;  
* Check the Administration page - 'Setup > Administration' noting and acting upon any messages reported.
* Update your database - 'Setup > Administration > Update database'.
* Make a note of your 'Write API Key' from the 'Setup > My Account' page, and also ensure that the correct timezone is selected & saved.

### Install Emonhub

    git clone https://github.com/emonhub/dev-emonhub.git ~/dev-emonhub && ~/dev-emonhub/upgrade

Edit the emonhub configuration file, entering your emoncms 'Write API Key' and set the "local" emoncms address `url = http://localhost/emoncms` (emonhub sends to http://emoncms.org by default). Also set your RFM2Pi frequency, group & base id if necessary:

    nano /etc/emonhub/emonhub.conf

Save & exit.  
Edit the cmdline.txt file:

    sudo nano /boot/cmdline.txt

by changing the line to - `dwc_otg.lpm_enable=0 console=tty1 console=tty1 root=/dev/mmcblk0p2 rootfstype=ext4 elevator=deadline fsck.repair=yes rootwait`  
At this stage, power off your Raspberry Pi:

    sudo poweroff

Once your Pi has stopped, disconnect the power lead and connect your RFM69Pi add-on board, ensuring it's positioned correctly (see the photos in the OEM shop pages).

**You should now have a fully working version of emoncms installed on your Raspberry Pi, if at this stage you don't, you may wish to check the emoncms log - 'Setup > Administration > Logger' or report the issue in the [OEM forum](http://openenergymonitor.org/emon/forum) giving as much detail as possible.**

### System Options
* [Move the operating system partition (root) to an USB HDD](USB_HDD.md)
* [Enabling low-write mode](Low-write-mode.md)
* [Enabling MQTT](MQTT.md)
* [Installing emoncms Modules](general.md#module-installation)
* [Updating emoncms](general.md#updating-emoncms-via-git)  
* [System Logs](general.md#system-logs)
