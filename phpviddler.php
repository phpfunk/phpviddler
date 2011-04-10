<?php
/* Viddler PHP Wrapper for Viddler's API 
  Version 2.2
  Released: December 2010.
  http://developers.viddler.com/projects/api-wrappers/phpviddler/
*/

class Viddler_V2 {

  public $api_key          = NULL;
  public $log_path         = '';
  public $response_time    = 0;
  public $response_type    = 'php';
  
  protected $attempt      = 1;
  protected $end_time     = 0;
  protected $max_attempts = 10;
  protected $query        = array();
  protected $start_time   = 0;
  protected $url          = NULL;
  
  /**
  Constuctor
  - If API Key is NOT empty, set to $obj->api_key
  **/
  public function __construct($api_key=NULL, $response_type='php') {
    if (! empty($api_key)) {
      $this->api_key = $api_key;
    }
    $this->response_type = $response_type;
  }

  /**
  Method: __call (It's magic!)
  - This method will be called for every API call and sent to correct location
  
  - Can be called like such:
    $__viddler = new Viddler_V2('YOUR KEY');
    $result = $__viddler->viddler_users_getProfile(array("user"=>"phpfunk"));
  **/
  public function __call($method, $args) { 
    $this->start_time = $this->timer();
    return self::call($method, $args, "object");
  }
  
  /**
  Method: call
   - This method is called on every API method call. It figures out if the method exists,
   if not it calls the Viddler API, otherwise it calls the method.
  **/
  protected function call($method, $args, $call)
  { 
    /**
    Format the Method
    Accepted Formats:
    $__viddler->viddler_users_auth();
    Turns into: viddler.users.auth
    **/
    $method = str_replace("_", ".", $method);
    
    //If the method exists here, call it
    if (method_exists($this, $method)) { return $this->$method($args[0]); }
    
    // Methods that require HTTPS
    $secure_methods = array(
      'viddler.users.auth'
    );
    
    // Methods that require POST
    $post_methods = array(
      'viddler.encoding.cancel',
      'viddler.encoding.encode',
      'viddler.encoding.setOptions',
      'viddler.groups.addVideo',
      'viddler.groups.join',
      'viddler.groups.leave',
      'viddler.groups.removeVideo',
      'viddler.playlists.addVideo',
      'viddler.playlists.create',
      'viddler.playlists.delete',
      'viddler.playlists.removeVideo',
      'viddler.playlists.moveVideo',
      'viddler.playslists.setDetails',
      'viddler.users.setSettings',
      'viddler.users.setProfile',
      'viddler.users.setOptions',
      'viddler.users.acceptFriendRequest',
      'viddler.users.ignoreFriendRequest',
      'viddler.users.sendFriendRequest',
      'viddler.users.subscribe',
      'viddler.users.unsubscribe',
      'viddler.videos.setDetails',
      'viddler.videos.setPermalink',
      'viddler.videos.comments.add',
      'viddler.videos.comments.remove',
      'viddler.videos.upload',
      'viddler.videos.delete',
      'viddler.videos.delFile',
      'viddler.videos.favorite',
      'viddler.videos.unfavorite',
      'viddler.videos.setPermalink',
      'viddler.videos.setThumbnail',
      'viddler.videos.setDetails',
      'viddler.videos.enableAds',
      'viddler.videos.disableAds'
    );
    
    // Methods that require Binary transfer
    $binary_methods = array(
      'viddler.videos.setThumbnail',
      'viddler.videos.upload'
    );
    
    $binary = (in_array($method, $binary_methods)) ? TRUE : FALSE;
    $post = (in_array($method, $post_methods)) ? TRUE : FALSE;
    
    // Figure protocol http:// or https://
    $protocol = (in_array($method, $secure_methods)) ? "https" : "http";
    
    // Build API endpoint URL
    // This is generally used to switch the end-point for uploads. See /examples/uploadExample.php in PHPViddler 2
    $this->url = (isset($args[1])) ? $args[1] : $protocol . '://api.viddler.com/api/v2/' . $method . '.' . $this->response_type;
    
    if ($post === TRUE) { // Is a post method
        array_push($this->query, "key=" . $this->api_key); // Adds API key to the POST arguments array
    } else {
      $this->url .= "?key=" . $this->api_key;
    }
    
    //Figure the query string
    if (@count($args[0]) > 0 && is_array($args[0])) {
      foreach ($args[0] as $k => $v) {
        if ($k != 'response_type' && $k != 'api_key') {
          array_push($this->query, $k . '=' . $v);
        }
        
        if ($k == 'response_type') {
          $this->url = str_replace('.' . $this->response_type, '.' . $v, $this->url);
          $this->response_type = $v;
        }
      }
      
      $this->query = (empty($binary)) ? implode("&", $this->query) : $args[0];
      if ($post === FALSE) {
        $this->url .= (! empty($this->query)) ? "&" . $this->query : "";
      }
    }
    else {
      $this->query = NULL;
      $args[0] = array();
    }
    
    //Attempt to get a valid response upto the max_attempts set
    for ($this->attempt; $this->attempt <= $this->max_attempts; $this->attempt++) {
      $this->response_time = $this->timer();
      $response = $this->request($args[0], $post, $binary);
      if (! empty($response)) {
        $this->attempt = 1;
        return $response;
      }
    }
    
  }
  
  protected function log($response, $info)
  {
    $this->end_time = number_format($this->timer() - $this->start_time, '5', '.', '');
    if (! empty($this->log_path)) {
      $text = 'Date: ' . date('m/d/Y - h:i:s A') . "\n";
      $text .= 'Attempt: ' . $this->attempt . ' of ' . $this->max_attempts . "\n";
      foreach ($info as $k => $v) {
        $text .= $k . ': ' . $v . "\n";
      }
      $text .= 'API Response Time: ' . $this->response_time . " seconds \n";
      $text .= 'Total Time: ' . $this->end_time . " seconds \n";
      $text .= 'Response: ' . $response . "\n";
      $text .= '-----------------------------------------------' . "\n";
      $fp = fopen($this->log_path, 'a');
      fwrite($fp, $text);
      fclose($fp);
    }
  }
  
  protected function request($args, $post)
  {
    // Custruct the cURL call
    $ch = curl_init();
    curl_setopt ($ch, CURLOPT_URL, $this->url);
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt ($ch, CURLOPT_HEADER, 0);
    curl_setopt ($ch, CURLOPT_TIMEOUT, 0);
    curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    
    // Figure POST vs. GET
    if ($post == TRUE) {
      curl_setopt($ch, CURLOPT_POST, TRUE);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->query);
    }
    else {
      curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
    }
    
    //Get the response
    $response = curl_exec($ch);
    $this->response_time = number_format($this->timer() - $this->response_time, '5', '.', '');
    $info     = curl_getinfo($ch);
    
    if (! $response || empty($response) || $info['http_code'] != '200') {
      $response = curl_error($ch);
      $this->log($response, $info);
      $response['response'] = $response;
      return ($this->attempt >= $this->max_attempts) ? array_merge($response, $info) : false;
    }
    
    curl_close($ch);
    $this->log($response, $info);
    return ($this->response_type == 'php') ? unserialize($response) : $response;
  }
  
  protected function timer()
	{
		list($msec, $sec) = explode(' ' , microtime());
		return ((float)$msec + (float)$sec);
	}
}

?>