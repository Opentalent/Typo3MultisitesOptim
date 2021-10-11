# Typo3 Multisites Optimization

This project provides:

* a docker container hosting a typo3 10.4 instance 
* an extension which allow to populate the db with any number of basic websites
* an extension that proposes some patches to improve performance with a large amount of websites

To setup:

    git clone ...
    cd multisites_optim
    docker-compose build
    docker-compose up
    docker exec -it multisites_optim_typo3 bash
