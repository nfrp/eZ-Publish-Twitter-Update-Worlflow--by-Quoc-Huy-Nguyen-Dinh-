<?php
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: My Twitter
// COPYRIGHT NOTICE: Copyright (C) 2010-2011 NGUYEN DINH Quoc-Huy
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
//
//
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
//
error_reporting( E_ALL | E_NOTICE );

// Go back to eZ root folder
require 'autoload.php';
//require_once( 'extension/mytwitter/lib/oauth/twitterOAuth.php' );

$cli = eZCLI::instance();

$script = eZScript::instance( array( 'description' => ( "Twitter credentials registration / validation\n" .
                                                        "Script to register and validate OAuth credentials for Twitter\n" .
                                                        "\n" .
                                                        "extension/mytwitter/lib/setup.php" ),
                                     'use-session'    => false,
                                     'use-modules'    => false,
                                     'use-extensions' => true ) );

$script->startup();

// CLI parameters
$options = $script->getOptions( "[register][validate:]",
                                "",
                                array( 'register'                 => 'generate a registration URL',
                                       'validate'                 => 'validate the PIN returned by the registration URL' ) );

$script->initialize();

$myTwitterINI = eZINI::instance( 'mytwitter.ini' );
$consumerKey = $myTwitterINI->variable( 'TwitterSettings', 'ConsumerKey' );
$consumerSecret = $myTwitterINI->variable( 'TwitterSettings', 'ConsumerSecret' );
$accessToken = $myTwitterINI->variable( 'TwitterSettings', 'AccessToken' );

$varDir = eZSys::varDirectory();
$myTwitterTmpDir = $varDir . '/mytwitter';
if ( !file_exists( $myTwitterTmpDir ) )
{
     eZDir::mkdir( $myTwitterTmpDir, eZDir::directoryPermission(), true);
}

$noAction = true;
$register = isset( $options['register'] );
$noAction = !$register;

$pin = false;
if ( $options['validate'] )
{
    $noAction = false;
    $pin = $options['validate'];
}


if ( $register )
{
    // instantiate a TwitterOAuth object and request a token
    $oauth = new TwitterOAuth( $consumerKey, $consumerSecret );
    $request = $oauth->getRequestToken();

    $request_token = $request["oauth_token"];
    $request_token_secret = $request["oauth_token_secret"];

    // Saving the token in files for the validation process to use
    // Make sure to delete this after validation.
    file_put_contents( "{$myTwitterTmpDir}/request_token", $request_token );
    file_put_contents( "{$myTwitterTmpDir}/request_token_secret", $request_token_secret );

    // Generate an authorisation URL
    $request_link = $oauth->getAuthorizeURL( $request );
    $cli->warning( "Request here: " . $request_link );
}
elseif ( $pin )
{

    // read the request token from the registration process
    $request_token = file_get_contents( $myTwitterTmpDir ."/request_token" );
    $request_token_secret = file_get_contents( $myTwitterTmpDir ."/request_token_secret" );

    // Instantiate a TwitterOath object and provide it with the loaded token
    $oauth = new TwitterOAuth( $consumerKey, $consumerSecret,
			       $request_token, $request_token_secret );

    // request an access token from Twitter
    $request = $oauth->getAccessToken( FALSE, $pin );

    $cli->output( "Twitter user: {$request['screen_name']}" );

    $access_token = $request['oauth_token'];
    $access_token_secret = $request['oauth_token_secret'];

    // Display INI file settings
    $cli->output( "mytwitter.ini.append.php variables:" );
    $cli->warning( "AccessToken={$access_token}" );
    $cli->warning( "AccessSecret={$access_token_secret}\n" );

    //require_once( "extension/mytwitter/lib/Arc90/Service/Twitter.php" );

    // Lets see if everything is working
    $twitter = new Arc90_Service_Twitter();
    // Authenticate
    $twitter->useOAuth( $consumerKey, $consumerSecret, $access_token, $access_token_secret );

    // Retreive our account's timeline
    $cli->output( 'Trying to retreive our Twitter timeline' );
    $response = $twitter->getFriendsTimeline( 'json', array( 'count' => 200, 'page' => 0 ) );
    $cli->output( 'HTTP code: '.$response->getHttpCode() );

    if (!$response->isError())
    {
        $messages = $response->getJsonData();
        $cli->output( "Found ".count($messages). "new tweets" );
    }
    else
    {
        $cli->output( 'Error description: '.$response->getData() );
    }

    // Deleting the token request files
    unlink( $myTwitterTmpDir ."/request_token" );
    unlink( $myTwitterTmpDir ."/request_token_secret" );

}

if ( $noAction )
{
    $cli->warning( "Please use one of the following options --register --validate. Use --help option for more details." );
}

$script->shutdown();
?>
