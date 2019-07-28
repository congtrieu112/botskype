
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
    $default = array("Bot AI did not understand the word ", " Teaching Bot AI to not understand ", " Bot AI is sad, can't answer? ", " Bot AI is busy studying ");
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
        file_put_contents(__DIR__ . "/log.txt", print_r($result, true), FILE_APPEND);
        file_put_contents(__DIR__ . "/log.txt", print_r(json_encode($res), true), FILE_APPEND);
}
function build_response($info,$adaptiveCard=null,$messageCard=null,$video=null)
{
    $videoSend = $adaptiveCardData = "";
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
    if($adaptiveCard) {
        $adaptiveCardData = '{
            "contentType": "application/vnd.microsoft.card.audio",
            "content": {
              "title": "Allegro in C Major",
              "subtitle": "Allegro Duet",
              "text": "",
              "duration": "PT2M55S",
              "image":{
                  "url":"https://photo-resize-zmp3.zadn.vn/w600_r300x169_jpeg/thumb_video/8/0/e/6/80e6bd68d6bbafc06e7cc744a6ece067.jpg"
              },
              "media": [
                {
                  "url": "https://vnso-zn-15-tf-mp3-s1-zmp3.zadn.vn/97366d7f4738ae66f729/465611020948694906?authen=exp=1564278347~acl=/97366d7f4738ae66f729/*~hmac=de1d504047a22d1ea0e0e93fe8260c94"
                }
              ],
              "shareable": true,
              "autoloop": true,
              "autostart": true,
              "value": {}
            }
          }';
    }elseif($messageCard) {
        $messageCard = '{
            "contentType": "application/vnd.microsoft.teams.card.o365connector",
            "content": {
              "@type": "MessageCard",
              "@context": "http://schema.org/extensions",
              "summary": "Summary",
              "title": "Connector Card HTML formatting",
              "sections": [
                  {
                      "text": "This is some <strong>bold</strong> text"
                  },
                  {
                      "text": "This is some <em>italic</em> text"
                  },
                  {
                      "text": "This is some <strike>strikethrough</strike> text"
                  },
                  {
                      "text": "<h1>Header 1</h1>\r<h2>Header 2</h2>\r <h3>Header 3</h3>"
                  },
                  {
                      "text": "bullet list <ul><li>text</li><li>text</li></ul>"
                  },
                  {
                      "text": "ordered list <ol><li>text</li><li>text</li></ol>"
                  },
                  {
                      "text": "hyperlink <a href=\"https://www.bing.com/\">Bing</a>"
                  },
                  {
                      "text": "embedded image <img src=\"http://aka.ms/Fo983c\" alt=\"Duck on a rock\"></img>"
                  },
                  {
                      "text": "preformatted text <pre>text</pre>"
                  },
                  {
                      "text": "Paragraphs <p>Line a</p><p>Line b</p>"
                  },
                  {
                      "text": "<blockquote>Blockquote text</blockquote>"
                  }
               ]
            }
          }'; 
    }
    else if($video){
        $videoSend = '{
            "contentType": "video/mp4",
            "contentUrl": "https://mcloud-bf-s7-mv-zmp3.zadn.vn/QCAmCH_aEv4/4888379d52dbbb85e2ca/53a0281f655a8c04d54b/480/Song-Gio.mp4?authen=exp=1564462957~acl=/QCAmCH_aEv4/*~hmac=91ae3454b9a149b12d56cc6ea409260f",
            "name": "duck-on-a-rock.jpg",
            "thumbnailUrl":"https://photo-resize-zmp3.zadn.vn/w600_r300x169_jpeg/thumb_video/8/0/e/6/80e6bd68d6bbafc06e7cc744a6ece067.jpg"
        }';
        $videoSend = '{
            "contentType": "video/mp3",
            "contentUrl": "https://mp3-s1-zmp3.zadn.vn/b800471a6d5d8403dd4c/1145597756150434004?authen=exp=1564412241~acl=/b800471a6d5d8403dd4c/*~hmac=f83d35eccf70bb4b37c055056994ca05",
            "name": "duck-on-a-rock.jpg",
            "thumbnailUrl":"https://photo-resize-zmp3.zadn.vn/w600_r300x169_jpeg/thumb_video/8/0/e/6/80e6bd68d6bbafc06e7cc744a6ece067.jpg"
        }';
    }
    $response = '{
        "type": "message",
        "from": {
            "id": "12345678",
            "name": "sender\'s name"
        },
        "conversation": {
            "id": "abcd1234",
            "name": "conversation\'s name"
        },
        "recipient": {
            "id": "1234abcd",
            "name": "recipient\'s name"
        },
        "attachments": [
            {
                "contentType": "application/vnd.microsoft.card.hero",
                "content": {
                    "title": "title goes here",
                    "subtitle": "subtitle goes here",
                    "text": "descriptive text goes here",
                    "images": [
                        {
                            "url": "https://aka.ms/DuckOnARock",
                            "alt": "picture of a duck",
                            "tap": {
                                "type": "playAudio",
                                "value": "url to an audio track of a duck call goes here"
                            }
                        }
                    ],
                    "buttons": [
                        {
                            "type": "playAudio",
                            "title": "Duck Call",
                            "value": "url to an audio track of a duck call goes here"
                        },
                        {
                            "type": "openUrl",
                            "title": "Watch Video",
                            "image": "https://aka.ms/DuckOnARock",
                            "value": "url goes here of the duck in flight"
                        }
                    ]
                }
            }
        ],
        "replyToId": "5d5cdc723"
    }';
    $res = json_decode($response, true);
    $res["from"] = $info["bot"];
    $res["recipient"] = $info["user"];
    $res["conversation"] = $info["conversation"];
    $res["text"] = $info["text"];
    $res["replyToId"] = $info["id"];
    $res["channelId"] = $info["channelId"];
    if($messageCard) {
        $res["attachments"] = [json_decode($messageCard, true)];
    }elseif($adaptiveCard){
        $response = json_decode($adaptiveCardData, true);
        $response["content"]["image"]["url"] = $adaptiveCard["thumbnail"];
        $response["content"]["media"][0]["url"] = $adaptiveCard["url"];
        $response["content"]["title"] = $adaptiveCard["title"];
        $response["content"]["subtitle"] = $adaptiveCard["singer"];
        $response["contentType"]=$adaptiveCard["type"]==="video"?"application/vnd.microsoft.card.video" :"application/vnd.microsoft.card.audio";
        $response["content"]["aspect"] = $adaptiveCard["type"]==="video" ? "16:9":"4:3";
        $res["attachments"] = [$response];
    }elseif($video){
        $response = json_decode($videoSend, true);
        // $response["contentType"] = ($video["type"]==="video") ? "video/mp4"  :"audio/wav";
        $response["contentUrl"] = $video["url"];
        // $response["name"] = "sanphamweb.com";
        $response["thumbnailUrl"] = $video["thumbnail"];
        $res["attachments"] = [$response];
        
    }
    return $res;
}
function ask_ai($text)
{
    $result = false;
    if (stripos($text, "bai hat") !== false) {
        $result = true;
    }

    if (stripos($text, "bài hát") !== false) {
        $result = true;
    }

    if (stripos($text, "video") !== false) {
        $result = true;
    }

    if($result){
        $search = str_replace("bai hat","",$text);
        $search = str_replace("bài hát","",$search);
        $search = str_replace("video","",$search);
        $search = urlencode($search);
        $videCheck = (stripos($text, "video") !== false) ? "type-audio=video&":"";
        
        $urlApi = "http://media.sanphamweb.com/process.php?title=$search&{$videCheck}artist=&type=1&method=1&link=";
        $dataMedia = file_get_contents($urlApi);
        $dataMedia = json_decode($dataMedia,true);
        $urlMedia = $dataMedia["download128"];
        $thumbnail =  $dataMedia["videoImage"];
        $type = "audio";
        if(isset($dataMedia["videoLink"])){
            $urlMedia = $dataMedia["videoLink"];
            // $thumbnail = $dataMedia["videoImage"];
            $type = "video";
        }
        $result = [
            "url"=>$urlMedia,
            "thumbnail"=>$thumbnail,
            "type"=> $type,
            "title"=>$dataMedia["title"],
            "singer"=>$dataMedia["singer"]
        ];

        
    }

    return $result;
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
            sendLog($message,"29:1WFN32csZeW06pnOG_pHWzgpJBuSvQa6A1iAcFqRlrnQ"); // id congtrieu_it
        }elseif(isset($req["report"])) {
					$message = "$req[message]";
					sendLog($message,"29:1WFN32csZeW06pnOG_pHWzgpJBuSvQa6A1iAcFqRlrnQ"); //id congtrieu_it
		}elseif(isset($req["search"])){
            $result = searchGoogle($req["search"]);
            $req["search"] = "co vao day";
            file_put_contents(__DIR__."/log.txt",print_r($req["search"], true), FILE_APPEND);
            file_put_contents(__DIR__."/log.txt",print_r($result, true), FILE_APPEND);
            exit();
        }else{
          $res = build_response($req);
          if($data = ask_ai($req["text"]))
          {
              $res = build_response($req,$data,null,null);
              file_put_contents(__DIR__."/log.txt",print_r($data, true), FILE_APPEND);
              $res["text"] = "Bai hat cua ban";
          }
          else
          {
              $res["text"] = ask_eve($req["text"]);
          }
          reply($req, $res);
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
    $res = build_response($req,null,null,true);
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

function searchGoogle($key) { 
    $key = urlencode($key);
    $url = "https://www.googleapis.com/customsearch/v1?key=$GLOBALS[KEY_GOOGLE_SEARCH]&cx=$GLOBALS[ID_GOOGLE_SEARCH]&q=$key";
    $result = do_request($url);
    // print_r($url);
    return $result;

}

?>
