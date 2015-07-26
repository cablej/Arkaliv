<?php

require_once('dbInfo.php');

define("NUM_LINKS", 25);

//returns the database used
function getmysqli() {
	$mysqli = new mysqli(MYSQLI_HOST, MYSQLI_USERNAME, MYSQLI_PASSWORD, MYSQLI_DB_NAME);
	return $mysqli;
}

//gets a username with the specific key
function getUser($key, $mysqli, $require = true) {
	$sql = "SELECT `username` FROM `Sessions` WHERE `key` = '$key'";
	
	$row = query($sql, $mysqli);
	
	if(count($row) == 0) {
		if($require) error("Please log in");
		return "";
	}
	
	return $row[0]["username"];
}

//helper method to encrypt the given password
function cryptPass($input, $rounds = 12){ //Sequence - cryptPass, save hash in db, crypt(input, hash) == hash
	$salt = "";
	$saltChars = array_merge(range('A','Z'), range('a','z'), range(0,9));
	for($i = 0; $i < 22; $i++){
		$salt .= $saltChars[array_rand($saltChars)];
	}
	return crypt($input, sprintf('$2y$%02d$', $rounds) . $salt);
}

//signs in a user with the username and password given
function signIn($username, $password, $mysqli) {
	$sql = "SELECT `username`, `password` FROM `Users` WHERE username = '$username'";
	$row = query_one($sql, $mysqli);
	$hashedPass = $row["password"];
	if(crypt($password, $hashedPass) == $hashedPass) {
		$key = uniqid();
		$sql = "INSERT INTO `Sessions`(`key`, `username`) VALUES ('$key', '$username')";
		if(!$mysqli->query($sql)) {
			error("could not log in");
		}
		
		return $key;
		
	} else {
		error("wrong username/password");
	}
}

//creates a user with the username and password given
function createUser($username, $newPass, $mysqli) {
	$hashedPass = cryptPass($newPass);
	$sql = "SELECT `username` FROM `Users` WHERE username = '$username'";
	if($result = $mysqli->query($sql)) {
		if($result->num_rows == 0) {
			$sql = "INSERT INTO `Users`(`username`, `password`) VALUES ('$username', '$hashedPass')";
			if(!$mysqli->query($sql)) {
				error("could not create user");
			}
			return signIn($username, $newPass, $mysqli);
		} else {
			error("username already used");
		}
	} else {
		error("could not create user");
	}
}

//returns a list of links with the given sort and page
function getLinks($sort, $page, $user, $mysqli) {

	$sort_type = getSortType($sort);
	
	$start_from = ($page-1) * NUM_LINKS;
	
	$sql = "SELECT * FROM `Links` ORDER BY $sort_type LIMIT $start_from, " . NUM_LINKS;

	$links = query_links($sql, $user, $mysqli);
	
	return $links;
}

//returns a list of bloggers with the given sort
function getBloggers($sort, $mysqli) {
	$sql = "SELECT * FROM `Links` WHERE `isBlogPost` = 1";
	
	$blog_posts = query($sql, $mysqli);
	
	$bloggers = [];
	
	foreach($blog_posts as $post) {
		$name = $post["bloggerName"];
		if(!array_key_exists($name, $bloggers)) {
			$bloggers[$name] = ["bloggerName" => $name, "numPosts" => 0, "mostRecentDate" => 0, "totalPoints" => 0];
		}
		$bloggers[$name]["numPosts"]++;
		if(strtotime($post["date"]) > strtotime($bloggers[$name]["mostRecentDate"])) {
			$bloggers[$name]["mostRecentDate"] = $post["date"];
		}
		$bloggers[$name]["totalPoints"] += $post["numUpvotes"] - $post["numDownvotes"];
	}
	
	$bloggers_arr = array_values($bloggers);
	
	usort($bloggers_arr, function($a, $b) {
    	return strtotime($b['mostRecentDate']) - strtotime($a['mostRecentDate']);
	});
	
	return $bloggers_arr;
}

