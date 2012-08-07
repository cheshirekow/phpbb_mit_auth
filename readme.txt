---Explanation---

The way this works is that it sets up two "dummy" paths in the phpbb system.

    http://youserver.mit.edu/phpbb_root/login/ssl
    http://youserver.mit.edu/phpbb_root/login/shib
    
If A user navigates to one of these paths, apache will require an 
MIT Certificate or touchtone authentication (respectively). Then, if the
certificate or touchstone authentication is successful, it will dump certain
veriables into the apache environment. The script login/index.php will look 
for these variables. If it finds them, it will turn the user's email address
into a username:

   [user]@mit.edu   --> [user]
   [other email     --> [other email]     (i.e. unchanged)
   
If the username exists then it looks up the user-id and pre-authenticates the
user by creating a random number and inserting it into the table "ares_auth". 
It then redirects the user to the phpbb user control panel using the username
"MITAuthUser" and the random number as the password. 

If the username does not exist, it creates a new user with that username, and
then proceeds as if it found the user. The password created for the user is
a random string. It can be changed by an administrator to allow 
plain-old-password authentication. 

The custom phpbb authentication module "auth_mit.php" checks to see if the
username is "MITAuthUser". If it is, it looks up the `user_id` in the table
"ares_auth" by matching the random number submitted as the password. 

If the username is anythign else, it attempts to log the user in with normal
database authentication (i.e. normal usernames and passwords still work). To
avoid security holes and conflicts, you should disable automatic registration
to avoid people gaining access by registering with touchtone using email 
addresses of existing users. 

---Create a table for nonces---

Using whatever you use to administer your sql database, use the following SQL
to create a table for authentication. Feel free to change the name of the
table but you should modify the two php scripts to use the new table name. 

    CREATE TABLE IF NOT EXISTS `ares_auth` (
      `nonce` char(32) NOT NULL COMMENT 'number used once (is removed immediately after use)',
      `user_id` int(11) NOT NULL COMMENT 'authenticated user_id',
      PRIMARY KEY (`nonce`)
    ) ;



---Install Files--

Copy the directory "login" to the root of your phpbb installation. If you have
problems with the simlinks you may have to remake them:

    cd ${PHP_ROOT}/login/ssl
    rm index.php
    ln -s ../index.php ./
    cd ../shib
    ln -s ../index.php ./
    
    
---Create Links---

You need to create some links to these paths so that users can find it
easily. I put them right above the normal login box. You can do this by
editing your templates "login_body.html" file:

    <!-- IF LOGIN_ERROR --><div class="error">{LOGIN_ERROR}</div><!-- ENDIF -->
    <a href="/phpBB3/login/ssl">Login using MIT Certificates</a><br/>
    <a href="/phpBB3/login/shib">Login using Touchstone</a><br/>
    <dl>

You can edit these from the web interface by going to:

* Admin Control Panel (ACP)
** Styles (tab)
*** Templates (on the left)
**** edit (under "options")
***** select "login_body.html"
    
---Apache Configuration--- 

Add the following to your secure localhost (probably port 443), i.e. the
https version of your website:

    <Location /phpBB3/login/ssl>
        SSLVerifyClient require
        SSLRequireSSL
        SSLOptions +StdEnvVars
    </Location>
    
    <Location /phpBB3/login/shib>
        AuthType shibboleth
        ShibRequestSetting requireSession 1
        Require valid-user
    </Location>
    
Change "/phpBB3" to whatever the root directory is of your phpbb installation.
What this does is that, anytime a user navigates to 
"https://yourserver.com/path/to/phpbb/login/ssl" apache will require that the
user has a valid SSL certificate verifiable by whatever certificate authorities
the server has enabled (in our case, only the MIT CA). Likewise whenever a 
user nagivates to "https://yourserver.com/path/to/phpbb/login/ssl" apache
will require that the user authenticate with shibboleth. In our case it will
redirect to the touchstone gateway. A minimal virtual host configuration 
might look like this:

    <IfModule mod_ssl.c>
    <VirtualHost _default_:443>
        # This is the directory where we installed the MIT client CA 
        # (and other CAs for that matter)
        SSLCACertificatePath /etc/ssl/certs/
    
        ServerAdmin ares-admin@mit.edu
        ServerName ares.lids.mit.edu:443
    
        <Directory /var/www/>
            # allows us to symlink content into the document root
            # the lack of "Indexes" means we don't list files if index.* is missing
            Options FollowSymLinks MultiViews
        
            # things allowed in .htaccess files (phpbb does some limiting in it's
            # .htaccess file)
            AllowOverride Limit
        
            # everyone is allowed to view this
            Order allow,deny
            allow from all
        
            # Does not allow unencrypted connections on port 443    
            SSLRequireSSL
        </Directory>
    
    
        <Location /phpBB3/login/ssl>
            SSLVerifyClient require
            SSLRequireSSL
            SSLOptions +StdEnvVars
        </Location>
        
        <Location /phpBB3/login/shib>
            AuthType shibboleth
            ShibRequestSetting requireSession 1
            Require valid-user
        </Location>
    
        #   SSL Engine Switch:
        #   Enable/Disable SSL for this virtual host.
        SSLEngine on
    
        #   The location of our certificate and private key. The key is used to 
        #   generate a certificate reques, and the certificate is given to us by
        #   MIT 
        SSLCertificateFile    /etc/ssl/certs/ares.pem
        SSLCertificateKeyFile /etc/ssl/private/ares.key
    
        #   Passes SSL_ environment variables to scripts
        <FilesMatch "\.(cgi|shtml|phtml|php)$">
            SSLOptions +StdEnvVars
        </FilesMatch>
    
        <Directory /usr/lib/cgi-bin>
            SSLOptions +StdEnvVars
        </Directory>
    </VirtualHost>
    </IfModule>

If you also want to serve the BB on unencrypted connections, then add the
following to your port 80 virtual host configuration:

    <Location /phpBB3/login/shib>
        RewriteEngine On
        RewriteCond %{HTTPS} off
        RewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI}
    </Location>
    
    <Location /phpBB3/login/ssl>
        RewriteEngine On
        RewriteCond %{HTTPS} off
        RewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI}
    </Location>
    
What this does is that if any users navigates to 
"http://youserver.com/path/to/phpbb/login/ssl" it will redirect them to the
same page on an ecnrypted connection: 
"https://youserver.com/path/to/phpbb/login/ssl".


