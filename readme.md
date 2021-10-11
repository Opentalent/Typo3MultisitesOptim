# Typo3 Multisites Optimization

This project provides:

* a docker container hosting a typo3 10.4 instance 
* an extension which allow to populate the db with any number of basic websites
* an extension that proposes some patches to improve performance with a large amount of websites

### Setup the container

To setup:

    git clone ...
    cd multisites_optim
    docker-compose build
    docker-compose up
    docker exec -it multisites_optim_typo3 bash

> This typo3 container is listening by default on port 8080, change it in the docker-compose.yml file if needed
> The mysql db is listening by default on port 3307, change it in the docker-compose.yml file if needed

Access to [http://localhost:8080](http://localhost:8080)

Process to the installation, configuration shall be already set.

Access to the typo3 BE:

user: typo3
password: typo3000+

Ensure the extensions 'websites' and 'populate' are enabled, then run a database analysis.
Apply the suggested changes, if any.

### Populate the DB

To populate the DB with 3000 websites (default):

    php /var/www/html/typo3/sysext/core/bin/typo3 ot:populate

Or specify the amount of websites you need:

    php /var/www/html/typo3/sysext/core/bin/typo3 ot:populate 1500

### Control the result

Try to display a page, without and with the 'optimizer' extension enabled.