//returns the blogs for the given blogger
function getBlogger($name, $sort, $user, $mysqli) {
	$sort_type = getSortType($sort);
	
	$sql = "SELECT * FROM `Links` WHERE `bloggerName` = '$name' ORDER BY $sort_type";

	$blogger_links = query_links($sql, $user, $mysqli);
	
	return $blogger_links;
}

//returns the info for a given link
function getLink($id, $sort, $user, $mysqli) {
	$link_sql = "SELECT * FROM `Links` WHERE `id` = '$id'";
	
	$sort_type = getSortType($sort);
	$comment_sql = "SELECT * FROM `Comments` WHERE `parent` = '$id' ORDER BY $sort_type";
	
	$link = query_link($link_sql, $comment_sql, $user, $mysqli);
	
	return $link;
}

//gives the way the posts should be sorted
function getSortType($sort) {
	$sort_type = "date";
	
	if($sort == "old") {
		$sort_type = "date";
	} else if($sort == "new") {
		$sort_type = "date DESC";
	}
	
	return $sort_type;
}

//adds the votes to the links/comments given for a specific username
function addVotesToObjects($objects, $username, $mysqli) {
	for($i=0; $i<count($objects); $i++) {
		$objects[$i] = addVotesToObject($objects[$i], $username, $mysqli);
	}
	return $objects;
}

//adds the votes to the link/comment given for a specific username
function addVotesToObject($object, $username, $mysqli) {
	$id = $object["id"];
	$sql = "SELECT * FROM `Votes` WHERE `id` = '$id' AND `username` = '$username'";
	$result = query($sql, $mysqli);
	
	$voteType = "none";
	
	if(count($result) != 0) {
		$voteType = $result[0]['voteType'];
	}
	
	$object["voteType"] = $voteType;
	return $object;
}

//gets the history for a specific user
function getUserHistory($user, $type, $sort, $current_user, $mysqli) {
	
	$sort_type = getSortType($sort);
	
	$links = [];

	$sql = "SELECT * FROM `Links` WHERE `author` = '$user' ORDER BY $sort_type";
	$links = query_links($sql, $current_user, $mysqli);
	
	$sql = "SELECT * FROM `Comments` WHERE `author` = '$user' ORDER BY $sort_type";
	$comments = query_links($sql, $current_user, $mysqli);
	
	return ["user" => $user, "links" => $links, "comments" => $comments];
	
}

//a generic query, returns an associative array
function query($sql, $mysqli) {
	$resultArray = [];
	if($result = $mysqli->query($sql)) {
		while($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$resultArray[] = $row;
		}
	} else {
		error("could not query");
	}
	return $resultArray;
}

//queries exactly one row
function query_one($sql, $mysqli) {
	if($result = $mysqli->query($sql)) {
	    if($result->num_rows == 1) {
	        $row = $result->fetch_array(MYSQLI_ASSOC);
	        return $row;
		} else {
			error("could not query");
		}
	} else {
		error("could not query");
	}
}

//processes a number of posts
function query_links($sql, $user, $mysqli) {
	$links = addVotesToObjects(query($sql, $mysqli), $user, $mysqli);
	return $links;
}

//processes one post
function query_link($link_sql, $comments_sql, $user, $mysqli) {

	$link = [];

	$link_row = addVotesToObject(query_one($link_sql, $mysqli), $user, $mysqli);
	$link["link"] = $link_row;
	
	$comments = query($comments_sql, $mysqli);
	$comments = sortComments($comments);
	$link["comments"] = addVotesToObjects($comments, $user, $mysqli);
	
	return $link;
	
}

//sorts the comments for a post based on their parents
function sortComments($comments, $parentComment = "") {
	$sortedComments = [];
	foreach($comments as $comment) {
		if($comment["parentComment"] == $parentComment) { //on that level
			$sortedComments[] = $comment;
			if(hasComments($comments, $comment)) {
				$sortedComments = array_merge($sortedComments, sortComments($comments, $comment["id"]));
			}
		}
	}
	
	return $sortedComments;
}

//helper method for sortComments
function hasComments($comments, $comment) {
	foreach($comments as $checkComment) {
		if($checkComment["parentComment"] == $comment["id"]) return true;
	}
	return false;
}

