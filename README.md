Trellis - A User Management and Engagement Web Application
==========================================================

Trellis is iPlant's user management system, designed to register new users and enable current users to gain access to iPlant's expanding Cyber-Infrastructure (CI).

I wrote it in PHP using the latest Frameworks and modern software craftmanship methods:

* MVC pattern.
* Silex HTTP Micro-framework for the controllers,
* Doctrine2 Object Relational Mapper (ORM) for models and data access,
* Twig

I wrote a library in the iPlant namespace to handle abstractable services, including importing and exporting user data to and from various formats. I also used library components from: 

* Zend Framework,
* Symfony2,
* Guzzle HTTP Client Framework,
* Monolog

Note
----
All sensitive data has been scrubbed and sample, dummy config files have been provided. To prevent any historical data contamination just in case anyone committed passwords to the VCS repository, the original Mercurial repository was exported to the filesystem and a new Git repository was created. 
