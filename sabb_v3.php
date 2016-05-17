<?php

/**
 * 
 * SAAB V3 WRAPPER FOR CISCO SPARK API V1
 * @author Ryan Huff <ryan@sparkresearchlabs.com>
 * @version 3.0.1
 * 
 */

class sabbV3
{
    //DB VARS
    var $db = array(
        'host'=>'DBHOST',
        'name'=>'DBNAME',
        'user'=>'DBUSER',
        'pass'=>'DBPASS',
        'resource'=>null,
        );
    var $clientId = null; //POPULATED FROM DB SCHEMA
    var $clientSecret = null; //POPULATED FROM DB SCHEMA
    var $appName = 'sabb'; //POPULATED FROM DB SCHEMA
    //URLs
    var $oauthStep1 = 'https://api.ciscospark.com/v1/authorize';
    var $oauthStep2 = 'https://api.ciscospark.com/v1/access_token';
    var $redirectUrl = "http://sabb.ryanthomashuff.com";
    var $messagesUrl = "https://api.ciscospark.com/v1/messages";
    var $peopleUrl = 'https://api.ciscospark.com/v1/people';
    var $roomsUrl = 'https://api.ciscospark.com/v1/rooms';

    //SECURITY VARS
    var $oauthEnabled = true;
    var $useDatabase = true;
    var $SparkAccessToken = null; //ADD THE DEVELOPER TOKEN IF OAUTH IS DISABLED
    var $refreshToken = null;
    

    /**
     * Class Constructor
     * Used to establish a database connection, 
     * pre-populate the security and refresh token,
     * determine if a token refresh should occur and
     * to detect inbound JSON payloads
     */
    function __construct() 
    {
        if ($this->useDatabase)
        {
            //SETUP DB CONNECTION
            $this->db['resource'] = mysqli_connect($this->db['host'], $this->db['user'], $this->db['pass']);
            mysqli_select_db($this->db['resource'], $this->db['name']);
            //POPULATE CLIENT ID
            $result = mysqli_fetch_array(
                    mysqli_query(
                            $this->db['resource'],
                            "SELECT * FROM securityAssets WHERE clientName='" . $this->appName . "' LIMIT 1;"
                            )
                    );
            $this->clientId = $result['clientId'];
            $this->clientSecret = $result['clientSecret'];
        }

        if ($this->oauthEnabled)
        {
            if (!empty($result['accessToken']))
            {
                $this->SparkAccessToken = $result['accessToken'];
                
                if (!empty($result['refreshToken']))
                {
                    $this->refreshToken = $result['refreshToken'];
                }
            }
            else 
            {
                $this->newAuth();
            }
            
            if (!empty($result['lastRefresh']))
            {
                
                if ($distal = time('now') - $result['lastRefresh'] > 432000)
                {
                    $this->refreshToken();
                }
                
            }
        }
        if ( $callback = json_decode(
                file_get_contents(
                        'php://input'
                        ), 
                true
                )
            )
        {   
            //CALLBACK TRUE (PROCESS NOTIFICATION FROM WEBHOOK)
        }
        
        else 
        {
            echo "
            <html>
            <title>Thank You!</title>
            <body>
            <div style='width: 100%; text-align: center;'><img src='sabb.png'/></div>
            <div style='width: 100%; text-align: center;'><span style='font-family: Verdana, Helvetica, Tahoma;'>I didn't detect any notifications from Spark.</span></div>
            <br />
            </body>
            </html>
            ";
            
        }
    }
    
    /**
     * OAuth navigation for new token
     * Used to kick off the OAuth flow for the user in the case
     * where an access token or refresh token is not already
     * present in the database
     * @return NULL returned output is handled within method
     * @USAGE
     * $this->newAuth();
     */
    function newAuth()
    {   
        if (isset($_GET['code']) && !is_null($_GET['code']))
        {
            $context = stream_context_create(
                $this->postPayload(
                    array (
                        'grant_type' => 'authorization_code',
                        'client_id' => $this->clientId,
                        'client_secret' => $this->clientSecret,
                        'code' => $_GET['code'],
                        'redirect_uri' => $this->redirectUrl,
                        ),
                        'HTTP'
                    )
                );
            
            $result = json_decode(file_get_contents($this->oauthStep2, false, $context));
            if (@mysqli_query($this->db['resource'], "UPDATE securityAssets SET accessToken='$result->access_token', expiresIn='$result->expires_in', refreshToken='$result->refresh_token', refreshExpiresIn='$result->refresh_token_expires_in', lastRefresh='" . time('now') . "' WHERE clientName='$this->appName'"))
            {
                $this->SparkAccessToken = $result->access_token;
            }
            echo "
            <html>
            <title>Thank You!</title>
            <body>
            <div style='width: 100%; text-align: center;'><img src='sabb.png'/></div>
            <div style='width: 100%; text-align: center;'><span style='font-family: Verdana, Helvetica, Tahoma;'>Thanks for the permission!</span></div>
            </body>
            </html>
            ";
            return;
            
        }
        $scopes = array (
            'spark:rooms_read',
            'spark:rooms_write',
            'spark:messages_read',
            'spark:messages_write',
            'spark:people_read',
            'spark:memberships_read',
            'spark:memberships_write',
            );
        echo header("Location: $this->oauthStep1?response_type=code&client_id=" . urlencode($this->clientId) . "&redirect_uri=" . urlencode($this->redirectUrl) . "&scope=" . urlencode(implode(" ", $scopes)) . "&state=" . urlencode(rand(1, 63)));
    }
    
