<?php


define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : '../../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
require($phpbb_root_path . 'common.' . $phpEx);
require($phpbb_root_path . 'includes/functions_user.' . $phpEx);
require($phpbb_root_path . 'includes/functions_module.' . $phpEx);

$user->session_begin();
$auth->acl($user->data);
$user->setup('ucp');



do_auth_mit();



function create_user_auth_mit($username,$email)
{
    global $db, $config;
    
//     $handle = fopen("00_create_user_auth_mit.log", "a");
//     fwrite($handle, date(DATE_RSS) .":\n   Creating new user"
//                                     ."\n      username: $username"
//                                     ."\n      email:    $email" );
//     fflush($handle);
    
    // I'm not sure what coppa is, perhaps Childrens Online Privacy
    // Protection... i.e. user is under 13 (jjb)
    $coppa = false;
    
    // the user’s password, which is hashed before
    // inserting into the database, and is also a random string
    $password = md5(rand(0, 100) . time());
     
    // default is 4 for registered users, or 5 for coppa users.
    $group_id = ($coppa) ? 5 : 4;
    
    // since group IDs may change, you may want to use a query to
    // make sure you are grabbing the right default group...
    $group_name = ($coppa) ? 'REGISTERED_COPPA' : 'REGISTERED';
    $sql = 'SELECT group_id
                FROM ' . GROUPS_TABLE . "
                WHERE group_name = '" . $db->sql_escape($group_name) . "'
                AND group_type = " . GROUP_SPECIAL;
    
    $result   = $db->sql_query($sql);
    $row      = $db->sql_fetchrow($result);
    $group_id = $row['group_id'];
     
    // timezone of the user... Based on GMT in the format of '-6', '-4', 3, 9 etc...
    $timezone = '-5';
     
    // two digit default language for this use of a language pack that is installed on the board.
    $language = 'en';
     
    // user type, this is USER_INACTIVE, or USER_NORMAL depending on if the user needs to activate himself, or does not.
    // on registration, if the user must click the activation link in their email to activate their account, their account
    // is set to USER_INACTIVE until they are activated. If they are activated instantly, they would be USER_NORMAL
    $user_type = USER_NORMAL;
     
    // here if the user is inactive and needs to activate thier account through an activation link sent in an email
    // we need to set the activation key for the user... (the goal is to get it about 10 chars of randomization)
    // you can use any randomization method you want, for this example, I’ll use the following...
    $user_actkey = md5(rand(0, 100) . time());
    $user_actkey = substr($user_actkey, 0, rand(8, 12));
     
    // IP address of the user stored in the Database.
    $user_ip = $_SERVER['REMOTE_ADDR'];
     
    // registration time of the user, timestamp format.
    $registration_time = time();
     
    // inactive reason is the string given in the inactive users list in the ACP.
    // there are four options: INACTIVE_REGISTER, INACTIVE_PROFILE, INACTIVE_MANUAL and INACTIVE_REMIND
    // you do not need this if the user is not going to be inactive
    // more can be read on this in the inactive users section
    $user_inactive_reason = INACTIVE_REGISTER;
     
    // time since the user is inactive. timestamp.
    $user_inactive_time = time();
    
    
    
    $user_row = array(
            'username'              => $username,
            'user_password'         => phpbb_hash($password),
            'user_email'            => $email,
            'group_id'              => (int) $group_id,
            'user_timezone'         => (float) $timezone,
            'user_dst'              => $is_dst,
            'user_lang'             => $language,
            'user_type'             => $user_type,
            'user_actkey'           => $user_actkey,
            'user_ip'               => $user_ip,
            'user_regdate'          => $registration_time,
            'user_inactive_reason'  => $user_inactive_reason,
            'user_inactive_time'    => $user_inactive_time,
    );
    
    // all the information has been compiled, add the user
    // tables affected: users table, profile_fields_data table, groups table, and config table.
    $user_id = user_add($user_row);
    return $user_id;
}



