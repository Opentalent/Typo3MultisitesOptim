# Typo3 Multisites Optimization

This project provides:

* a docker container hosting a typo3 10.4 instance 
* an extension providing a CLI command to populate the db with any number of basic websites
* an extension that proposes some patches to improve performance with a large amount of websites

### Usage

#### Setup the container

To setup:

    git clone ...
    cd multisites_optim
    docker-compose build
    docker-compose up

> This typo3 container is listening by default on port 8080, change it in the docker-compose.yml file if needed
> The mysql db is listening by default on port 3307, change it in the docker-compose.yml file if needed

Access to [http://localhost:8080](http://localhost:8080)

Process to the installation, configuration shall be already set.

Access to the typo3 BE:

user: typo3
password: typo3000+

Ensure the extensions 'websites' and 'populate' are enabled, then run a database analysis.
Apply the suggested changes, if any.

#### Populate the DB

To populate the DB with 3000 websites (default):

    docker-compose exec multisites_optim_typo3 php /var/www/html/typo3/sysext/core/bin/typo3 ot:populate

Or specify the amount of websites you need:

    docker-compose exec multisites_optim_typo3 php /var/www/html/typo3/sysext/core/bin/typo3 ot:populate 1500

> If needed, you can also use the command `php /var/www/html/typo3/sysext/core/bin/typo3 ot:clear-db` to remove all
> the websites created with the populate command

#### Control the result

Try to display a page, without and with the 'optimizer' extension enabled.

If you possess a blackfire account, you can also run it against your Typo3 instance, before and after the populate,
with the command:

    docker-composer exec multisites_optim_blackfire blackfire curl http://local.typo3.net/website1000

Don't forget to replace the tested url according to your typo3 instance.

> To use blackfire, you'll have to create a file named `.env` at the root of your project, containing the following lines:
> `BLACKFIRE_CLIENT_ID=<value>`
> `BLACKFIRE_CLIENT_TOKEN=<value>`
> `BLACKFIRE_SERVER_ID=<value>`
> `BLACKFIRE_SERVER_TOKEN=<value>`
>
> You can find the corresponding ids on your blackfire profile page


You can also consult those two blackfire reports, executed on the same instance of typo3, with 3510 websites 
and the cache flushed before each one:

* With **optimizer extension disabled**: <https://blackfire.io/profiles/1ccb80ed-08ae-40e3-8106-855039581ee8/graph>
* With **optimizer extension enabled**: <https://blackfire.io/profiles/23e7967a-13b4-4db4-baad-a4b51d49112c/graph>


### Context

Typo3 allows to host multiple websites on a single instance, which makes possible to use such an instance
as a 'websites factory' hosting thousands of websites at once, with dedicated templates, contents, admin and editors,
file storage...

However, performances become a real problem when this websites number begins to grow.

First of all, the website configuration system introduced with Typo3 9  and implying one yaml file per website 
caused a huge performance loss. The time needed to parse 3500 files is really long, and opening the "Sites" 
backend module can last something like 30sec. We had to rise the php limit about the max number of files that 
it can maintain open at the same time.

The website and page resolution were also problematics. 
In their primitive form, they triggered one or two db query per site, meaning that each page displayed made around 7000 
db queries each time! Not only the loading time was near to 6secs, but our hosting machine had some bad times...

We fixed these issues by:
* rebuilding an inbase website configuration (we've got a 'website' table hosting those informations). The 'pages' table got a new foreign key linking it to this new table.
* overriding the \TYPO3\CMS\Frontend\Middleware\PageResolver middleware to resolve the website first with one db query on this 'website' table, then
  a second query in the 'pages' table to find the suited page. From 7000 queries, we're now to only 2.
* also, xclassing the now named TYPO3\CMS\Core\Routing\PageSlugCandidateProvider class, precisely the getPagesFromDatabaseForCandidates method. the way this method is designed makes the while loop to call getSiteByPageId once for each page matching the given 'slug'. But with 3500 websites, we've also got 3500 pages with the '/' slug...


### Concepts

The core of the optimization is related to the routing system. For the sake of simplicity, we will only consider
the `http://localhost/subdomain` url form.

In a real prod env, this should depends on the env:

* `http://<subdomain>.my-domain.com` in production
* `http://my-testing-domain.com/<subdomain>` in a testing env

The 'websites' extension add a 'websites' table holding the website subdomain, and making possible to resolve 
the website based on the requested url.