    /**
     * OAuth navigation for new token
     * Used for acquiring a new access token by using
     * the refresh token
     * @return NULL returned output is handled within method
     * @USAGE
     * $this->refreshAuth();
     */
    function refreshAuth()
    {
        $context = stream_context_create(
             $this->postPayload(
                array (
                     'grant_type' => 'refresh_token',
                     'client_id' => $this->clientId,
                     'client_secret' => $this->clientSecret,
                     'refresh_token' => $this->refreshToken,
                     ),
                     'HTTP'
                )
             ); 

        if ($result = json_decode(file_get_contents($this->oauthStep2, false, $context)))
        {
            if (@mysqli_query($this->db['resource'], "UPDATE securityAssets SET accessToken='$result->access_token', expiresIn='$result->expires_in' WHERE clientName='$this->appName'"))
            {
                $this->SparkAccessToken = $result->access_token;
            }         
        }

        return;
    }
    
    /**
     * 
     * @param ARRAY $data ( optional response headers )
     * @return ARRAY
     * @USAGE
     * $this->getPayload(array('key'=>'value','key'=>'value'));
     */
    function getPayload( $data = null )
    {
        if ( is_null( $data ) )
        {
            return array(
                'http' => array(
                    'header' => "Authorization: Bearer $this->SparkAccessToken\r\nContent-type: application/json\r\n",
                    'method' => 'GET',
                ),
            );
        }
        else 
        {
            return array(
                'http' => array(
                    'header' => "Authorization: Bearer $this->SparkAccessToken\r\nContent-type: application/json\r\n",
                    'method' => 'GET',
                    'content' => json_encode($data),
                ),
            );                
        }
    }
    
     /**
     * 
     * @param ARRAY $data ( optional response headers )
     * @return ARRAY
     * @USAGE
     * $this->postPayload(array('key'=>'value','key'=>'value'), 'HTTP'); ( json or HTTP as second param to determine header type )
     */
    function postPayload( $data = null, $header = 'json' )
    {
        if ( $header == 'json' )
        {
            $header = "Authorization: Bearer $this->SparkAccessToken\r\nContent-type: application/json\r\n";
            $content = json_encode($data);
        }
        else
        {
            $header = "Content-type: application/x-www-form-urlencoded\r\n";
            $content = http_build_query($data);
        }
        if ( is_null( $data ) )
        {
            return array(
                'http' => array(
                    'header' => $header,
                    'method' => 'POST',
                ),
            );
        }
        else 
        {
            return array(
                'http' => array(
                    'header' => $header,
                    'method' => 'POST',
                    'content' => $content,
                ),
            );                
        }
    }
    
    /**
     * 
     * @param STRING $personId ( person ID or me )
     * @param STRING $email ( email address of person to look up )
     * @param STRING $type ( Type of payload requested )
     * @return STRING ( display name of person )
     * @USAGE
     * $this->people('me'); ( introspective )
     * $this->people(null, 'email@address.com', 'list'); ( user lookup )
     */
    function people( $personId = null, $email = null, $type = null ) 
    {
        if ($type == 'list')
        {
            $result = $this->jDecoder(
                    $this->peopleUrl . '?email=' . urlencode($email), 
                    $this->getPayload()
            );

            return array(
                'id'=>$result->items[0]->id,
                'emails'=>$result->items[0]->emails[0],
                'displayName'=>$result->items[0]->displayName,
                'avatar'=>$result->items[0]->avatar,
                'created'=>$result->items[0]->created
            );
                    
        }
        else
        {
            $result = $this->jDecoder(
                    $this->peopleUrl . '/' . $personId, 
                    $this->getPayload()
                    );  
            return array(
                'id'=>$results->id,
                'emails'=>$result->emails[0],
                'displayName'=>$result->displayName,
                'avatar'=>$result->avatar,
                'created'=>$result->created
            );
        
        }
        
    }
    
