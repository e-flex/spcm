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

    // DDoS protection; uncomment this to disable the script
    // header('Location: /confirmation_page.html');

    // Uncomment for debugging
    // print("<pre>");

    // SQL statements to update the database and to query it
    $sqlFindIp = "SELECT id, ip_address, created FROM spam_fltr WHERE ip_address LIKE ? LIMIT 1";
    $sqlInsertBlock = "INSERT INTO spam_fltr (ip_address, created) VALUES (?, strftime('%s','now'))";
    $sqlUpdateBlock = "UPDATE spam_fltr SET created=strftime('%s','now') WHERE ip_address LIKE ?";
    $sqlInsertMessage = "INSERT INTO messages (email, name, message, created) VALUES (?, ?, ?, strftime('%s','now'))";

    // This function is used to make a request to the reCaptcha service to validate the user
    function curl_post_json($url, array $post = NULL, array $options = array()) {
        $defaults = array(
            CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_URL => $url,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_POSTFIELDS => http_build_query($post)
        );

        $ch = curl_init();
        curl_setopt_array($ch, ($options + $defaults));
        if(!$result = curl_exec($ch)) {
            exit(curl_error($ch));
        }
        curl_close($ch);
        return json_decode($result, true);
    }

    // This function gets the requesters IP address
    function getRealIpAddr() {
        if (!empty($_SERVER['HTTP_CLIENT_IP']))
        {
          $ip=$_SERVER['HTTP_CLIENT_IP'];
        }
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        {
          $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        else
        {
          $ip=$_SERVER['REMOTE_ADDR'];
        }
        return $ip;
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

        $theIpAddress = getRealIpAddr();

        $captcha_post_data = array(
                           'secret' => $reCaptcha_secret,
                           'response' => $_POST['g-recaptcha-response']
                           );
        $captcha_response_data = curl_post_json('https://www.google.com/recaptcha/api/siteverify', $captcha_post_data);

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
            // print($theIpAddress);

            // Open the database file
            $conn = new PDO("sqlite:" . $_SERVER['DOCUMENT_ROOT'] . "/filter.sqlite");
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Prepare the SQL statements
            $queryIp = $conn->prepare($sqlFindIp);
            $insertIp = $conn->prepare($sqlInsertBlock);
            $insertMessage = $conn->prepare($sqlInsertMessage);
            $updateIp = $conn->prepare($sqlUpdateBlock);

            // Query the database
            $queryIp->execute(array($theIpAddress));
            $queryIp->setFetchMode(PDO::FETCH_ASSOC);
            $ipAddress = $queryIp->fetch();

            if (!$ipAddress) {
                // Uncomment for debugging
                // print("No IP address found, inserting it");
                $insertIp->execute(array($theIpAddress));
            } if ((time() - $ipAddress["created"]) < $request_timeout_threshold) {
                // The visitor is sent to the confirmation page if they are being denied.
                header("Location: /confirmation_page.html");
                // exit("Timestamp is less than the specified timeout value\n</pre>");
            } else {
                // The timeout has been passed, we update the timestamp and continue
                // print("The timeout has been passed\n");
                $updateIp->execute(array($theIpAddress));
            }

        } catch (PDOException $pe) {
            die("<pre>Database error:" . $pe->getMessage() . "</pre>");
        }

        // Sanitise the input a bit
        $name    = stripslashes(trim($_POST['name']));
        $email   = stripslashes(trim($_POST['email']));
        $subject = stripslashes(trim($_POST['subject']));
        $message = stripslashes(trim($_POST['message']));
        $pattern = '/[\r\n]|Content-Type:|Bcc:|Cc:/i';
        if (preg_match($pattern, $name) || preg_match($pattern, $email)) {
            header('Location: /error_page.html');
        }

        $emailIsValid = filter_var($email, FILTER_VALIDATE_EMAIL);

        if($name && $email && $emailIsValid && $message){
            $subject = $subjectPrefix . $subject;
            $message = wordwrap($message, 80, "\r\n");
            $body = "Namn: $name <br /> Email: $email <br /> Meddelande: $message";
            $headers .= sprintf( 'Return-Path: %s%s', $email, PHP_EOL );
            $headers .= sprintf( 'From: %s%s', $emailTo, PHP_EOL );
            $headers .= sprintf( 'Reply-To: %s%s', $email, PHP_EOL );
            $headers .= sprintf( 'Message-ID: <%s@%s>%s', md5( uniqid( rand( ), true ) ), $_SERVER[ 'HTTP_HOST' ], PHP_EOL );
            $headers .= sprintf( 'X-Priority: %d%s', 3, PHP_EOL );
            $headers .= sprintf( 'X-Mailer: PHP/%s%s', phpversion( ), PHP_EOL );
            $headers .= sprintf( 'Disposition-Notification-To: %s%s', $email, PHP_EOL );
            $headers .= sprintf( 'MIME-Version: 1.0%s', PHP_EOL );
            $headers .= sprintf( 'Content-Transfer-Encoding: 8bit%s', PHP_EOL );
            $headers .= sprintf( 'Content-Type: text/html; charset="utf-8"%s', PHP_EOL );
            mail($emailTo, "=?utf-8?B?".base64_encode($subject)."?=", $body, $headers);
            $emailSent = true;

            $insertMessage->execute(array($email, $name, $message));

            // print("Data inserted\n</pre>");
            header('Location: /confirmation_page.html');
        } else {
            header('Location: /error_page.html');
        }
    }
?>