//uploads a post with the given parameters
function uploadPost($url, $title, $text, $author, $bloggerName, $mysqli) {
	
	$id = substr(md5(microtime()),rand(0,26),6);
	
	$isBlogPost = $bloggerName != "";
	$isSelf = $text != "";
	
	$sql = "INSERT INTO `Links`(`id`, `url`, `selfText`, `title`, `isBlogPost`, `isSelf`, `bloggerName`, `author`) VALUES ('$id', '$url', '$text', '$title', '$isBlogPost', '$isSelf', '$bloggerName', '$author')";
	if($mysqli->query($sql)) {
		
		return getLink($id, "new", $mysqli);
	} else {
		error("could not add link");
	}
	
}

//adds a comment with the given parameters
function addComment($parentID, $parentCommentID, $text, $author, $mysqli) {
	
	$id = substr(md5(microtime()),rand(0,26),6);
	
	$level = 0;
	
	if($parentCommentID != "") {
		$parentValue = getValue($parentCommentID, "level", "Comments", $mysqli);
		
		$level = $parentValue + 1;
	}
	
	$sql = "INSERT INTO `Comments`(`id`, `parent`, `parentComment`, `text`, `author`, `level`) VALUES ('$id', '$parentID', '$parentCommentID', '$text','$author', '$level')";
	if($mysqli->query($sql)) {
		//added comment, now need to update # of comments
	} else {
		error("could not add comment");
	}
	
	incrementValue($parentID, "numComments", "Links", $mysqli);
	
	return true;
}

//adds a vote with the given parameters
function addVote($user, $id, $dbType, $voteType, $mysqli) {
	$sql = "SELECT `voteType` FROM `Votes` WHERE `username` = '$user' AND `id` = '$id'";
	
	$row = query($sql, $mysqli);
	
	$sql = "INSERT INTO `Votes`(`username`, `type`, `id`, `voteType`) VALUES ('$user', '$dbType', '$id', '$voteType')";
	
	$lastType = "none";
	if(count($row) != 0) {
		$lastType = $row[0]['voteType'];
		$sql = "UPDATE `Votes` SET `type` = '$dbType', `voteType` = '$voteType' WHERE `username` = '$user' AND `id` = '$id'";
	}
	
	if($mysqli->query($sql)) {
	
		if($lastType != "none") {
			incrementValue($id, getVoteColumnType($lastType), $dbType, $mysqli, -1);
		}
	
		incrementValue($id, getVoteColumnType($voteType), $dbType, $mysqli, 1);
			
		return ["success" => "true"];
	} else {
		error("could not add vote");
	}
	
}

function getVoteColumnType($voteType) {
	$voteColumnType = "";
	if($voteType == "upvote") $voteColumnType = "numUpvotes";
	else if($voteType == "downvote") $voteColumnType = "numDownvotes";
	if($voteColumnType == "") error("invalid vote type");
	return $voteColumnType;
}

//generic function to increment a value in the database
function incrementValue($id, $column, $type, $mysqli, $increment = 1) { //$column: column to increment like numComments, numUpvotes..., $type: table to increment, Links or Comments

	$previousValue = getValue($id, $column, $type, $mysqli);

	$newValue = $previousValue + $increment;
	
	updateValue($id, $column, $type, $newValue, $mysqli);

	
}

//generic function to get a value from the database
function getValue($id, $column, $type, $mysqli) {
	
	$value = 0;

	$sql = "SELECT `$column` FROM `$type` WHERE `id` = '$id'";
	
	$row = query_one($sql, $mysqli);
	
	$value = $row[$column];
	
	return $value;
}

//generic function to update a value in the database
function updateValue($id, $column, $type, $newValue, $mysqli) {
	$sql = "UPDATE `$type` SET `$column`= $newValue WHERE `id` = '$id'";
	if($mysqli->query($sql)) {
		return true;
	} else {
		error("could not update link");
	}
}

//terminates the program with an error
function error($message) {
	die(json_encode(["error" => $message], JSON_UNESCAPED_SLASHES));
}

?>