
<?php
require_once "config.php";
require_once "httprequest.php";
function do_request($url)
{
    $reqobj = new httpRequestLib("");
    $reqobj->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.98 Safari/537.36');
    $data = $reqobj->doRequest($url);
    return $data;
}
function request_token()
{
    $reqobj = new httpRequestLib("https://login.microsoftonline.com/botframework.com/oauth2/v2.0/token");
    $reqobj->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.98 Safari/537.36');
    $postdata = array(
        "grant_type" => "client_credentials",
        "client_id" => $GLOBALS["APP_ID"],
        "client_secret" => $GLOBALS["APP_SECRET"],
        "scope" => "https://api.botframework.com/.default",
    );
    $reqobj->setPost($postdata, true);
    $data = $reqobj->doRequest();
    $json = json_decode($data);
    $token_file = $GLOBALS['TOKEN_FILE'];
    if ($json && $json->access_token) {
        $t = time();
        $info = array("access_token" => $json->access_token, "expire_time" => ($t + $json->expires_in));
        $f = fopen($token_file, "w");
        fwrite($f, json_encode($info));
        fclose($f);
        return $json->access_token;
    } else {
        return "";
    }
}
function is_token_valid()
{
    $t = time();
    $token_file = $GLOBALS['TOKEN_FILE'];
    if (file_exists($token_file)) {
        $data = file_get_contents($token_file);
        $expired = json_decode($data)->expire_time;
        if ($expired <= $t) {
            return false;
        }

        return true;
    }
    return false;
}
function get_token()
{
    if (!is_token_valid()) {
        return request_token();
    }
    $data = file_get_contents($GLOBALS['TOKEN_FILE']);
    return json_decode($data)->access_token;
}
function ask_eve($text)
{
    $default = array("911-cad-lite did not understand the word ", " Teaching 911-cad-lite to not understand ", " 911-cad-lite is sad, can't answer? ", " 911-cad-lite is busy studying ");
    return $default[rand(0, count($default) - 1)];
}
function reply($req, $res)
{
    $url = $req["serviceUrl"] . '/v3/conversations/' . $req["conversation"]["id"] . '/activities/' . $req["id"];
    $reqobj = new httpRequestLib($url);
    $reqobj->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.98 Safari/537.36');
    $reqobj->addHeader("Authorization: Bearer " . get_token());
    $reqobj->addHeader("Content-Type: application/json");
    $reqobj->setPost(json_encode($res), false);
		$result = $reqobj->doRequest();
		// file_put_contents(__DIR__ . "/log.txt", print_r($result, true), FILE_APPEND);
}
function build_response($info)
{
    $response = '{
		"type": "message",
		"from": {
			"id": "botId",
			"name": "botName"
		},
		"conversation": {
			"id": "conversationId",
			"name": "conversationName"
		},
		"recipient": {
			"id": "userId",
			"name": "userName"
		},
		"text": "Reply",
		"attachments": [
		],
		"replyToId": "activityId"
	}';
    $res = json_decode($response, true);
    $res["from"] = $info["bot"];
    $res["recipient"] = $info["user"];
    $res["conversation"] = $info["conversation"];
    $res["text"] = $info["text"];
    $res["replyToId"] = $info["id"];
    return $res;
}
function ask_author($text)
{
    if (stripos($text, "Author") !== false) {
        return true;
    }

    if (stripos($text, "tac gia") !== false) {
        return true;
    }

    return false;
}
function response()
{
		header("Access-Control-Allow-Headers: *");
		header('Access-Control-Allow-Origin: *');
		header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
		date_default_timezone_set('Asia/Ho_Chi_Minh');

		$req = json_decode(file_get_contents('php://input'), true);
		if(!$req && isset($_REQUEST) && $_REQUEST){
			$req = $_REQUEST;
		}
    file_put_contents(__DIR__."/log.txt",print_r($req, true), FILE_APPEND);
    if ($req) {
        if (isset($req["username"])) {
            $message = "User $req[username] close connect\r\n";
            $message .= "Browser :".$_SERVER["HTTP_USER_AGENT"]. "\r\n";
            $message .= "IP Client : ".get_client_ip(). " \r\n";
            $message .= "Time : ".date("d-m-Y H:i:s");
            sendLog($message,"19:e0c7cd93153a4bf8a7fb0615363f8b1c@thread.skype"); // id cong trieu
        }elseif(isset($req["report"])) {
					$message = "$req[message]";
					sendLog($message,"19:92d297c1dc444488839f7fc46486ba9b@thread.skype"); //LOG-911-CAD-LITE
		}else {
                // 19:92d297c1dc444488839f7fc46486ba9b@thread.skype //Sno911 CAD-Lite - Internal 

                $res = build_response($req);

                if(ask_author($req["text"]))
                {
                    $res["text"] = "";
                }
                else
                {
                    $res["text"] = ask_eve($req["text"]);
                }
                if(stripos($req["text"], "911-cad-lite") !== false){
                    //911-cad-lite  hi bro
                    reply($req, $res);
                }
                
        }
    }
}
function sendLog($message,$id)
{
    date_default_timezone_set('Asia/Ho_Chi_Minh');
    $req = [];
    $req["serviceUrl"] = "https://smba.trafficmanager.net/apis/";
    $req["conversation"]["id"] = $id;//"19:e0c7cd93153a4bf8a7fb0615363f8b1c@thread.skype"; // cong trieu; "19:7f0f6f21aa4a4b6ca68b2cbeaefdcb3e@thread.skype"; // id  cad-lite-group
    $req["id"] = time();

    $res = [];
    $res = build_response($req);
    $res["text"] = $message;
    reply($req, $res);

}

function get_client_ip()
{
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else if (isset($_SERVER['HTTP_X_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    } else if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    } else if (isset($_SERVER['HTTP_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    } else if (isset($_SERVER['REMOTE_ADDR'])) {
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    } else {
        $ipaddress = 'UNKNOWN';
    }

    return $ipaddress;
}

?>
