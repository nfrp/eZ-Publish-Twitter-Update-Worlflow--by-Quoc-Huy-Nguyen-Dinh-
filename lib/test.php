<?php
error_reporting( E_ALL | E_NOTICE );

// Go back to eZ root folder
require 'autoload.php';

$cli = eZCLI::instance();

$script = eZScript::instance( array( 'description' => ( "Twitter credentials registration / validation\n" .
                                                        "Script to register and validate OAuth credentials for Twitter\n" .
                                                        "\n" .
                                                        "extension/mytwitter/lib/setup.php" ),
                                     'use-session'    => false,
                                     'use-modules'    => false,
                                     'use-extensions' => false ) );

$script->startup();

$options = $script->getOptions( "[register][validate:]",
                                "",
                                array( 'register'                 => 'generate a registration URL',
                                       'validate'                 => 'validate the PIN returned by the registration URL' ) );

$script->initialize();

$test = eZINI::instance( 'huy.ini' );
$cli->output("[HuySettings] / Test => ". $test->variable( 'HuySettings', 'Test' ) );
var_export($test);

$script->shutdown();
?>
