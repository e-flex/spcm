#########################
Simple PHP Contact Mailer
#########################

The *Simple PHP Contact Mailer* works by getting a POST request from the contact form in the ``contact.html`` file and then try to figure out if the data is SPAM and then send it along to you via email.

The steps to verify if it is SPAM or not are the following:
    #. See if reCaptcha accepts the request.
    #. See if the IP address has made a request within the threshold.
    #. Sanitise the data a bit.
    #. Send the email to you.

Usage
-----
#. Make sure that you have a web server with support for PHP and its mail component.
#. Set up `reCaptcha v2 <https://www.google.com/recaptcha/admin>`_ and copy the corresponding keys into ``mailer.php`` and ``contact.html``
#. Change the settings at the top of the ``mailer.php`` file to suite your needs.
#. Upload the ``mailer.php``, ``contact.html``, ``error_page.html``, ``filter.sqlite`` and ``confirmation_page.html`` to the root of your server, all in the same folder.
#. Browse to the ``contact.html`` file on your server, like ``example.com/contact.html`` for example.

Database Set-up
-----
The database file is created with the following statement::

    CREATE TABLE `messages` (
        `id`    INTEGER,
        `email` TEXT,
        `name`  TEXT,
        `message`   TEXT,
        `created`   INTEGER,
        PRIMARY KEY(id)
    );

    CREATE TABLE "spam_fltr" (
        `id`    INTEGER,
        `ip_address`    TEXT(45) UNIQUE,
        `created`   INTEGER,
        PRIMARY KEY(id)
    );