    /**
     * 
     * @param STRING $roomId (ID of room to get messages from after being notified by the web hook)
     * @return ARRAY
     * @USAGE
     * $this->getRoomMessages (' ROOM ID ');
     */
    function getRoomMessages ( $roomId ) 
    {
        
        $data = array(
            'roomId' => $roomId,
            'max' => $this->sample
            );
        
        $result = $this->jDecoder(
                $this->messagesUrl . '?' . http_build_query(
                        $data
                        ), 
                $this->getPayload()
                );
        
        foreach ( $result as $item ) 
        {
            foreach ( $item as $subk => $subv ) 
            {
                $sampleSet[] = $subv->text;
            }
        }

        return $sampleSet;
    }
    
    /**
     * 
     * @param STRING $messageId (ID of the message to get the contents of
     * @return ARRAY
     * @USAGE
     * $this->getMessageDetail ( 'MESSAGE ID' );
     */
    function getMessageDetail ( $messageId )
    {
        
        $result = $this->jDecoder(
                $this->messagesUrl . '/' . $messageid, 
                $this->getPayload()
                );

        return array(
            $result->text,
            $result->roomId,
            $this->people(
                    $result->personId
                    ),
            $result->files,
            $result->personId
        );
     
    }
    
    /**
     * 
     * @param STRING $roomId (ID of the room that the Webhook will trigger for)
     * @return INT
     * @USAGE
     * $this->createWebhook( 'ROOM ID' );
     */
    function createWebhook ( $roomId )
    {
            $data = array(
                'name' => 'BotCallback' . substr( $roomId,-6 ),
                'targetUrl' => $this->redirectUrl,
                'resource' => 'messages',
                'event' => 'created',
                'filter' => 'roomId=' . $roomId
                );

            $result = $this->jDecoder(
                    $this->webhookurl,
                    $this->postPayload( $data )
                    );

            return $result->id;
    }
    
    /**
     * 
     * @param STRING $message ( Message ID )
     * @param STRING $roomId ( Room ID )
     * @param STRING $url ( URL )
     * @return NULL
     * @USAGE
     * $this->createMessage ( 'MESSAGE ID', 'ROOM ID', 'URL' ); ( URL NOT REQUIRED )
     */
    function createMessage ( $message, $roomId, $url = null )
    {
        if (is_null( $url ) ) 
        {
            $data = array(
                'roomId' => $roomId,
                'text' => $message,            
            );
        }
        else 
        {
            $data = array(
                'roomId' => $roomId,
                'text' => $message,
                'files' => array($url),
            );
        }

        $result = $this->jDecoder(
                $this->messagesurl,
                $this->postPayload($data)
        );
        
        return null;
    }
    
    /**
     * @return ARRAY ( List of current rooms )
     */
    function showCurrentRooms ()
    {
        $result = $this->jDecoder(
                $this->roomsUrl,
                $this->getPayload( 
                    array(
                    'showSipAddress' => true
                    ) 
                )
        );

        foreach ($result as $k=>$v) 
        {
            foreach ($v as $subk=>$subv) 
            {
                $currentRooms[] = $subv->id;
            }
        }
        
        return $currentRooms;
    }
    
    /**
     * 
     * @param STRING $url ( URL to retrieve JSON payload from )
     * @param ARRAY $payload ( array of header options )
     * @return OBJECT
     * @USEAGE
     * $this->jDecoder( 'http://...', array() ); ( First param is the url to get the json payload from and second is the response headers )
     */
    function jDecoder( $url, $payload )
    {
        return json_decode(
                file_get_contents(
                        $url, 
                        false, 
                        stream_context_create(
                                $payload
                        )
                )
        );
    }
    
}

$sabb = new sabbV3();

//USAGE EXAMPLES

/** PRINT THE ROOM IDs OF ALL ROOMS CURRENTLY JOINED TO **/
//print_r($sabb->showCurrentRooms());

/** CREATE A WEBHOOK IN SPARK FOR YOUR USER ACCOUNT THAT WILL NOTIFY THIS SCRIPT **/
//$roomId = 'One of the room IDs shown from showCurrentRooms()';
//$sabb->createWebhook ($roomId );
?>