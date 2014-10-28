Frequently asked questions
==========================


Q) Why does the config file return an object, rather than having it as JSON or XML? 

A) Having the config file return an object allows you to write your own config parser - possibly one with some clever stuff built in.

For example, you could write a config class that downloads the list of repos to package from your webserver or Github. That would allow dynamic updating of the list of repos that Bastion builds without having to touch the Bastion config file.

Or you could write a XML parser if you really wanted one.

Q) Why not just use Satis?

A) Satis does not download packages from Github which means that you would not be able to run 'composer update' or 'composer install' during any Github downtime. It also means that the data transfer is slow compared to downloading the packages locally.

Satis also doesn't allow easy control of which versions of packages will be available in your repo, which Bastion allows by having a simple list of what versions should be ignored.


Q) Why does Bastion need write access to be able to download private Github repositories?

A) Short version, Github's api sucks. It is not possible to ask for just read-only access to someone's repositories. The choice is either 'public access', which is implicitly read-only, or full access including write access.

Please feel free to contact Github's support people to complain about this.