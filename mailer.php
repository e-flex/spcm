<?php

    /*********
     Settings
    *********/
    // The email address to send these messages to
    $emailTo = "contact@example.com";
    // The timeout threshold, in seconds, for your visitors, set to 5 hours by default.
    $request_timeout_threshold = 60*60*5;
    // reCaptcha secret
    $reCaptcha_secret = "BIG_SECRET_NUMBER";
    $sqlite_filename = "/filter.sqlite"

    // DDoS protection; uncomment this to disable the script
    // header('Location: /confirmation_page.html');

    // Uncomment for debugging
    // print("<pre>");

    // SQL statements to update the database and to query it
    $sql_find_ip = "SELECT id, ip_address, created FROM spam_fltr WHERE ip_address LIKE ? LIMIT 1";
    $insert_blocked_ip_sql = "INSERT INTO spam_fltr (ip_address, created) VALUES (?, strftime('%s','now'))";
    $update_blocked_ip_sql = "UPDATE spam_fltr SET created=strftime('%s','now') WHERE ip_address LIKE ?";
    $insert_message_sql = "INSERT INTO messages (email, name, message, created) VALUES (?, ?, ?, strftime('%s','now'))";

    // This makes a POST request to the reCaptcha service to validate the request
    function captcha_post(array $post = NULL) {
        $captcha_url = 'https://www.google.com/recaptcha/api/siteverify'
        $defaults = array(
            CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_URL => $captcha_url,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_POSTFIELDS => http_build_query($post)
        );

        $ch = curl_init();
        curl_setopt_array($ch, ($defaults));
        if(!$result = curl_exec($ch)) {
            exit(curl_error($ch));
        }
        curl_close($ch);
        return json_decode($result, true);
    }

    // This function gets the requesters IP address
    function get_user_ip_address() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
          return $_SERVER['HTTP_CLIENT_IP'];
        }
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
          return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        else {
          return $_SERVER['REMOTE_ADDR'];
        }
    }

    if($_SERVER['REQUEST_METHOD'] == 'POST') {

        /*************************************************************
         The IP and captcha filtering is done here,
         the reCaptcha service should take care of most of the abuse.
        *************************************************************/

        // Uncomment for debugging, NOT for production.
        // print("<pre>");
        // var_dump($_POST);
        // exit("</pre>");

        $requester_ip_address = get_user_ip_address();

        $captcha_post_data = array(
                           'secret' => $reCaptcha_secret,
                           'response' => $_POST['g-recaptcha-response']
                           );
        $captcha_response_data = captcha_post($captcha_post_data);

        // Uncomment for debugging.
        // var_dump($captcha_response_data);

        // Send the requester to the confirmation page if the captcha fails.
        if (!$captcha_response_data['success']) {
            header('Location: /confirmation_page.html');
            // Uncomment for debugging.
            // var_dump($captcha_response_data);
            // exit("</pre>");
        }

        try {
            // Uncomment for debugging.
            // print($requester_ip_address);

            // Open the database file
            $conn = new PDO("sqlite:" . $_SERVER['DOCUMENT_ROOT'] . $sqlite_filename);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Prepare the SQL statements
            $query_ip = $conn->prepare($sql_find_ip);
            $insert_blocked_ip = $conn->prepare($insert_blocked_ip_sql);
            $insert_message = $conn->prepare($insert_message_sql);
            $update_ip = $conn->prepare($update_blocked_ip_sql);

            // Query the database to see if the IP address is present and when it was last used.
            $query_ip->execute(array($requester_ip_address));
            $query_ip->setFetchMode(PDO::FETCH_ASSOC);
            $stored_ip_address = $query_ip->fetch();

            if (!$stored_ip_address) {
                // There is no match in the database, so we insert the IP address
                // Uncomment for debugging
                // print("No IP address found, inserting it");
                $insert_blocked_ip->execute(array($requester_ip_address));
            } elseif ((time() - $stored_ip_address["created"]) < $request_timeout_threshold) {
                // The visitor is sent to the confirmation page if they are being denied.
                header("Location: /confirmation_page.html");
                // exit("Timestamp is less than the specified timeout value\n</pre>");
            } else {
                // The timeout has been passed, we update the timestamp and continue
                // print("The timeout has been passed\n");
                $update_ip->execute(array($requester_ip_address));
            }

        } catch (PDOException $pe) {
            die("<pre>Database error:" . $pe->getMessage() . "</pre>");
        }

        // Sanitise the input a bit
        $name    = stripslashes(trim($_POST['name']));
        $email   = stripslashes(trim($_POST['email']));
        $subject = stripslashes(trim($_POST['subject']));
        $message = stripslashes(trim($_POST['message']));

        // A simple check to see that the contact form is not being abused
        $pattern = '/[\r\n]|Content-Type:|Bcc:|Cc:/i';
        if (preg_match($pattern, $name) || preg_match($pattern, $email)) {
            header('Location: /error_page.html');
        }

        $email_is_valid = filter_var($email, FILTER_VALIDATE_EMAIL);

        if($name && $email && $email_is_valid && $message) {
            $subject = $subject;
            $message = wordwrap($message, 80, "\r\n");
            $body = "Namn: $name <br /> Email: $email <br /> Meddelande: $message";
            $headers .= sprintf( 'Return-Path: %s%s', $email, PHP_EOL );
            $headers .= sprintf( 'From: %s%s', $email_to, PHP_EOL );
            $headers .= sprintf( 'Reply-To: %s%s', $email, PHP_EOL );
            $headers .= sprintf( 'Message-ID: <%s@%s>%s', md5( uniqid( rand( ), true ) ), $_SERVER[ 'HTTP_HOST' ], PHP_EOL );
            $headers .= sprintf( 'X-Priority: %d%s', 3, PHP_EOL );
            $headers .= sprintf( 'X-Mailer: PHP/%s%s', phpversion( ), PHP_EOL );
            $headers .= sprintf( 'Disposition-Notification-To: %s%s', $email, PHP_EOL );
            $headers .= sprintf( 'MIME-Version: 1.0%s', PHP_EOL );
            $headers .= sprintf( 'Content-Transfer-Encoding: 8bit%s', PHP_EOL );
            $headers .= sprintf( 'Content-Type: text/html; charset="utf-8"%s', PHP_EOL );
            mail($email_to, "=?utf-8?B?".base64_encode($subject)."?=", $body, $headers);
            $emailSent = true;

            $insert_message->execute(array($email, $name, $message));

            // print("Data inserted\n</pre>");
            header('Location: /confirmation_page.html');
        } else {
            header('Location: /error_page.html');
        }
    }
?>
