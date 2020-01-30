Simple PHP Contact Mailer
#########################

Usage
-----

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
