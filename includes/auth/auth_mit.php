<?php
/**
 *  auth_mit: provides SSL / Touchstone authentication for phpBB
 *
 *  Copyright (C) 2012 Josh Bialkowski (jbialk@mit.edu)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require('auth_db.php');

// is called when a user tries to manually login, it should return error
function login_mit(&$username, &$password, $ip = '', $browser = '', $forwarded_for = '')
{
    global $db, $config;
    
//     $handle = fopen("00_auth_mit.log", "a");
//     fwrite($handle, date(DATE_RSS) .":\n   Authenticating user"
//             ."\n      username: $username"
//             ."\n      password: $password"
//             ."\n" );
//     fflush($handle);
    
    if($username == "MITAuthUser")
    {
        $sql     = sprintf( "SELECT user_id FROM ares_auth WHERE nonce='%s'", 
                            $password );
        $result  = $db->sql_query($sql);
        $row     = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        if($row)
        {
            $user_id = $row['user_id'];
            $sql = sprintf( "DELETE FROM ares_auth WHERE nonce='%s'", 
                                $password );
            $result  = $db->sql_query($sql);
            
            $sql = sprintf( "SELECT * FROM %s WHERE user_id=%d",
                                USERS_TABLE, $user_id );
            $result  = $db->sql_query($sql);
            $row     = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);
            if($row)
            {
//                 fwrite($handle, date(DATE_RSS) .":\n   Authentication success"
//                         ."\n      username: " . $row['username']
//                         ."\n" );
//                         fflush($handle);
                return array(
                        'status'		=> LOGIN_SUCCESS,
                        'error_msg'		=> false,
                        'user_row'		=> $row,
                );
            }
            else
            {
//                 fwrite($handle, "\n   Authentication failure"
//                         ."\n      no row with user_id: $user_id"
//                         ."\n" );
//                 fflush($handle);
                return array(
                        'status'		=> LOGIN_ERROR_EXTERNAL_AUTH,
                        'error_msg'		=> 'user_id does not exist',
                        'user_row'      => array('user_id' => ANONYMOUS),
                );
            }
        }
        else
        {
//             fwrite($handle, "\n   Authentication failure"
//                     ."\n      no such nonce"
//                     ."\n" );
//                     fflush($handle);
            return array(
                'status'		=> LOGIN_ERROR_EXTERNAL_AUTH,
                'error_msg'		=> 'invalid nonce',
                'user_row'      => array('user_id' => ANONYMOUS),
            );
        }
    }
    else
    {
        return login_db(&$username, &$password);
    }
     

}


?>
