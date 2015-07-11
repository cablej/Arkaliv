<?php

function cryptPass($input, $rounds = 12){ //Sequence - cryptPass, save hash in db, crypt(input, hash) == hash
	$salt = "";
	$saltChars = array_merge(range('A','Z'), range('a','z'), range(0,9));
	for($i = 0; $i < 22; $i++){
		$salt .= $saltChars[array_rand($saltChars)];
	}
	return crypt($input, sprintf('$2y$%02d$', $rounds) . $salt);
}


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

function createUser($username, $hashedPass, $mysqli) {
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

function getmysqli() {
	$mysqli = new mysqli("sql308.byethost10.com", "b10_16388530", "Password changed to protect the innocent", "b10_16388530_arkaliv");
	return $mysqli;
}

function getLinks($sort, $mysqli) {

	$sort_type = getSortType($sort);
	
	$sql = "SELECT * FROM `Links` ORDER BY $sort_type";

	$links = query($sql, $mysqli);
	
	return $links;
}

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

function getBlogger($name, $sort, $mysqli) {
	$sort_type = getSortType($sort);
	
	$sql = "SELECT * FROM `Links` WHERE `bloggerName` = '$name' ORDER BY $sort_type";

	$blogger_links = query($sql, $mysqli);
	
	return $blogger_links;
}


function getLink($id, $sort, $mysqli) {

	$link = [];

	$sql = "SELECT * FROM `Links` WHERE `id` = '$id'";
	$link_row = query_one($sql, $mysqli);
	$link["link"] = $link_row;
	
	$sort_type = getSortType($sort);
	$sql_comments = "SELECT * FROM `Comments` WHERE `parent` = '$id' ORDER BY $sort_type";
	$comments = query($sql_comments, $mysqli);
	$comments = sortComments($comments, "");
	$link["comments"] = $comments;
	
	return $link;
}

function getSortType($sort) {
	$sort_type = "date";
	
	if($sort == "old") {
		$sort_type = "date";
	} else if($sort == "new") {
		$sort_type = "date DESC";
	}
	
	return $sort_type;
}

function getUserHistory($user, $type, $sort, $mysqli) {
	
	$sort_type = getSortType($sort);
	
	$links = [];

	$sql = "SELECT * FROM `Links` WHERE `author` = '$user' ORDER BY $sort_type";
	$links = query($sql, $mysqli);
	
	$sql = "SELECT * FROM `Comments` WHERE `author` = '$user' ORDER BY $sort_type";
	$comments = query($sql, $mysqli);
	
	return ["user" => $user, "links" => $links, "comments" => $comments];
	
}

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

function sortComments($comments, $parentComment) {
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

function hasComments($comments, $comment) {
	foreach($comments as $checkComment) {
		if($checkComment["parentComment"] == $comment["id"]) return true;
	}
	return false;
}

function uploadLink($url, $title, $author, $bloggerName, $mysqli) {
	
	$id = substr(md5(microtime()),rand(0,26),6);
	
	$isBlogPost = $bloggerName != "";
	
	$sql = "INSERT INTO `Links`(`id`, `url`, `title`, `isBlogPost`, `bloggerName`, `author`) VALUES ('$id', '$url', '$title', '$isBlogPost', '$bloggerName', '$author')";
	if($mysqli->query($sql)) {
		
		return getLink($id, "new", $mysqli);
	} else {
		error("could not add link");
	}
	
}

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

function addVote($user, $id, $dbType, $voteType, $mysqli) {
	$sql = "SELECT `voteType` FROM `Votes` WHERE `username` = '$user' AND `id` = '$id'";
	
	$row = query($sql, $mysqli);
	
	if(count($row) != 0) error("user has already voted");
	
	$sql = "INSERT INTO `Votes`(`username`, `type`, `id`, `voteType`) VALUES ('$user', '$dbType', '$id', '$voteType')";
	
	if($mysqli->query($sql)) {
		return ["success" => "true"];
	} else {
		error("could not add vote");
	}
	
}

function incrementValue($id, $column, $type, $mysqli) { //$column: column to increment like numComments, numUpvotes..., $type: table to increment, Links or Comments

	$previousValue = getValue($id, $column, $type, $mysqli);

	$newValue = $previousValue + 1;
	
	updateValue($id, $column, $type, $newValue, $mysqli);

	
}

function getValue($id, $column, $type, $mysqli) {
	
	$value = 0;

	$sql = "SELECT `$column` FROM `$type` WHERE `id` = '$id'";
	
	$row = query_one($sql, $mysqli);
	
	$value = $row[$column];
	
	return $value;
}

function updateValue($id, $column, $type, $newValue, $mysqli) {
	$sql = "UPDATE `Links` SET `$column`= $newValue WHERE `id` = '$id'";
	if($mysqli->query($sql)) {
		return true;
	} else {
		error("could not update link");
	}
}

function getUser($key, $mysqli) {
	$sql = "SELECT `username` FROM `Sessions` WHERE `key` = '$key'";
	
	$row = query_one($sql, $mysqli);
	
	return $row["username"];
}

function error($message) {
	die(json_encode(["error" => $message], JSON_UNESCAPED_SLASHES));
}

?>