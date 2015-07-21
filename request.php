<?php

require_once('SQLTools.php');

$ck_id = '/^[A-Za-z0-9]{6,6}$/';
$ck_key = '/^[A-Za-z0-9]{13,13}$/';
$ck_username = '/^[A-Za-z0-9_]{2,20}$/';
$ck_password =  '/^[A-Za-z0-9!@#$%^&*()_]{2,20}$/';
$ck_sort = '/^[A-Za-z0-9_]{2,20}$/';
$ck_type = '/^[A-Za-z0-9_]{2,20}$/';
$ck_url = '_^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@)?(?:(?!10(?:\.\d{1,3}){3})(?!127(?:\.\d{1,3}){3})(?!169\.254(?:\.\d{1,3}){2})(?!192\.168(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,})))(?::\d{2,5})?(?:/[^\s]*)?$_iuS';

if(!isSet($_POST["action"])) {
	error("no action specified");
}

$action = $_POST["action"];

$mysqli = getmysqli();

switch ($action) {
	case "AddComment":
		$parentID = $_POST['parent'];
		$parentCommentID = $_POST['parentComment'];
		$text = $_POST['text'];
		$key = $_POST['key'];
		if(!preg_match($ck_id, $parentID) || (!preg_match($ck_id, $parentCommentID) && $parentCommentID != "") || !preg_match($ck_key, $key)) {
			error("id is not valid");
		}
		$user = getUser($key, $mysqli);
		$text = $mysqli->real_escape_string($text);
		$result = addComment($parentID, $parentCommentID, $text, $user, $mysqli);
		echo(json_encode(["success" => "true"]));
		break;
	case "GetLink":
		$id = $_POST['id'];
		$sort = "old";
		if(isSet($_POST['sort'])) {
			$sort = $_POST['sort'];
		}
		if(!preg_match($ck_id, $id)) {
			error("id is not valid");
		}
		$link = getLink($id, $sort,$mysqli);
		echo(json_encode($link, JSON_UNESCAPED_SLASHES));
		break;
	case "GetLinks":
		$page = (int) $_POST['page'];
		if($page == 0) $page = 1;
	
		$sort = "new";
		if(isSet($_POST['sort'])) {
			$sort = $_POST['sort'];
		}
		if(!preg_match($ck_sort, $sort)) {
			error("id is not valid");
		}
		$links = getLinks($sort, $page, $mysqli);

		echo(json_encode($links, JSON_UNESCAPED_SLASHES));

		break;
	case "GetUser":
		$user = $_POST['user'];
		$type = $_POST['type'];
		$sort = $_POST['sort'];
		if(!preg_match($ck_username, $user) || !preg_match($ck_username, $type) || !preg_match($ck_username, $sort)) {
			error("user is not valid");
		}
		$user = getUserHistory($user, $type, $sort,$mysqli);
		echo(json_encode($user, JSON_UNESCAPED_SLASHES));
		break;
	case "SignIn":
		$username = $_POST['username'];
		$newPass = $_POST['password'];
		if(!preg_match($ck_username, $username) || !preg_match($ck_password, $newPass)) {
		   error("username/password contains illegal characters");
		}
		$key = signIn($username, $newPass, $mysqli);
		$returnValue = ["key" => $key, "username" => $username];
		echo(json_encode($returnValue, JSON_UNESCAPED_SLASHES));
		break;
	case "SignUp":
		$username = $_POST['username'];
		$newPass = $_POST['password'];
		if(!preg_match($ck_username, $username) || !preg_match($ck_password, $newPass)) {
			error("username/password contains illegal characters");
		}
		$key = createUser($username, $newPass, $mysqli);
		$returnValue = ["key" => $key, "username" => $username];

		echo(json_encode($returnValue, JSON_UNESCAPED_SLASHES));
		break;
	case "UploadPost":
		$url = $_POST['url'];
		$title = $_POST['title'];
		$key = $_POST['key'];
		$text = $_POST['text'];
		$bloggerName = $_POST['bloggerName'];
		if(($text == "" && !preg_match($ck_url, $url)) || !preg_match($ck_key, $key)) {
			error("url contains illegal characters");
		}
		$user = getUser($key, $mysqli);
		$title = $mysqli->real_escape_string($title);
		$bloggerName = $mysqli->real_escape_string($bloggerName);
		$text = $mysqli->real_escape_string($text);
		$result = uploadPost($url, $title, $text, $user, $bloggerName, $mysqli);
		echo(json_encode($result));
		break;
	case "GetBloggers":
		$sort = "new";
		if(isSet($_POST['sort'])) {
			$sort = $_POST['sort'];
		}
		if(!preg_match($ck_sort, $sort)) {
			error("sort is not valid");
		}
		$bloggers = getBloggers($sort, $mysqli);

		echo(json_encode($bloggers, JSON_UNESCAPED_SLASHES));
		
		break;
	case "GetBlogger":
	
		
		$bloggerName = $_POST['bloggerName'];
	
		$sort = "new";
		if(isSet($_POST['sort'])) {
			$sort = $_POST['sort'];
		}
		if(!preg_match($ck_sort, $sort)) {
			error("id is not valid");
		}
		
		$bloggerName = $mysqli->real_escape_string($bloggerName);
		
		$blogger = getBlogger($bloggerName, $sort, $mysqli);

		echo(json_encode($blogger, JSON_UNESCAPED_SLASHES));

		break;
	case "Vote":
		
		$type = $_POST['type'];
		$vote = $_POST['vote'];
		
		$id = $_POST['id'];
		
		if(!preg_match($ck_id, $id)) {
			error("id is not valid");
		}
		
		$dbType;
		$voteType;
		
		if($type == "post") $dbType = "Links";
		else if($type == "comment") $dbType = "Comments";
		else error("Invalid type");
		
		if($vote == "upvote") $voteType = "upvote";
		else if($vote == "downvote") $voteType = "downvote";
		else error("Invalid vote type");
		
		$key = $_POST['key'];
		if(!preg_match($ck_key, $key)) {
			error("key contains illegal characters");
		}
		$user = getUser($key, $mysqli);
		
		echo(json_encode(addVote($user, $id, $dbType, $voteType, $mysqli)));
		
		break;
}

?>