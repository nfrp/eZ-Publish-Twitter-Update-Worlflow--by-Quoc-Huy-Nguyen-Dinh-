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

class TwitterStatusUpdateType extends eZWorkflowEventType
{
    const WORKFLOW_TYPE_STRING = "twitterstatusupdate";

    /**
     * Contains the name of the storage field for a tweet update format
     * @var string
     */
    const TWEET_FORMAT_STORAGE_FIELD = 'data_text1';

    /**
     * Default format for a twitter update. The <message> placeholder is dynamically replaced
     * by the content's excerpt + shrinked URL.
     * @var string
     */
    const DEFAULT_TWEET_FORMAT = "<message>";

    public function __construct()
    {
        parent::__construct( TwitterStatusUpdateType::WORKFLOW_TYPE_STRING, 'Update Twitter Status' );
    }

    function typeFunctionalAttributes( )
    {
        return array( 'tweet_format' );
    }

    function attributeDecoder( $event, $attr )
    {
        switch ( $attr )
        {
            case 'tweet_format':
            {
                $returnValue = $event->attribute( self::TWEET_FORMAT_STORAGE_FIELD );
            } break;

            default:
                $returnValue = null;
        }
        return $returnValue;
    }

    function initializeEvent( $event )
    {
        $event->setAttribute( self::TWEET_FORMAT_STORAGE_FIELD, self::DEFAULT_TWEET_FORMAT );
    }

    function fetchHTTPInput( $http, $base, $event )
    {
        $tweetFormat = $base . "_event_twitterstatusupdate_tweetformat_" . $event->attribute( "id" );
        if ( $http->hasPostVariable( $tweetFormat ) )
        {
            $tweetFormatValue = $http->postVariable( $tweetFormat );
            $event->setAttribute( self::TWEET_FORMAT_STORAGE_FIELD, $tweetFormatValue );
        }
    }

    public function execute( $process, $event )
    {
       $parameters = $process->attribute( 'parameter_list' );
       /*  YOUR CODE GOES HERE */

       $twitterINI = eZINI::instance( 'mytwitter.ini' );
       $twitterDebugOutput = $twitterINI->variable( 'TwitterSettings', 'DebugOutput' );

       eZLog::write( "Entering eztwitter workflow" );
       $twitterConsumerKey = $twitterINI->variable( 'TwitterSettings', 'ConsumerKey' );
       $twitterConsumerSecret = $twitterINI->variable( 'TwitterSettings', 'ConsumerSecret');
       $twitterAccessToken = $twitterINI->variable( 'TwitterSettings', 'AccessToken' );
       $twitterAccessSecret = $twitterINI->variable( 'TwitterSettings', 'AccessSecret' );

       if ( empty( $twitterConsumerKey ) ||
            empty( $twitterConsumerSecret ) ||
            empty( $twitterAccessToken ) ||
            empty( $twitterAccessSecret ) )
       {
           if ( $twitterDebugOutput=='enabled' )
               eZLog::write( "Please configure mytwitter.ini" );
       }
       if ( $twitterDebugOutput=='enabled' )
           eZLog::write( "Credentials found in mytwitter.ini" );

       $twitter = new Arc90_Service_Twitter();
       $twitter->useOAuth( $twitterConsumerKey,
                           $twitterConsumerSecret,
                           $twitterAccessToken,
                           $twitterAccessSecret );

       $objectID = $parameters['object_id'];
       $object = eZContentObject::fetch( $objectID );
       $nodeID = $object->attribute( 'main_node_id' );
       $node = eZContentObjectTreeNode::fetch( $nodeID );
       $datamap = $object->dataMap();

       // Now we need to check if the content class attribute “twitterstatus”
       // is present in the class and whether it is empty or not:
       // Quit if attribute twitterstatus does not exist in the contentclass
       if ( !isset( $datamap['twitterstatus'] ) )
       {
           if ( $twitterDebugOutput == 'enabled' )
               eZLog::write("twitter status not found");

           return eZWorkflowType::STATUS_ACCEPTED;
       }
       if ( $twitterDebugOutput == 'enabled' )
           eZLog::write( "Found twitter status attribute" );


       // Else take note of it
       $twitterStatus = $datamap['twitterstatus']->attribute('data_text');

       if ( empty( $twitterStatus ) )
       {
           if ( $twitterDebugOutput == 'enabled' )
               eZLog::write( "twitter status empty" );

           return eZWorkflowType::STATUS_ACCEPTED;
       }

       if ( $twitterDebugOutput == 'enabled' )
           eZLog::write( "twitter status attribute not empty" );

       $siteINI = eZINI::instance();
       $siteHost = $siteINI->variable( 'SiteSettings', 'SiteURL' );
       $siteHost = preg_replace( "|/$|", "", $siteHost );

       // Generate the full URI
       $nodeUrl = $node->attribute( 'url_alias' );
       eZURI::transformURI( $nodeUrl, false, 'full' );

       // Shrink it
       $tinyURL = eZHTTPTool::getDataByURL( 'http://tinyurl.com/api-create.php?url=' .  urlencode( $nodeUrl ), false, false );

       if ( empty( $tinyURL ) )
       {
           if ( $twitterDebugOutput == 'enabled' )
               eZLog::write("Error with TinyURL");

           return eZWorkflowType::STATUS_ACCEPTED;
       }

       try
       {
           // Craft the tweet
           $tweetFormat = $event->attribute( self::TWEET_FORMAT_STORAGE_FIELD );
           if ( $tweetFormat and $tweetFormat != "" )
           {
               // Was the <message> placeholder properly used in the format ?
               if ( ( $pos = strpos( $tweetFormat, self::DEFAULT_TWEET_FORMAT ) ) !== false )
               {
                   // If so, replace is by the dynamic content
                   $tweet = str_replace( self::DEFAULT_TWEET_FORMAT, $twitterStatus . ' : ' . $tinyURL , $tweetFormat );
               }
               else
               {
                   // If not, make sure the dyanmice content + URL are sent through anyways, and
                   // get back in touch with the person who configured the workflow event, she missed one thing :)
                   $tweet = "$twitterStatus : $tinyURL";
               }
           }
           else
           {
               // No format is used
               $tweet = "$twitterStatus : $tinyURL";
           }

           if ( $twitterDebugOutput == 'enabled' )
               eZLog::write( "About to send this Twitter status: {$tweet}" );

           $response = $twitter->updateStatus( $tweet, 'xml' );
           if ( $response->isError() )
           {
               if ( $twitterDebugOutput == 'enabled' )
                    eZLog::write( "Twitter error: " . $response->http_code );

               return eZWorkflowType::STATUS_ACCEPTED;
           }
           else
           {
               if ( $twitterDebugOutput == 'enabled' )
                   eZLog::write( "Twitter status updated: $tweet" );
           }
       }
       catch( Arc90_Service_Twitter_Exception $e )
       {
           if ( $twitterDebugOutput == 'enabled' )
               eZLog::write( "Error with Twitter API" );

           return eZWorkflowType::STATUS_ACCEPTED;
       }

       // After having sent the status update, we’ll clear it so it won’t be resent.
       $datamap['twitterstatus']->setAttribute( 'data_text', '' );
       $datamap['twitterstatus']->store();
       if ( $twitterDebugOutput == 'enabled' )
           eZLog::write( "Resetting twitter status attribute" );


       return eZWorkflowType::STATUS_ACCEPTED;
    }
}

eZWorkflowEventType::registerEventType( TwitterStatusUpdateType::WORKFLOW_TYPE_STRING, 'twitterstatusupdatetype' );
?>
