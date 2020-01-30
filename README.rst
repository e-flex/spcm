Simple PHP Contact Mailer
#########################

Usage
-----
#. Make sure that you have a web server with suppoer for PHP and its mail component.
#. Change the settings at the top of the ``mailer.php`` file to suite your needs.
#. Upload the ``mailer.php``, ``contact.html``, ``error_page.html`` and ``confirmation_page.html`` to the root of your server, all in the same folder.

Setup
-----
Create a sqlite file with the following tables::

    CREATE TABLE `messages` (
        `id`    INTEGER,
        `email` TEXT,
        `name`  TEXT,
        `message`   TEXT,
        `created`   INTEGER,
        PRIMARY KEY(id)
    )

    CREATE TABLE "spam_fltr" (
        `id`    INTEGER,
        `ip_address`    TEXT(45) UNIQUE,
        `created`   INTEGER,
        PRIMARY KEY(id)
    )