function do_auth_mit()
{
    global $db, $config;
    
    // now, authenticate the user by making sure the SSL information is 
    // dropped in the environment by apache
//     $handle = fopen("00_do_auth_mit.log", "a");
//     fwrite($handle, date(DATE_RSS) . ": ssl authing\n");
//     fflush($handle);
    
    $user_row = array();
    $email    = "";
    
    if (@$_SERVER['mail'])
    {
//         fwrite($handle, "   Touchstone info was found\n");
//         fwrite($handle, "      " . $_SERVER['mail'] . "\n");
//         fwrite($handle, "      " . $_SERVER['displayName'] . "\n");
//         fflush($handle);
        
        $email = $_SERVER['mail'];
    }
    else if(@$_SERVER['SSL_CLIENT_S_DN_Email'])
    {
//         fwrite($handle, "   Certificate was found\n");
//         fwrite($handle, "      " . $_SERVER['SSL_CLIENT_S_DN_CN'] . "\n");
//         fwrite($handle, "      " . $_SERVER['SSL_CLIENT_S_DN_Email'] . "\n");
//         fwrite($handle, "      " . $_SERVER['SSL_CLIENT_I_DN_O'] . "\n");
//         fwrite($handle, "      " . $_SERVER['SSL_CLIENT_V_END'] . "\n");
//         fflush($handle);
        
        $email = $_SERVER['SSL_CLIENT_S_DN_Email'];
    }
    else
    {
        ?>
        <html>
            <head>
                <title>ARES phpBB SSL Authentication</title>
            </head>
            <body>
                <h1>Authentication Error:</h1>
                <p>
                    You must have an MIT Certificate or a Touchstone account
                    to use this authentication 
                    mechanism. If you do one of these, perhaps your certificate
                    is expired or you have incorrectly entered your password. 
                    Alternatively, perhaps you're using a browser which 
                    doesn't implement SSL Certificates correctly. 
                    In either case, you may want to go back and try Touchstone 
                    authentication (again).
                </p>
            </body>
        </html>
        <?
        die();
    }
    
    if( preg_match('/([^@]+)@?([^@]*)/', $email, $matches) !== 1 )
    {
//         fwrite($handle, "      email is not an email\n");
//         fflush($handle);
        ?>
        <html>
            <head>
                <title>ARES phpBB SSL Authentication</title>
            </head>
            <body>
                <h1>Authentication Error:</h1>
                <p>
                    I am very sorry, it seems you have a touchstone account
                    or an MIT certificate but I still can't log you in. This
                    problem has been logged and the admin will attempt to
                    fix it as soon as possible. 
                </p>
            </body>
        </html>
        <?
        die();
    }

//     fwrite($handle, "   matched user: " . $matches[0] . "\n");
//     fflush($handle);
    
    if( preg_match( '/mit.edu/i', $matches[2] ) )
    {
//         fwrite($handle, "   user is an @mit.edu" );
        $username    = $matches[1];
    }
    else
    {
//         fwrite($handle, "   user is not an @mit.edu" );
        $username    = $matches[0];
    }

    
    $sql     = sprintf('SELECT * FROM %s WHERE username = "%s"',
                            USERS_TABLE,
                            $db->sql_escape($username) );
    
    $result  = $db->sql_query($sql);
    $row     = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);

    if ($row)
        $user_id = $row['user_id'];
    else
        $user_id = create_user_auth_mit($username,$matches[0]);
    $nonce = md5(rand(0, 100) . time());
    
    $sql     = sprintf(
            "INSERT INTO ares_auth (nonce, user_id) VALUES ('%s',%d)",
            $nonce, $user_id );
    $result  = $db->sql_query($sql);

//     fclose($handle);
    ?>
<html>
    <head>
        <title>ARES phpBB SSL Authentication</title>
    </head>
    <body onload="document.frm.submit()">
        <p>
            Authentication was successful, redirecting back to phpBB
        </p>
        <form action="/../../ucp.php?mode=login" method="post" name="frm">
            <input type="hidden" name="mode"     value="login"/>
            <input type="hidden" name="username" value="MITAuthUser"/>
            <input type="hidden" name="password" value="<?=$nonce?>" />
            <input type="hidden" name="sid"   value="<?= session_id() ?>" />
            <input type="hidden" name="login" value="Login" />
            <input type="hidden" name="redirect" value="index.php" />
            <input type="submit" name="login" value="Login" />
        </form>
    </body>
</html>
    <? 
}






?>
